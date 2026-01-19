<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_story_generate_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/story/generate', [
            'config' => ['prompt' => 'test'],
        ]);

        $response->assertStatus(401);
    }

    public function test_story_generate_creates_batch_and_jobs(): void
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/story/generate', [
            'name' => 'Test Story',
            'config' => [
                'prompt' => 'A beautiful story',
                'frame_count' => 5,
            ],
            'frame_count' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'batch',
            'batchId',
        ]);

        $this->assertDatabaseHas('batches', [
            'user_id' => $this->user->id,
            'name' => 'Test Story',
            'status' => Batch::STATUS_PENDING,
        ]);

        $batch = Batch::where('user_id', $this->user->id)->first();
        $this->assertEquals(5, $batch->videoJobs()->count());
    }

    public function test_story_generate_validates_config(): void
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/story/generate', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['config']);
    }

    public function test_get_batch_status_returns_batch_info(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Batch',
        ]);

        $videoJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'generation_parameters' => ['frame_index' => 0],
        ]);

        $batch->videoJobs()->attach($videoJob->id, ['order' => 1, 'status' => 'pending']);

        $response = $this->actingAs($this->user, 'api')->getJson("/api/v1/story/batch/{$batch->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'status',
            'progress',
            'total_jobs',
            'completed_jobs',
            'jobs',
        ]);
    }

    public function test_get_batch_status_checks_ownership(): void
    {
        $otherUser = User::factory()->create();
        $batch = Batch::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'api')->getJson("/api/v1/story/batch/{$batch->id}");

        $response->assertStatus(404);
    }

    public function test_pause_batch_stops_processing(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'status' => Batch::STATUS_PROCESSING,
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/pause");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Batch paused successfully']);

        $batch->refresh();
        $this->assertEquals('paused', $batch->status);
    }

    public function test_pause_batch_requires_processing_status(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'status' => Batch::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/pause");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Batch is not processing']);
    }

    public function test_resume_batch_starts_processing(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'paused',
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/resume");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Batch resumed successfully']);

        $batch->refresh();
        $this->assertEquals(Batch::STATUS_PROCESSING, $batch->status);
    }

    public function test_resume_batch_requires_paused_status(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'status' => Batch::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/resume");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Batch is not paused']);
    }

    public function test_cancel_batch_cancels_all_jobs(): void
    {
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'status' => Batch::STATUS_PROCESSING,
        ]);

        $videoJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'status' => Videojob::STATUS_PROCESSING,
        ]);

        $batch->videoJobs()->attach($videoJob->id, ['order' => 1, 'status' => 'processing']);

        $response = $this->actingAs($this->user, 'api')->deleteJson("/api/v1/story/batch/{$batch->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Batch cancelled successfully']);

        $batch->refresh();
        $this->assertEquals(Batch::STATUS_CANCELLED, $batch->status);

        $videoJob->refresh();
        $this->assertEquals(Videojob::STATUS_CANCELLED, $videoJob->status);
    }

    public function test_persist_frame_saves_frame_data(): void
    {
        $batch = Batch::factory()->create(['user_id' => $this->user->id]);
        $videoJob = Videojob::factory()->create(['user_id' => $this->user->id]);

        $batch->videoJobs()->attach($videoJob->id, ['order' => 1, 'status' => 'pending']);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/frames", [
            'video_job_id' => $videoJob->id,
            'frame_data' => ['url' => 'test.jpg'],
            'metadata' => ['timestamp' => time()],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Frame persisted successfully']);

        $videoJob->refresh();
        $this->assertArrayHasKey('frame_data', $videoJob->generation_parameters);
        $this->assertArrayHasKey('metadata', $videoJob->generation_parameters);
    }

    public function test_persist_frame_checks_job_belongs_to_batch(): void
    {
        $batch = Batch::factory()->create(['user_id' => $this->user->id]);
        $videoJob = Videojob::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/v1/story/batch/{$batch->id}/frames", [
            'video_job_id' => $videoJob->id,
            'frame_data' => ['url' => 'test.jpg'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Video job does not belong to this batch']);
    }

    public function test_create_share_link_generates_token(): void
    {
        $batch = Batch::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/story/share', [
            'batch_id' => $batch->id,
            'public' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'share' => [
                'token',
                'url',
                'public',
            ],
        ]);

        $batch->refresh();
        $this->assertArrayHasKey('share', $batch->settings);
        $this->assertNotEmpty($batch->settings['share']['token']);
    }

    public function test_extend_generation_creates_new_batch(): void
    {
        $sourceBatch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'settings' => ['prompt' => 'Original story'],
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/story/generate', [
            'name' => 'Extended Story',
            'config' => ['prompt' => 'Extended story'],
            'extendFrom' => $sourceBatch->id,
            'frame_count' => 3,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['batch', 'batchId']);

        $newBatch = Batch::find($response->json('batchId'));
        $this->assertNotNull($newBatch);
        $this->assertEquals('Extended Story', $newBatch->name);
        $this->assertNotEquals($sourceBatch->id, $newBatch->id);
    }
}


