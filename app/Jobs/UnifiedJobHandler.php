<?php

namespace App\Jobs;

use App\Models\Videojob;
use App\Services\JobProcessors\JobProcessorFactory;
use App\Services\LoadBalancerService;
use Illuminate\Support\Facades\Log;

/**
 * Unified handler for processing all job types
 */
class UnifiedJobHandler
{
    public function __construct(
        private JobProcessorFactory $processorFactory,
        private LoadBalancerService $loadBalancer
    ) {
    }
    
    /**
     * Handle job processing with concurrency control
     *
     * @param Videojob $videoJob
     * @param int $previewFrames
     * @param int|null $extendFromJobId
     * @param callable|null $releaseCallback Callback to release job back to queue
     * @return void
     */
    public function handle(
        Videojob $videoJob, 
        int $previewFrames = 0, 
        ?int $extendFromJobId = null,
        ?callable $releaseCallback = null
    ): void {
        // Check concurrent job limit
        $maxConcurrentJobs = config('app.video_processing.max_concurrent_jobs', 1);
        $processingJobs = Videojob::where('status', Videojob::STATUS_PROCESSING)->count();

        if ($previewFrames == 0 && $maxConcurrentJobs > 0 && $processingJobs >= $maxConcurrentJobs) {
            if ($videoJob->status == Videojob::STATUS_PROCESSING) {
                $videoJob->status = Videojob::STATUS_APPROVED;
                $videoJob->save();
            }
            
            Log::info("Maximum concurrent jobs reached, requeueing", [
                'current_jobs' => $processingJobs,
                'max_allowed' => $maxConcurrentJobs
            ]);

            if ($releaseCallback) {
                $releaseCallback(10);
            }
            return;
        }
        
        // Get the appropriate processor
        $processor = $this->processorFactory->getProcessor($videoJob);
        
        // Set timeout
        set_time_limit($processor->getTimeout());
        
        // Process the job
        $processor->process($videoJob, $previewFrames, $extendFromJobId);
    }
}
