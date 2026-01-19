<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratorInstance;
use App\Models\InstanceJob;
use App\Models\Videojob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InstanceStatusController extends Controller
{
    /**
     * Get comprehensive status for all instances including metrics.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $instances = GeneratorInstance::with(['instanceJobs' => function ($query) {
            $query->whereIn('status', [InstanceJob::STATUS_QUEUED, InstanceJob::STATUS_PROCESSING])
                  ->orderBy('assigned_at', 'desc')
                  ->limit(10);
        }])->get();

        $instancesData = $instances->map(function ($instance) {
            // Get current job (if any)
            $currentJob = InstanceJob::where('instance_id', $instance->id)
                ->where('status', InstanceJob::STATUS_PROCESSING)
                ->with('videoJob')
                ->first();

            // Get recent job history (last 10 completed)
            $recentJobs = InstanceJob::where('instance_id', $instance->id)
                ->where('status', InstanceJob::STATUS_COMPLETED)
                ->orderBy('completed_at', 'desc')
                ->limit(10)
                ->with('videoJob')
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'video_job_id' => $job->video_job_id,
                        'processing_time_seconds' => $job->processing_time_seconds,
                        'completed_at' => $job->completed_at?->toIso8601String(),
                    ];
                });

            return [
                'id' => $instance->id,
                'name' => $instance->name,
                'url' => $instance->url,
                'type' => $instance->type,
                'enabled' => $instance->enabled,
                'queue_size' => $instance->queue_size,
                'processing_count' => $instance->processing_count,
                'total_load' => $instance->getTotalLoad(),
                'current_model' => $instance->current_model,
                'gpu_utilization' => $instance->gpu_utilization,
                'cpu_utilization' => $instance->cpu_utilization,
                'memory_utilization' => $instance->memory_utilization,
                'health_status' => $instance->health_status,
                'last_health_check_at' => $instance->last_health_check_at?->toIso8601String(),
                'last_queue_check_at' => $instance->last_queue_check_at?->toIso8601String(),
                'current_job' => $currentJob ? [
                    'id' => $currentJob->id,
                    'video_job_id' => $currentJob->video_job_id,
                    'video_job' => $currentJob->videoJob ? [
                        'id' => $currentJob->videoJob->id,
                        'prompt' => $currentJob->videoJob->prompt,
                        'status' => $currentJob->videoJob->status,
                        'progress' => $currentJob->videoJob->progress,
                    ] : null,
                    'started_at' => $currentJob->started_at?->toIso8601String(),
                ] : null,
                'recent_jobs' => $recentJobs,
            ];
        });

        // Get FFMpeg encoding status
        $ffmpegStatus = $this->getFFMpegStatus();

        return response()->json([
            'instances' => $instancesData,
            'ffmpeg' => $ffmpegStatus,
            'summary' => [
                'total_instances' => $instances->count(),
                'enabled_instances' => $instances->where('enabled', true)->count(),
                'online_instances' => $instances->where('health_status', 'online')->count(),
                'total_queue_size' => $instances->sum('queue_size'),
                'total_processing' => $instances->sum('processing_count'),
            ],
        ]);
    }

    /**
     * Get FFMpeg encoding status.
     *
     * @return array
     */
    protected function getFFMpegStatus(): array
    {
        $encodingJobs = Videojob::whereNotNull('encoding_status')
            ->where('encoding_status', '!=', 'completed')
            ->get();

        $activeEncoding = Videojob::where('encoding_status', 'encoding')->count();
        $pendingEncoding = Videojob::where('encoding_status', 'pending')->count();

        $activeJobs = Videojob::where('encoding_status', 'encoding')
            ->select('id', 'original_filename', 'encoding_started_at', 'status', 'progress')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'filename' => $job->original_filename,
                    'started_at' => $job->encoding_started_at?->toIso8601String(),
                    'status' => $job->status,
                    'progress' => $job->progress,
                ];
            });

        return [
            'active_encoding_count' => $activeEncoding,
            'pending_encoding_count' => $pendingEncoding,
            'total_queue_size' => $pendingEncoding + $activeEncoding,
            'active_jobs' => $activeJobs,
        ];
    }

    /**
     * Get metrics history for an instance.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function metricsHistory(int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);

        $history = DB::table('instance_metrics_history')
            ->where('instance_id', $instance->id)
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'recorded_at' => $record->recorded_at,
                    'gpu_utilization' => $record->gpu_utilization,
                    'cpu_utilization' => $record->cpu_utilization,
                    'memory_utilization' => $record->memory_utilization,
                    'queue_size' => $record->queue_size,
                    'processing_count' => $record->processing_count,
                    'health_status' => $record->health_status,
                    'current_model' => $record->current_model,
                ];
            });

        return response()->json([
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
            ],
            'history' => $history,
        ]);
    }

    /**
     * Get job history for an instance.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function jobHistory(int $id): JsonResponse
    {
        $instance = GeneratorInstance::findOrFail($id);

        $jobs = InstanceJob::where('instance_id', $instance->id)
            ->where('status', InstanceJob::STATUS_COMPLETED)
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->with('videoJob')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'video_job_id' => $job->video_job_id,
                    'processing_time_seconds' => $job->processing_time_seconds,
                    'assigned_at' => $job->assigned_at?->toIso8601String(),
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                    'video_job' => $job->videoJob ? [
                        'id' => $job->videoJob->id,
                        'prompt' => $job->videoJob->prompt,
                        'generator' => $job->videoJob->generator,
                    ] : null,
                ];
            });

        return response()->json([
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
            ],
            'jobs' => $jobs,
        ]);
    }
}


