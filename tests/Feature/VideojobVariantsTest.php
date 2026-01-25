<?php

namespace Tests\Feature;

use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use App\Models\User;
use App\Models\Videojob;
use App\Services\VideoProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class VideojobVariantsTest extends TestCase
{
    use RefreshDatabase;

    private VideoProcessingService $mockVideoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->mockVideoService = Mockery::mock(VideoProcessingService::class);
        $this->mockVideoService->shouldIgnoreMissing();
        $this->app->instance(VideoProcessingService::class, $this->mockVideoService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_vid2vid_with_single_variant_returns_single_job(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'beautiful landscape',
            'denoising' => 0.5,
            'variants' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'id', 'status', 'seed', 'job_time', 'progress', 
            'estimated_time_left', 'width', 'height', 'length', 'fps'
        ]);

        // Should not have 'variants' key for single variant
        $this->assertArrayNotHasKey('variants', $response->json());
        
        Queue::assertPushed(ProcessVideoJob::class, 1);
    }

    public function test_generate_vid2vid_with_multiple_variants_creates_multiple_jobs(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'api');

        $variantCount = 3;
        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'beautiful landscape',
            'denoising' => 0.5,
            'variants' => $variantCount,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'variants' => [
                '*' => ['id', 'status', 'seed', 'job_time', 'progress', 'estimated_time_left', 'width', 'height', 'length', 'fps']
            ],
            'count',
        ]);

        $responseData = $response->json();
        $this->assertSame($variantCount, $responseData['count']);
        $this->assertCount($variantCount, $responseData['variants']);

        // Verify all variants have unique seeds
        $seeds = array_column($responseData['variants'], 'seed');
        $this->assertCount($variantCount, array_unique($seeds), 'All variants should have unique seeds');

        // Verify all variants have the same prompt parameters
        foreach ($responseData['variants'] as $variant) {
            $this->assertSame(Videojob::STATUS_PROCESSING, $variant['status']);
        }

        // Verify that jobs were dispatched
        Queue::assertPushed(ProcessVideoJob::class, $variantCount);

        // Verify jobs exist in database
        $jobIds = array_column($responseData['variants'], 'id');
        $dbJobs = Videojob::whereIn('id', $jobIds)->count();
        $this->assertSame($variantCount, $dbJobs);
    }

    public function test_generate_vid2vid_validates_variants_min_value(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create();

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'test',
            'denoising' => 0.5,
            'variants' => 0, // Invalid: min is 1
        ]);

        $response->assertStatus(422);
    }

    public function test_generate_vid2vid_validates_variants_max_value(): void
    {
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create();

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'test',
            'denoising' => 0.5,
            'variants' => 11, // Invalid: max is 10
        ]);

        $response->assertStatus(422);
    }

    public function test_generate_deforum_with_multiple_variants_creates_multiple_jobs(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'api');

        $variantCount = 3;
        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'deforum',
            'modelId' => 1,
            'prompt' => 'flowing water',
            'preset' => 'default',
            'length' => 4,
            'variants' => $variantCount,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'variants' => [
                '*' => ['id', 'status', 'seed', 'job_time', 'progress', 'estimated_time_left', 'width', 'height', 'length', 'fps']
            ],
            'count',
        ]);

        $responseData = $response->json();
        $this->assertSame($variantCount, $responseData['count']);
        $this->assertCount($variantCount, $responseData['variants']);

        // Verify all variants have unique seeds
        $seeds = array_column($responseData['variants'], 'seed');
        $this->assertCount($variantCount, array_unique($seeds), 'All variants should have unique seeds');

        // Verify that jobs were dispatched
        Queue::assertPushed(ProcessDeforumJob::class, $variantCount);

        // Verify all jobs have deforum generator
        $jobIds = array_column($responseData['variants'], 'id');
        $dbJobs = Videojob::whereIn('id', $jobIds)->get();
        foreach ($dbJobs as $job) {
            $this->assertSame('deforum', $job->generator);
            $this->assertSame(Videojob::STATUS_PROCESSING, $job->status);
        }
    }

    public function test_generate_vid2vid_variants_replicates_original_job(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
            'width' => 512,
            'height' => 512,
            'fps' => 24,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'test prompt',
            'denoising' => 0.5,
            'variants' => 2,
        ]);

        $response->assertOk();
        $variants = $response->json('variants');

        // Get the jobs from database
        $jobIds = array_column($variants, 'id');
        $dbJobs = Videojob::whereIn('id', $jobIds)->get();

        // Verify all jobs have same parameters except seed
        foreach ($dbJobs as $job) {
            $this->assertSame(1, $job->model_id);
            $this->assertSame(7, $job->cfg_scale);
            $this->assertSame(0.5, $job->denoising);
            $this->assertSame('test prompt', $job->prompt);
            $this->assertSame($user->id, $job->user_id);
        }
    }

    public function test_generate_deforum_variants_replicates_original_job(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
            'width' => 512,
            'height' => 512,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'deforum',
            'modelId' => 2,
            'prompt' => 'abstract art',
            'preset' => 'cinematic',
            'length' => 5,
            'variants' => 2,
        ]);

        $response->assertOk();
        $variants = $response->json('variants');

        // Get the jobs from database
        $jobIds = array_column($variants, 'id');
        $dbJobs = Videojob::whereIn('id', $jobIds)->get();

        // Verify all jobs have same parameters except seed
        foreach ($dbJobs as $job) {
            $this->assertSame(2, $job->model_id);
            $this->assertSame('abstract art', $job->prompt);
            $this->assertSame(5, $job->length);
            $this->assertSame(24, $job->fps);
            $this->assertSame(120, $job->frame_count); // 5 * 24
            $this->assertSame('deforum', $job->generator);
            $this->assertSame($user->id, $job->user_id);
        }
    }

    public function test_variants_parameter_is_optional_defaults_to_one(): void
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/generate', [
            'videoId' => $videoJob->id,
            'type' => 'vid2vid',
            'modelId' => 1,
            'cfgScale' => 7,
            'prompt' => 'test',
            'denoising' => 0.5,
            // No 'variants' parameter specified
        ]);

        $response->assertOk();
        
        // Should return single job format, not variants array
        $this->assertArrayHasKey('id', $response->json());
        $this->assertArrayNotHasKey('variants', $response->json());
        
        Queue::assertPushed(ProcessVideoJob::class, 1);
    }
}
