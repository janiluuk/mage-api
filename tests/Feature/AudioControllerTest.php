<?php

namespace Tests\Feature;

use App\Services\Audio\AudioGenerationService;
use App\Services\Audio\AudioQueueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AudioControllerTest extends TestCase
{
    use RefreshDatabase;

    private AudioQueueManager $queueManager;
    private AudioGenerationService $audioGenerationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->queueManager = new AudioQueueManager();
        $this->app->instance(AudioQueueManager::class, $this->queueManager);
        
        Config::set('services.comfy.host', '127.0.0.1:8188');
        Config::set('services.stable.url', 'http://test-stable.local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    public function test_config_endpoint_returns_stable_url(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJsonStructure([
            'stableUrl',
        ]);
        $response->assertJson([
            'stableUrl' => 'http://test-stable.local',
        ]);
    }

    public function test_status_endpoint_returns_queue_status(): void
    {
        $response = $this->getJson('/api/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'processing',
            'queued',
            'recent',
        ]);
        $response->assertJson([
            'processing' => null,
            'queued' => 0,
            'recent' => [],
        ]);
    }

    public function test_status_endpoint_shows_queued_jobs(): void
    {
        // Add a job to the queue
        $job = $this->queueManager->enqueue(['text' => 'test audio']);
        $this->queueManager->markProcessing($job['id']);

        $response = $this->getJson('/api/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'processing' => [
                'id',
                'status',
                'createdAt',
                'metadata',
                'startedAt',
            ],
            'queued',
            'recent',
        ]);
        $this->assertNotNull($response->json('processing'));
        $this->assertEquals('processing', $response->json('processing.status'));
    }

    public function test_queue_endpoint_returns_queue_details(): void
    {
        $response = $this->getJson('/api/audio-queue');

        $response->assertOk();
        $response->assertJsonStructure([
            'queued',
            'processing',
            'history',
        ]);
        $response->assertJson([
            'queued' => [],
            'processing' => [],
            'history' => [],
        ]);
    }

    public function test_queue_endpoint_includes_history(): void
    {
        // Create and complete a job
        $job = $this->queueManager->enqueue(['text' => 'test']);
        $this->queueManager->markProcessing($job['id']);
        $this->queueManager->markComplete($job['id']);

        $response = $this->getJson('/api/audio-queue');

        $response->assertOk();
        $this->assertCount(1, $response->json('history'));
        $this->assertEquals('completed', $response->json('history.0.status'));
    }

    public function test_stream_endpoint_validates_text_parameter(): void
    {
        // Mock the AudioGenerationService even though we won't use it
        // This prevents 500 errors from dependency injection failures
        $mockService = Mockery::mock(AudioGenerationService::class);
        $mockService->shouldIgnoreMissing();
        $this->app->instance(AudioGenerationService::class, $mockService);
        
        $response = $this->get('/api/stream');

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'error' => 'Text parameter must be a string',
        ]);
    }

    public function test_stream_endpoint_validates_text_length(): void
    {
        // Mock the AudioGenerationService even though we won't use it
        // This prevents 500 errors from dependency injection failures
        $mockService = Mockery::mock(AudioGenerationService::class);
        $mockService->shouldIgnoreMissing();
        $this->app->instance(AudioGenerationService::class, $mockService);
        
        $longText = str_repeat('a', 1001);
        
        $response = $this->get('/api/stream?text=' . urlencode($longText));

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'error' => 'Text parameter exceeds maximum length of 1000 characters',
        ]);
    }

    public function test_stream_endpoint_accepts_valid_text(): void
    {
        // Mock the AudioGenerationService to avoid actual ComfyUI calls
        $mockService = Mockery::mock(AudioGenerationService::class);
        $mockService->shouldReceive('generateAudio')
            ->once()
            ->with('test audio', Mockery::any())
            ->andReturn('fake audio data');
        
        $this->app->instance(AudioGenerationService::class, $mockService);

        $response = $this->get('/api/stream?text=' . urlencode('test audio'));

        // Should return audio content type and job ID header
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/aac');
        $response->assertHeader('X-Job-Id');
        $this->assertNotEmpty($response->headers->get('X-Job-Id'));
        $this->assertEquals('fake audio data', $response->getContent());
    }

    public function test_stream_endpoint_handles_generation_errors(): void
    {
        // Mock service to throw an error
        $mockService = Mockery::mock(AudioGenerationService::class);
        $mockService->shouldReceive('generateAudio')
            ->once()
            ->andThrow(new \Exception('ComfyUI connection failed'));
        
        $this->app->instance(AudioGenerationService::class, $mockService);

        $response = $this->get('/api/stream?text=test');

        $response->assertStatus(500);
        $response->assertJsonStructure([
            'error',
            'details',
        ]);
    }

    public function test_legacy_audio_routes_work(): void
    {
        // Test backward compatibility routes
        $response = $this->getJson('/api/status');
        $response->assertOk();

        $response = $this->getJson('/api/config');
        $response->assertOk();

        $response = $this->getJson('/api/audio-queue');
        $response->assertOk();
    }
}

