<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;
use App\Services\DeforumProcessingService;
use App\Services\LoadBalancerService;

/**
 * Processor for Deforum animation jobs
 */
class DeforumJobProcessor extends AbstractJobProcessor
{
    public function __construct(
        private DeforumProcessingService $deforumProcessingService,
        private LoadBalancerService $loadBalancer
    ) {
    }
    
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void
    {
        $startTime = time();
        
        try {
            $this->logJobStart($videoJob, 'Deforum');
            $this->markStaleJobsAsErrors();
            
            // Mark job as started for load balancing
            $this->loadBalancer->markJobAsStarted($videoJob->id);
            
            // Check for existing Deforum processes
            $pids = false;
            exec('ps aux | grep -i deforum.py | grep -i "\-\-jobid=' . $videoJob->id . '" | grep -v grep', $pids);
            
            if (!empty($pids) && $videoJob->status == Videojob::STATUS_PROCESSING) {
                $videoJob->status = Videojob::STATUS_APPROVED;
                $videoJob->save();
                $this->logJobStart($videoJob, 'Deforum - Already Processing');
                return;
            }
            
            // Initialize job
            $this->initializeJob($videoJob);
            $videoJob->job_time = time() - $startTime;
            
            if ($videoJob->frame_count > 0) {
                $videoJob->estimated_time_left = $videoJob->frame_count * 6;
                $videoJob->save();
            }
            
            $targetFile = implode("/", [config('app.paths.processed'), $videoJob->outfile]);
            $targetUrl = config('app.url') . '/processed/' . $videoJob->outfile;
            
            // Process with Deforum
            $this->deforumProcessingService->startProcess($videoJob, $previewFrames, $extendFromJobId);
            
            // Check for successful completion
            if (file_exists($targetFile) && $previewFrames == 0) {
                $videoJob->job_time = time() - $startTime;
                $videoJob->progress = 100;
                $videoJob->estimated_time_left = 0;
                $videoJob->url = $targetUrl;
                $videoJob->status = 'finished';
                $videoJob->save();
                
                $this->loadBalancer->markJobAsCompleted($videoJob->id);
                $this->logJobCompletion($videoJob, 'Deforum', $videoJob->job_time);
            }
            
        } catch (\Exception $e) {
            $this->loadBalancer->markJobAsFailed($videoJob->id);
            $this->logJobError($videoJob, 'Deforum', $e);
            
            $videoJob->job_time = time() - $startTime;
            $videoJob->status = 'error';
            $videoJob->save();
            
            throw $e;
        }
    }
    
    public function supports(Videojob $videoJob): bool
    {
        return $videoJob->generator === 'deforum';
    }
}
