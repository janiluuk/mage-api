<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;
use App\Services\VideoProcessingService;
use App\Services\LoadBalancerService;

/**
 * Processor for Stable Diffusion (Vid2Vid) jobs
 */
class StableDiffusionJobProcessor extends AbstractJobProcessor
{
    public function __construct(
        private VideoProcessingService $videoProcessingService,
        private LoadBalancerService $loadBalancer
    ) {
    }
    
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void
    {
        $startTime = time();
        
        try {
            $this->logJobStart($videoJob, 'StableDiffusion');
            $this->markStaleJobsAsErrors();
            
            // Mark job as started for load balancing
            $this->loadBalancer->markJobAsStarted($videoJob->id);
            
            // Check if already being processed
            if ($this->isJobLocked($videoJob->id) && $videoJob->status == Videojob::STATUS_PROCESSING && $previewFrames == 0) {
                $videoJob->status = Videojob::STATUS_APPROVED;
                $videoJob->save();
                $this->logJobStart($videoJob, 'StableDiffusion - Already Processing');
                return;
            }
            
            // Lock the job
            $this->lockJob($videoJob->id);
            
            // Initialize job
            $this->initializeJob($videoJob);
            $videoJob->job_time = time() - $startTime;
            
            if ($videoJob->frame_count > 0) {
                $videoJob->estimated_time_left = ($videoJob->frame_count * 10) + 5;
                $videoJob->save();
            }
            
            // Process the video
            $this->videoProcessingService->startProcess($videoJob, $previewFrames, $extendFromJobId);
            
            // Release lock and mark complete
            $this->unlockJob($videoJob->id);
            $this->loadBalancer->markJobAsCompleted($videoJob->id);
            
            $duration = time() - $startTime;
            $this->logJobCompletion($videoJob, 'StableDiffusion', $duration);
            
        } catch (\Exception $e) {
            $this->unlockJob($videoJob->id);
            $this->loadBalancer->markJobAsFailed($videoJob->id);
            $this->logJobError($videoJob, 'StableDiffusion', $e);
            
            $videoJob->job_time = time() - $startTime;
            $videoJob->queued_at = \Carbon\Carbon::now();
            $videoJob->retries += 1;
            $videoJob->save();
            
            throw $e;
        }
    }
    
    public function supports(Videojob $videoJob): bool
    {
        return $videoJob->generator !== 'deforum';
    }
}
