<?php

namespace App\Services\JobProcessors;

use App\Models\Videojob;

/**
 * Interface for job processors that handle different execution strategies
 */
interface JobProcessorInterface
{
    /**
     * Process the video job
     *
     * @param Videojob $videoJob The job to process
     * @param int $previewFrames Number of preview frames (0 for full processing)
     * @param int|null $extendFromJobId ID of job to extend from (if any)
     * @return void
     * @throws \Exception on processing failure
     */
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void;
    
    /**
     * Get the maximum execution timeout for this processor in seconds
     *
     * @return int
     */
    public function getTimeout(): int;
    
    /**
     * Get the stale job threshold in minutes
     *
     * @return int
     */
    public function getStaleThreshold(): int;
    
    /**
     * Check if this processor supports the given video job
     *
     * @param Videojob $videoJob
     * @return bool
     */
    public function supports(Videojob $videoJob): bool;
}
