<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;
use App\Services\AudioTrackSplitService;

/**
 * Processor for audio track split jobs
 */
class AudioJobProcessor extends AbstractJobProcessor
{
    protected int $timeout = 7200; // 2 hours
    protected int $staleThreshold = 30;
    
    public function __construct(
        private AudioTrackSplitService $audioTrackSplitService
    ) {
    }
    
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void
    {
        $startTime = time();
        
        try {
            $this->logJobStart($videoJob, 'AudioTrackSplit');
            $this->markStaleJobsAsErrors();
            
            // Check if already being processed
            if ($this->isJobLocked($videoJob->id) && $videoJob->status == Videojob::STATUS_PROCESSING) {
                $videoJob->status = Videojob::STATUS_APPROVED;
                $videoJob->save();
                $this->logJobStart($videoJob, 'AudioTrackSplit - Already Processing');
                return;
            }
            
            // Lock the job
            $this->lockJob($videoJob->id);
            
            // Initialize job
            $this->initializeJob($videoJob);
            $videoJob->job_time = time() - $startTime;
            $videoJob->save();
            
            // Process the audio
            $this->audioTrackSplitService->startProcess($videoJob);
            
            // Release lock
            $this->unlockJob($videoJob->id);
            
            $duration = time() - $startTime;
            $this->logJobCompletion($videoJob, 'AudioTrackSplit', $duration);
            
        } catch (\Exception $e) {
            $this->unlockJob($videoJob->id);
            $this->logJobError($videoJob, 'AudioTrackSplit', $e);
            
            $videoJob->job_time = time() - $startTime;
            $videoJob->status = Videojob::STATUS_ERROR;
            $videoJob->save();
            
            throw $e;
        }
    }
    
    public function supports(Videojob $videoJob): bool
    {
        $params = $videoJob->generation_parameters ? json_decode($videoJob->generation_parameters, true) : [];
        return isset($params['jobType']) && $params['jobType'] === 'audio-track-split';
    }
}
