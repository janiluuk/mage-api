<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;
use App\Services\ComfyUI\ComfyUIClient;
use App\Services\LoadBalancerService;

/**
 * Processor for ComfyUI workflow jobs
 */
class ComfyUIJobProcessor extends AbstractJobProcessor
{
    public function __construct(
        private ComfyUIClient $comfyClient,
        private LoadBalancerService $loadBalancer
    ) {
    }
    
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void
    {
        $startTime = time();
        
        try {
            $this->logJobStart($videoJob, 'ComfyUI');
            $this->markStaleJobsAsErrors();
            
            // Mark job as started for load balancing
            $this->loadBalancer->markJobAsStarted($videoJob->id);
            
            // Check if already being processed
            if ($this->isJobLocked($videoJob->id) && $videoJob->status == Videojob::STATUS_PROCESSING && $previewFrames == 0) {
                $videoJob->status = Videojob::STATUS_APPROVED;
                $videoJob->save();
                $this->logJobStart($videoJob, 'ComfyUI - Already Processing');
                return;
            }
            
            // Lock the job
            $this->lockJob($videoJob->id);
            
            // Initialize job
            $this->initializeJob($videoJob);
            $videoJob->job_time = time() - $startTime;
            $videoJob->save();
            
            // Get workflow from generation_parameters
            $params = json_decode($videoJob->generation_parameters, true);
            $workflow = $params['workflow'] ?? null;
            
            if (!$workflow) {
                throw new \Exception('No ComfyUI workflow provided in generation_parameters');
            }
            
            // Process with ComfyUI
            // This is a placeholder - actual implementation would use ComfyUIClient methods
            // to queue the prompt, monitor progress, and retrieve results
            // $this->comfyClient->queuePrompt($workflow);
            
            // Release lock and mark complete
            $this->unlockJob($videoJob->id);
            $this->loadBalancer->markJobAsCompleted($videoJob->id);
            
            $duration = time() - $startTime;
            $this->logJobCompletion($videoJob, 'ComfyUI', $duration);
            
        } catch (\Exception $e) {
            $this->unlockJob($videoJob->id);
            $this->loadBalancer->markJobAsFailed($videoJob->id);
            $this->logJobError($videoJob, 'ComfyUI', $e);
            
            $videoJob->job_time = time() - $startTime;
            $videoJob->queued_at = \Carbon\Carbon::now();
            $videoJob->retries += 1;
            $videoJob->save();
            
            throw $e;
        }
    }
    
    public function supports(Videojob $videoJob): bool
    {
        $params = $videoJob->generation_parameters ? json_decode($videoJob->generation_parameters, true) : [];
        return isset($params['jobType']) && $params['jobType'] === 'comfyui-workflow';
    }
}
