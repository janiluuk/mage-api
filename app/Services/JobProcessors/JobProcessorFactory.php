<?php

namespace App\Services\JobProcessors;

use App\Jobs\JobType;
use App\Models\Videojob;

/**
 * Factory for creating the appropriate job processor
 */
class JobProcessorFactory
{
    /**
     * Get the appropriate processor for a video job
     *
     * @param Videojob $videoJob
     * @return JobProcessorInterface
     * @throws \Exception if no suitable processor found
     */
    public function getProcessor(Videojob $videoJob): JobProcessorInterface
    {
        $jobType = JobType::fromVideoJob($videoJob);
        $processorClass = $jobType->getProcessorClass();
        
        $processor = app($processorClass);
        
        if (!$processor instanceof JobProcessorInterface) {
            throw new \Exception("Invalid processor class: {$processorClass}");
        }
        
        if (!$processor->supports($videoJob)) {
            throw new \Exception("Processor {$processorClass} does not support this job");
        }
        
        return $processor;
    }
    
    /**
     * Get processor by job type
     *
     * @param JobType $jobType
     * @return JobProcessorInterface
     */
    public function getProcessorByType(JobType $jobType): JobProcessorInterface
    {
        $processorClass = $jobType->getProcessorClass();
        return app($processorClass);
    }
}
