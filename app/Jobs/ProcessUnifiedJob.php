<?php

namespace App\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Videojob;

/**
 * Unified job class that handles all processing types
 * This replaces ProcessVideoJob, ProcessDeforumJob, ProcessAudioTrackSplitJob, and ProcessBeatMatchMusicVideoJob
 */
class ProcessUnifiedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Maximum execution time in seconds (7.5 hours)
     */
    public const TIMEOUT_SECONDS = 27200;
    
    /**
     * Maximum number of retry attempts
     */
    public const MAX_RETRIES = 200;
    
    /**
     * Delay between retries in seconds
     */
    public const BACKOFF_SECONDS = 30;
    
    /**
     * How long the job should remain unique in seconds (1 hour)
     */
    public const UNIQUE_FOR_SECONDS = 3600;
    
    public $timeout = self::TIMEOUT_SECONDS;
    public $tries = self::MAX_RETRIES;
    public $backoff = self::BACKOFF_SECONDS;
    public $uniqueFor = self::UNIQUE_FOR_SECONDS;

    public function __construct(
        public Videojob $videoJob, 
        public int $previewFrames = 0, 
        public ?int $extendFromJobId = null
    ) {
    }

    public function uniqueId(): string
    {
        $id = $this->videoJob->id . '-' . $this->previewFrames;
        if ($this->extendFromJobId !== null) {
            $id .= '-' . $this->extendFromJobId;
        }
        return $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(UnifiedJobHandler $handler)
    {
        $handler->handle(
            $this->videoJob,
            $this->previewFrames,
            $this->extendFromJobId,
            fn($delay) => $this->release($delay)
        );
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }
}
