<?php

namespace App\Jobs;

use App\Models\Videojob;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
     * Execute the job using the unified handler.
     *
     * @return void
     */
    public function handle(UnifiedJobHandler $handler)
    {
        $handler->handle(
            $this->videoJob,
            0,
            null,
            fn($delay) => $this->release($delay)
        );
    }

    public function retryUntil(): DateTimeInterface
    {
       return now()->addDay();
    }
}
