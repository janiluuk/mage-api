<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for job processors with common functionality
 */
abstract class AbstractJobProcessor implements JobProcessorInterface
{
    /**
     * Default timeout in seconds (7.5 hours for long-running jobs)
     */
    protected int $timeout = 27200;
    
    /**
     * Default stale job threshold in minutes
     */
    protected int $staleThreshold = 15;
    
    public function getTimeout(): int
    {
        return $this->timeout;
    }
    
    public function getStaleThreshold(): int
    {
        return $this->staleThreshold;
    }
    
    /**
     * Mark stale jobs as errors
     */
    protected function markStaleJobsAsErrors(): void
    {
        Videojob::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($this->getStaleThreshold()))
            ->update(['status' => 'error']);
    }
    
    /**
     * Get cache lock key for processing
     */
    protected function getProcessingLockKey(int $jobId): string
    {
        return "video_job_processing_{$jobId}";
    }
    
    /**
     * Check if job is already being processed
     */
    protected function isJobLocked(int $jobId): bool
    {
        return \Cache::has($this->getProcessingLockKey($jobId));
    }
    
    /**
     * Lock the job for processing
     */
    protected function lockJob(int $jobId, int $minutes = 30): void
    {
        \Cache::put($this->getProcessingLockKey($jobId), true, now()->addMinutes($minutes));
    }
    
    /**
     * Release the job lock
     */
    protected function unlockJob(int $jobId): void
    {
        \Cache::forget($this->getProcessingLockKey($jobId));
    }
    
    /**
     * Initialize job for processing
     */
    protected function initializeJob(Videojob $videoJob): void
    {
        $videoJob->resetProgress(Videojob::STATUS_PROCESSING);
        $videoJob->save();
    }
    
    /**
     * Log job start
     */
    protected function logJobStart(Videojob $videoJob, string $processorName): void
    {
        Log::info("Starting {$processorName} job processing", [
            'job_id' => $videoJob->id,
            'processor' => $processorName
        ]);
    }
    
    /**
     * Log job completion
     */
    protected function logJobCompletion(Videojob $videoJob, string $processorName, int $duration): void
    {
        Log::info("{$processorName} job completed", [
            'job_id' => $videoJob->id,
            'processor' => $processorName,
            'duration' => $duration,
            'url' => $videoJob->url
        ]);
    }
    
    /**
     * Log job error
     */
    protected function logJobError(Videojob $videoJob, string $processorName, \Exception $e): void
    {
        Log::error("Error in {$processorName} job", [
            'job_id' => $videoJob->id,
            'processor' => $processorName,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
