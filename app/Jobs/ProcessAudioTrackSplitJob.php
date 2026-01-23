<?php

namespace App\Jobs;

use App\Models\Videojob;
use App\Services\AudioTrackSplitService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAudioTrackSplitJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Maximum execution time in seconds (2 hours)
     */
    public const TIMEOUT_SECONDS = 7200;
    
    /**
     * Maximum number of retry attempts
     */
    public const MAX_RETRIES = 5;
    
    /**
     * Delay between retries in seconds
     */
    public const BACKOFF_SECONDS = 30;
    
    /**
     * Stale job detection threshold in minutes
     */
    public const STALE_JOB_THRESHOLD_MINUTES = 30;
    
    /**
     * How long the job should remain unique in seconds (1 hour)
     */
    public const UNIQUE_FOR_SECONDS = 3600;
    
    public $timeout = self::TIMEOUT_SECONDS;
    public $tries = self::MAX_RETRIES;
    public $backoff = self::BACKOFF_SECONDS;
    public $uniqueFor = self::UNIQUE_FOR_SECONDS;

    public function __construct(
        public Videojob $videoJob
    ) {
    }

    public function uniqueId(): string
    {
        return 'audio-track-split-' . $this->videoJob->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(AudioTrackSplitService $service)
    {
        // Set PHP execution time limit for long-running audio processing
        set_time_limit(self::TIMEOUT_SECONDS);
        
        $start_time = time();

        // Mark stale jobs as errors
        Videojob::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_JOB_THRESHOLD_MINUTES))
            ->update(['status' => 'error']);

        $videoJob = $this->videoJob;
        
        try {
            Log::info("Starting audio track split job processing", ['job_id' => $videoJob->id]);

            // Check for existing processing using cache
            $lockKey = $this->getProcessingLockKey($videoJob->id);
            $isLocked = \Cache::has($lockKey);
            
            if ($isLocked && $videoJob->status == Videojob::STATUS_PROCESSING) {
                $videoJob->status = VideoJob::STATUS_APPROVED;
                $videoJob->save();
                Log::info("Job is already being processed, aborting", ['job_id' => $videoJob->id]);
                return;
            }

            // Set lock for 30 minutes
            \Cache::put($lockKey, true, now()->addMinutes(30));

            $videoJob->resetProgress(Videojob::STATUS_PROCESSING);
            $videoJob->job_time = time() - $start_time;
            $videoJob->save();

            Log::info("Starting audio track split conversion", [
                'job_id' => $videoJob->id,
            ]);

            $service->startProcess($videoJob);

            // Release lock on successful completion
            \Cache::forget($this->getProcessingLockKey($videoJob->id));

            Log::info('Audio track split conversion completed', [
                'job_id' => $videoJob->id,
                'url' => $videoJob->url,
                'duration' => $videoJob->job_time
            ]);

        } catch (\Exception $e) {
            // Release lock on error
            \Cache::forget($this->getProcessingLockKey($videoJob->id));
            
            Log::error('Error while converting audio track split job', [
                'job_id' => $videoJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $videoJob->job_time = time() - $start_time;
            $videoJob->status = Videojob::STATUS_ERROR;
            $videoJob->save();
            
            throw $e;
        }
    }

    /**
     * Get the cache key for processing lock
     */
    private function getProcessingLockKey(int $jobId): string
    {
        return "audio_track_split_job_processing_{$jobId}";
    }

    public function retryUntil(): DateTimeInterface
    {
       return now()->addDay();
    }
}

