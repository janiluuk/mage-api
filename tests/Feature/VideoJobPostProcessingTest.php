<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Videojob;
use App\Services\VideoJobs\VideoPostProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class VideoJobPostProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Videojob $videoJob;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        
        $this->user = User::factory()->create();
        
        $this->videoJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'status' => Videojob::STATUS_FINISHED,
            'url' => 'http://example.com/video.mp4',
        ]);
    }

    public function test_can_get_available_effects()
    {
        $postProcessor = new VideoPostProcessor();
        
        $effects = $postProcessor->getAvailableEffects();
        
        $this->assertIsArray($effects);
        $this->assertArrayHasKey(VideoPostProcessor::EFFECT_FADE_IN, $effects);
        $this->assertArrayHasKey(VideoPostProcessor::EFFECT_BRIGHTNESS, $effects);
        $this->assertArrayHasKey(VideoPostProcessor::EFFECT_SHARPEN, $effects);
    }

    public function test_get_available_effects_api_endpoint()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson('/api/v1/video-jobs/post-process/effects');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'effects' => [
                'fade_in' => ['name', 'description', 'parameters'],
                'brightness' => ['name', 'description', 'parameters'],
            ]
        ]);
    }

    public function test_post_process_api_endpoint_validates_effects()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->videoJob->id}/post-process", [
            'effects' => [
                ['name' => 'invalid_effect', 'params' => []],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_post_process_requires_finished_status()
    {
        $pendingJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$pendingJob->id}/post-process", [
            'effects' => [
                ['name' => VideoPostProcessor::EFFECT_FADE_IN, 'params' => ['duration' => 1]],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Video job must be finished before post-processing'
        ]);
    }

    public function test_unauthorized_user_cannot_post_process()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->videoJob->id}/post-process", [
            'effects' => [
                ['name' => VideoPostProcessor::EFFECT_FADE_IN, 'params' => ['duration' => 1]],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_build_effect_filter_for_fade_in()
    {
        $postProcessor = new VideoPostProcessor();
        $reflection = new \ReflectionClass($postProcessor);
        $method = $reflection->getMethod('buildEffectFilter');
        $method->setAccessible(true);

        $filter = $method->invoke($postProcessor, VideoPostProcessor::EFFECT_FADE_IN, ['duration' => 2]);
        
        $this->assertEquals('fade=t=in:st=0:d=2', $filter);
    }

    public function test_build_effect_filter_for_brightness()
    {
        $postProcessor = new VideoPostProcessor();
        $reflection = new \ReflectionClass($postProcessor);
        $method = $reflection->getMethod('buildEffectFilter');
        $method->setAccessible(true);

        $filter = $method->invoke($postProcessor, VideoPostProcessor::EFFECT_BRIGHTNESS, ['value' => 0.2]);
        
        $this->assertEquals('eq=brightness=0.2', $filter);
    }

    public function test_build_effect_filter_for_scale()
    {
        $postProcessor = new VideoPostProcessor();
        $reflection = new \ReflectionClass($postProcessor);
        $method = $reflection->getMethod('buildEffectFilter');
        $method->setAccessible(true);

        $filter = $method->invoke($postProcessor, VideoPostProcessor::EFFECT_SCALE, ['width' => 1920, 'height' => 1080]);
        
        $this->assertEquals('scale=1920:1080', $filter);
    }
}
