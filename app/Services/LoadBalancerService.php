<?php

namespace App\Services;

use App\Models\GeneratorInstance;
use App\Models\InstanceJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LoadBalancerService
{
    /**
     * Select the best instance for a job based on load balancing strategy.
     *
     * @param string|null $type Filter by instance type (stable_diffusion_forge or comfyui)
     * @param string $strategy Load balancing strategy: 'least_loaded' or 'round_robin'
     * @return GeneratorInstance|null
     */
    public function selectInstance(?string $type = null, string $strategy = 'least_loaded', bool $preferHealthy = true): ?GeneratorInstance
    {
        $query = GeneratorInstance::enabled();

        if ($type) {
            $query->where('type', $type);
        }

        // Prefer healthy instances, but fallback to all if none are healthy
        if ($preferHealthy) {
            $healthyInstances = (clone $query)->where('health_status', 'online')->get();
            $instances = $healthyInstances->isNotEmpty() ? $healthyInstances : $query->get();
        } else {
            $instances = $query->get();
        }

        if ($instances->isEmpty()) {
            Log::warning('LoadBalancer: No enabled instances found', ['type' => $type, 'prefer_healthy' => $preferHealthy]);
            return null;
        }

        // Refresh queue counts from actual database state (optimized)
        $this->refreshQueueCounts($instances);

        return match ($strategy) {
            'least_loaded' => $this->selectLeastLoaded($instances),
            'round_robin' => $this->selectRoundRobin($instances),
            default => $this->selectLeastLoaded($instances),
        };
    }

    /**
     * Select the instance with the least total load.
     *
     * @param \Illuminate\Support\Collection $instances
     * @return GeneratorInstance|null
     */
    protected function selectLeastLoaded(\Illuminate\Support\Collection $instances): ?GeneratorInstance
    {
        // Consider both load and health status in selection
        return $instances->sortBy(function ($instance) {
            $load = $instance->getTotalLoad();
            // Add penalty for degraded instances
            $healthPenalty = $instance->health_status === 'degraded' ? 10 : 0;
            return $load + $healthPenalty;
        })->first();
    }

    /**
     * Select instance using round-robin strategy (for now, falls back to least loaded).
     * TODO: Implement proper round-robin with persistent state.
     *
     * @param \Illuminate\Support\Collection $instances
     * @return GeneratorInstance|null
     */
    protected function selectRoundRobin(\Illuminate\Support\Collection $instances): ?GeneratorInstance
    {
        // For now, use least loaded. Proper round-robin would require tracking last selected instance
        return $this->selectLeastLoaded($instances);
    }

    /**
     * Refresh queue counts for instances based on actual database state.
     *
     * @param \Illuminate\Support\Collection $instances
     * @return void
     */
    protected function refreshQueueCounts(\Illuminate\Support\Collection $instances): void
    {
        if ($instances->isEmpty()) {
            return;
        }

        $instanceIds = $instances->pluck('id');

        // Bulk fetch queue and processing counts for optimization
        $queueCounts = InstanceJob::whereIn('instance_id', $instanceIds)
            ->where('status', InstanceJob::STATUS_QUEUED)
            ->selectRaw('instance_id, COUNT(*) as count')
            ->groupBy('instance_id')
            ->pluck('count', 'instance_id');

        $processingCounts = InstanceJob::whereIn('instance_id', $instanceIds)
            ->where('status', InstanceJob::STATUS_PROCESSING)
            ->selectRaw('instance_id, COUNT(*) as count')
            ->groupBy('instance_id')
            ->pluck('count', 'instance_id');

        // Batch update instances that need updating
        $updates = [];
        foreach ($instances as $instance) {
            $queueCount = $queueCounts->get($instance->id, 0);
            $processingCount = $processingCounts->get($instance->id, 0);

            if ($instance->queue_size != $queueCount || $instance->processing_count != $processingCount) {
                $updates[] = [
                    'id' => $instance->id,
                    'queue_size' => $queueCount,
                    'processing_count' => $processingCount,
                    'last_queue_check_at' => now(),
                ];
            }
        }

        // Bulk update
        if (!empty($updates)) {
            foreach ($updates as $update) {
                GeneratorInstance::where('id', $update['id'])->update([
                    'queue_size' => $update['queue_size'],
                    'processing_count' => $update['processing_count'],
                    'last_queue_check_at' => $update['last_queue_check_at'],
                ]);
            }
        }
    }

    /**
     * Assign a video job to an instance.
     *
     * @param int $videoJobId
     * @param GeneratorInstance $instance
     * @return InstanceJob
     */
    public function assignJobToInstance(int $videoJobId, GeneratorInstance $instance): InstanceJob
    {
        // Use transaction to ensure atomicity
        return DB::transaction(function () use ($videoJobId, $instance) {
            $instanceJob = InstanceJob::create([
                'instance_id' => $instance->id,
                'video_job_id' => $videoJobId,
                'status' => InstanceJob::STATUS_QUEUED,
                'assigned_at' => now(),
            ]);

            // Update instance queue count
            $instance->incrementQueueSize();

            Log::info('LoadBalancer: Job assigned to instance', [
                'instance_id' => $instance->id,
                'instance_name' => $instance->name,
                'video_job_id' => $videoJobId,
                'instance_queue_size' => $instance->queue_size,
            ]);

            return $instanceJob;
        });
    }

    /**
     * Mark a job as started on an instance.
     *
     * @param int $videoJobId
     * @return InstanceJob|null
     */
    public function markJobAsStarted(int $videoJobId): ?InstanceJob
    {
        $instanceJob = InstanceJob::where('video_job_id', $videoJobId)
            ->where('status', InstanceJob::STATUS_QUEUED)
            ->first();

        if (!$instanceJob) {
            Log::warning('LoadBalancer: Could not find queued instance job to mark as started', [
                'video_job_id' => $videoJobId,
            ]);
            return null;
        }

        $instanceJob->markAsStarted();
        $instance = $instanceJob->instance;

        // Move from queue to processing
        $instance->decrementQueueSize();
        $instance->incrementProcessingCount();

        Log::info('LoadBalancer: Job started on instance', [
            'instance_id' => $instance->id,
            'video_job_id' => $videoJobId,
            'instance_processing_count' => $instance->processing_count,
        ]);

        return $instanceJob;
    }

    /**
     * Mark a job as completed.
     *
     * @param int $videoJobId
     * @return InstanceJob|null
     */
    public function markJobAsCompleted(int $videoJobId): ?InstanceJob
    {
        $instanceJob = InstanceJob::where('video_job_id', $videoJobId)
            ->whereIn('status', [InstanceJob::STATUS_QUEUED, InstanceJob::STATUS_PROCESSING])
            ->first();

        if (!$instanceJob) {
            Log::warning('LoadBalancer: Could not find instance job to mark as completed', [
                'video_job_id' => $videoJobId,
            ]);
            return null;
        }

        $instance = $instanceJob->instance;

        // Update counters based on current status
        if ($instanceJob->status === InstanceJob::STATUS_QUEUED) {
            $instance->decrementQueueSize();
        } elseif ($instanceJob->status === InstanceJob::STATUS_PROCESSING) {
            $instance->decrementProcessingCount();
        }

        $instanceJob->markAsCompleted();

        Log::info('LoadBalancer: Job completed on instance', [
            'instance_id' => $instance->id,
            'video_job_id' => $videoJobId,
            'processing_time_seconds' => $instanceJob->processing_time_seconds,
        ]);

        return $instanceJob;
    }

    /**
     * Mark a job as failed.
     *
     * @param int $videoJobId
     * @return InstanceJob|null
     */
    public function markJobAsFailed(int $videoJobId): ?InstanceJob
    {
        $instanceJob = InstanceJob::where('video_job_id', $videoJobId)
            ->whereIn('status', [InstanceJob::STATUS_QUEUED, InstanceJob::STATUS_PROCESSING])
            ->first();

        if (!$instanceJob) {
            return null;
        }

        $instance = $instanceJob->instance;

        // Update counters based on current status
        if ($instanceJob->status === InstanceJob::STATUS_QUEUED) {
            $instance->decrementQueueSize();
        } elseif ($instanceJob->status === InstanceJob::STATUS_PROCESSING) {
            $instance->decrementProcessingCount();
        }

        $instanceJob->markAsFailed();

        return $instanceJob;
    }

    /**
     * Mark a job as cancelled.
     *
     * @param int $videoJobId
     * @return InstanceJob|null
     */
    public function markJobAsCancelled(int $videoJobId): ?InstanceJob
    {
        $instanceJob = InstanceJob::where('video_job_id', $videoJobId)
            ->whereIn('status', [InstanceJob::STATUS_QUEUED, InstanceJob::STATUS_PROCESSING])
            ->first();

        if (!$instanceJob) {
            return null;
        }

        $instance = $instanceJob->instance;

        // Update counters based on current status
        if ($instanceJob->status === InstanceJob::STATUS_QUEUED) {
            $instance->decrementQueueSize();
        } elseif ($instanceJob->status === InstanceJob::STATUS_PROCESSING) {
            $instance->decrementProcessingCount();
        }

        $instanceJob->markAsCancelled();

        return $instanceJob;
    }
}

