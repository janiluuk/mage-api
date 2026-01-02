<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Videojob;
use App\Services\DeforumProcessingService;
use Illuminate\Support\Facades\Log;

class ProcessDeforumJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Maximum execution time in seconds (7.5 hours)
     * Long timeout needed for Deforum animation processing
     */
    public const TIMEOUT_SECONDS = 27200;
    
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
    public const STALE_JOB_THRESHOLD_MINUTES = 15;
    
    /**
     * How long the job should remain unique in seconds (1 hour)
     */
    public const UNIQUE_FOR_SECONDS = 3600;
    
    public $timeout = self::TIMEOUT_SECONDS;
    public $tries = 200; // Higher retry count for Deforum jobs due to external dependencies
    public $backoff = self::BACKOFF_SECONDS;
    public $uniqueFor = self::UNIQUE_FOR_SECONDS;

    public function __construct(public Videojob $videoJob, public int $previewFrames = 0, public ?int $extendFromJobId = null)
    {

    }

    public function uniqueId(): string
    {
        return $this->videoJob->id . '-' . $this->previewFrames . '-' . ($this->extendFromJobId ?? 'base');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(DeforumProcessingService $service)
    {
        // Set PHP execution time limit for long-running Deforum processing
        set_time_limit(self::TIMEOUT_SECONDS);
        
        $start_time = time();

        // Mark stale jobs as errors
        Videojob::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_JOB_THRESHOLD_MINUTES))
            ->update(['status' => 'error']);


        $processingJobs = Videojob::where('status', VideoJob::STATUS_PROCESSING)->count();
        $deforumJobs = Videojob::where('status', VideoJob::STATUS_PROCESSING)->where('generator', 'deforum')->count();

        if ($deforumJobs > 0 && $this->previewFrames == 0 && (!$this->videoJob || $processingJobs > 0)) {
            if ($this->videoJob && $this->videoJob->status == VideoJob::STATUS_PROCESSING) {
                $this->videoJob->status = VideoJob::STATUS_APPROVED;
                $this->videoJob->save();
            }
            if ($this->videoJob->generator != 'deforum') {
                $this->fail("not a deforum job (".$this->videoJob->generator.")");
            }
            Log::info("Found existing process, aborting..");
            return $this->release($this->backoff);
        }
        if ($this->videoJob) {
            $videoJob = $this->videoJob;
            try {
                $pids = false;
                Log::info("Starting deforum job for #" . $videoJob->id);

                exec('ps aux | grep -i deforum.py | grep -i \"\-\-jobid=' . $videoJob->id . '\" | grep -v grep', $pids);
                if (!empty($pids) && $videoJob->status == Videojob::STATUS_PROCESSING) {
                    $videoJob->status = VideoJob::STATUS_APPROVED;
                    $videoJob->save();
                    Log::info("Found existing process, aborting..");
                    return;
                }

                $videoJob->resetProgress(Videojob::STATUS_PROCESSING);
                $videoJob->job_time = time()-$start_time;
                if ($videoJob->frame_count > 0) {
                    $videoJob->estimated_time_left = $videoJob->frame_count * 6;
                    $videoJob->save();
                }
                $targetFile = implode("/", [config('app.paths.processed'), $videoJob->outfile]);
                $targetUrl = config('app.url') . '/processed/' . $videoJob->outfile;
                
                Log::info("Starting " . ($this->previewFrames ? " PREVIEW " : "") . "conversion for {$videoJob->filename} to {$targetFile} URL: ($targetUrl} ");
                
                $service->startProcess($videoJob, $this->previewFrames, $this->extendFromJobId);

                if (file_exists($targetFile) && $this->previewFrames == 0) {

                    $videoJob->job_time = time() - $start_time;
                    $videoJob->progress = 100;
                    $videoJob->estimated_time_left = 0;
                    $videoJob->url = $targetUrl;
                    $videoJob->status = 'finished';
                    $videoJob->save();
                    Log::info('Successfully converted {url} in {duration}', ['url' => $videoJob->url, 'duration' => $videoJob->job_time]);
                }

            } catch (\Exception $e) {
                Log::error('Error while converting a video job: {error} ', ['error' => $e->getMessage()]);
                $videoJob->job_time = time() - $start_time;
                $videoJob->status = 'error';
                $videoJob->save();
                $this->fail($e->getMessage());
            }
        }
    }
    
}