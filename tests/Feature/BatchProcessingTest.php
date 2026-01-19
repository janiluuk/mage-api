<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_list_batches_requires_authentication(): void
    {
        $response = $this->getJson('/api/batches');

        $response->assertStatus(401);
    }

    public function test_list_batches_returns_user_batches(): void
    {
        Batch::factory()->count(3)->for($this->user, 'user')->create();
        Batch::factory()->count(2)->create(); // Other user's batches

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/batches');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_batches_filters_by_status(): void
    {
        Batch::factory()->for($this->user, 'user')->create(['status' => 'pending']);
        Batch::factory()->for($this->user, 'user')->create(['status' => 'processing']);
        Batch::factory()->for($this->user, 'user')->create(['status' => 'completed']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/batches?status=processing');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'processing');
    }

    public function test_create_batch_requires_authentication(): void
    {
        $response = $this->postJson('/api/batches', [
            'name' => 'Test Batch',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_batch_validates_name(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/batches', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_batch_creates_batch(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/batches', [
                'name' => 'Test Batch',
                'description' => 'Test description',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'batch' => ['id', 'name', 'status']
            ]);

        $this->assertDatabaseHas('batches', [
            'user_id' => $this->user->id,
            'name' => 'Test Batch',
            'status' => Batch::STATUS_PENDING,
        ]);
    }

    public function test_show_batch_requires_authentication(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->getJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(401);
    }

    public function test_show_batch_checks_authorization(): void
    {
        $otherUser = User::factory()->create();
        $batch = Batch::factory()->for($otherUser, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(404);
    }

    public function test_show_batch_returns_batch_with_jobs(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();
        $videoJobs = Videojob::factory()->count(2)->for($this->user, 'user')->create();
        $batch->videoJobs()->attach($videoJobs->pluck('id'));

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'video_jobs' => [
                    '*' => ['id', 'filename', 'status']
                ]
            ]);
    }

    public function test_update_batch_requires_authentication(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->putJson("/api/v1/batches/{$batch->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_batch_prevents_update_while_processing(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create([
            'status' => Batch::STATUS_PROCESSING,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/batches/{$batch->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot update batch while processing'
            ]);
    }

    public function test_update_batch_updates_batch(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create([
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/batches/{$batch->id}", [
                'name' => 'Updated Name',
                'description' => 'New description',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'name' => 'Updated Name',
            'description' => 'New description',
        ]);
    }

    public function test_delete_batch_requires_authentication(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->deleteJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(401);
    }

    public function test_delete_batch_prevents_deletion_while_processing(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create([
            'status' => Batch::STATUS_PROCESSING,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete batch while processing'
            ]);
    }

    public function test_delete_batch_deletes_batch(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('batches', [
            'id' => $batch->id,
        ]);
    }

    public function test_add_jobs_to_batch_requires_authentication(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->postJson("/api/v1/batches/{$batch->id}/jobs", [
            'video_job_ids' => [1],
        ]);

        $response->assertStatus(401);
    }

    public function test_add_jobs_to_batch_validates_job_ids(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/batches/{$batch->id}/jobs", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_job_ids']);
    }

    public function test_add_jobs_to_batch_checks_job_ownership(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();
        $otherUser = User::factory()->create();
        $otherJob = Videojob::factory()->for($otherUser, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/batches/{$batch->id}/jobs", [
                'video_job_ids' => [$otherJob->id],
            ]);

        $response->assertStatus(404);
    }

    public function test_add_jobs_to_batch_adds_jobs(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();
        $videoJobs = Videojob::factory()->count(2)->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/batches/{$batch->id}/jobs", [
                'video_job_ids' => $videoJobs->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200);

        foreach ($videoJobs as $job) {
            $this->assertDatabaseHas('batch_video_job', [
                'batch_id' => $batch->id,
                'video_job_id' => $job->id,
            ]);
        }
    }

    public function test_remove_jobs_from_batch_requires_authentication(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();

        $response = $this->deleteJson("/api/v1/batches/{$batch->id}/jobs", [
            'video_job_ids' => [1],
        ]);

        $response->assertStatus(401);
    }

    public function test_remove_jobs_from_batch_removes_jobs(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create();
        $videoJobs = Videojob::factory()->count(2)->for($this->user, 'user')->create();
        $batch->videoJobs()->attach($videoJobs->pluck('id'));

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/batches/{$batch->id}/jobs", [
                'video_job_ids' => [$videoJobs->first()->id],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('batch_video_job', [
            'batch_id' => $batch->id,
            'video_job_id' => $videoJobs->first()->id,
        ]);
    }

    public function test_batch_status_returns_progress(): void
    {
        $batch = Batch::factory()->for($this->user, 'user')->create([
            'status' => Batch::STATUS_PROCESSING,
        ]);
        
        $videoJobs = Videojob::factory()->count(3)->for($this->user, 'user')->create();
        $batch->videoJobs()->attach($videoJobs->pluck('id'), ['status' => 'pending']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/batches/{$batch->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'status',
                'progress',
                'total_jobs',
                'completed_jobs',
                'failed_jobs',
                'jobs' => [
                    '*' => ['id', 'status', 'progress']
                ]
            ]);
    }
}
