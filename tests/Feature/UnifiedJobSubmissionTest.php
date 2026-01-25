<?php

namespace Tests\Feature;

use App\Jobs\ProcessUnifiedJob;
use App\Models\Videojob;
use App\Models\User;
use App\Services\VideoJobs\VideoJobSubmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnifiedJobSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_submit_dispatches_unified_job(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->create([
            'user_id' => $user->id,
            'generator' => 'stable_diffusion',
            'frame_count' => 10
        ]);

        $submitter = app(VideoJobSubmitter::class);
        $result = $submitter->submit($videoJob);

        $this->assertSame($videoJob->id, $result->id);
        Queue::assertPushed(ProcessUnifiedJob::class, function ($job) use ($videoJob) {
            return $job->videoJob->id === $videoJob->id;
        });
    }

    public function test_submit_uses_correct_queue_for_single_frame(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->create([
            'user_id' => $user->id,
            'frame_count' => 1
        ]);

        $submitter = app(VideoJobSubmitter::class);
        $submitter->submit($videoJob);

        Queue::assertPushedOn('high', ProcessUnifiedJob::class);
    }

    public function test_submit_uses_correct_queue_for_multiple_frames(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->create([
            'user_id' => $user->id,
            'frame_count' => 10
        ]);

        $submitter = app(VideoJobSubmitter::class);
        $submitter->submit($videoJob);

        Queue::assertPushedOn('medium', ProcessUnifiedJob::class);
    }

    public function test_submit_with_preview_frames(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->create([
            'user_id' => $user->id,
            'frame_count' => 10
        ]);

        $submitter = app(VideoJobSubmitter::class);
        $submitter->submit($videoJob, previewFrames: 5);

        Queue::assertPushed(ProcessUnifiedJob::class, function ($job) {
            return $job->previewFrames === 5;
        });
    }

    public function test_submit_with_extend_from_job_id(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->create([
            'user_id' => $user->id,
        ]);

        $submitter = app(VideoJobSubmitter::class);
        $submitter->submit($videoJob, extendFromJobId: 123);

        Queue::assertPushed(ProcessUnifiedJob::class, function ($job) {
            return $job->extendFromJobId === 123;
        });
    }
}
