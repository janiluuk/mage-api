<?php

namespace Tests\Unit;

use App\Jobs\JobType;
use App\Models\Videojob;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JobTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_determines_vid2vid_from_video_job(): void
    {
        $videoJob = Videojob::factory()->make([
            'generator' => 'stable_diffusion'
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::VID2VID, $jobType);
    }

    public function test_determines_deforum_from_video_job(): void
    {
        $videoJob = Videojob::factory()->make([
            'generator' => 'deforum'
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::DEFORUM, $jobType);
    }

    public function test_determines_audio_track_split_from_generation_parameters(): void
    {
        $videoJob = Videojob::factory()->make([
            'generation_parameters' => json_encode(['jobType' => 'audio-track-split'])
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::AUDIO_TRACK_SPLIT, $jobType);
    }

    public function test_determines_beat_match_from_generation_parameters(): void
    {
        $videoJob = Videojob::factory()->make([
            'generation_parameters' => json_encode(['jobType' => 'beat-match'])
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::BEAT_MATCH, $jobType);
    }

    public function test_determines_comfyui_workflow_from_generation_parameters(): void
    {
        $videoJob = Videojob::factory()->make([
            'generation_parameters' => json_encode(['jobType' => 'comfyui-workflow'])
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::COMFYUI_WORKFLOW, $jobType);
    }

    public function test_defaults_to_vid2vid_for_backwards_compatibility(): void
    {
        $videoJob = Videojob::factory()->make([
            'generator' => null,
            'generation_parameters' => null
        ]);

        $jobType = JobType::fromVideoJob($videoJob);

        $this->assertSame(JobType::VID2VID, $jobType);
    }
}
