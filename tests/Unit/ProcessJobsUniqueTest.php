<?php

namespace Tests\Unit;

use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Videojob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

class ProcessJobsUniqueTest extends TestCase
{
    // No database needed - these tests only check job properties

    public function test_video_job_is_unique_per_video_and_preview(): void
    {
        // Create a mock Videojob without using factory to avoid database
        $videoJob = new Videojob();
        $videoJob->id = 101;

        $job = new ProcessVideoJob($videoJob, 0);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        // uniqueId should format as: videoJobId-previewFrames-extendFromJobId (if extendFromJobId is set)
        // For ProcessVideoJob, extendFromJobId defaults to null, so format is: videoJobId-previewFrames
        $this->assertSame('101-0', $job->uniqueId());
        $this->assertSame(3600, $job->uniqueFor);
    }

    public function test_deforum_job_is_unique_per_video_and_preview(): void
    {
        // Create a mock Videojob without using factory to avoid database
        $videoJob = new Videojob();
        $videoJob->id = 202;

        $job = new ProcessDeforumJob($videoJob, 5);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('202-5-base', $job->uniqueId());
        $this->assertSame(3600, $job->uniqueFor);
    }

    public function test_deforum_extension_job_is_unique_per_video_preview_and_source(): void
    {
        // Create a mock Videojob without using factory to avoid database
        $videoJob = new Videojob();
        $videoJob->id = 303;

        $job = new ProcessDeforumJob($videoJob, 0, extendFromJobId: 999);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('303-0-999', $job->uniqueId());
    }
}
