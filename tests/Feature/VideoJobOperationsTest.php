<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoJobOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Storage::fake('public');
    }

    public function test_trim_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/video-jobs/trim', [
            'video_job_id' => 1,
            'start_seconds' => 0,
            'end_seconds' => 10,
        ]);

        $response->assertStatus(401);
    }

    public function test_trim_requires_valid_video_job_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/trim', [
                'video_job_id' => 999999,
                'start_seconds' => 0,
                'end_seconds' => 10,
            ]);

        $response->assertStatus(404);
    }

    public function test_trim_requires_start_and_end_seconds(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/trim', [
                'video_job_id' => $videoJob->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_seconds', 'end_seconds']);
    }

    public function test_trim_validates_end_greater_than_start(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/trim', [
                'video_job_id' => $videoJob->id,
                'start_seconds' => 10,
                'end_seconds' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_seconds']);
    }

    public function test_trim_checks_authorization(): void
    {
        $otherUser = User::factory()->create();
        $videoJob = Videojob::factory()->for($otherUser, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/trim', [
                'video_job_id' => $videoJob->id,
                'start_seconds' => 0,
                'end_seconds' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_extend_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/video-jobs/extend', [
            'video_job_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_extend_requires_valid_video_job_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/extend', [
                'video_job_id' => 999999,
            ]);

        $response->assertStatus(404);
    }

    public function test_extend_only_works_with_deforum_jobs(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
            'generator' => 'vid2vid', // Not deforum
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/extend', [
                'video_job_id' => $videoJob->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Only deforum jobs can be extended'
            ]);
    }

    public function test_extend_requires_completed_job(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_PENDING, // Not completed
            'generator' => 'deforum',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/extend', [
                'video_job_id' => $videoJob->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Base video job must be completed before extension'
            ]);
    }

    public function test_extend_creates_new_job(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
            'generator' => 'deforum',
            'model_id' => 1,
            'prompt' => 'Test prompt',
            'width' => 512,
            'height' => 512,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/extend', [
                'video_job_id' => $videoJob->id,
                'length' => 5,
                'prompt' => 'Extended prompt',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'video_job_id',
                'base_job_id',
                'status',
                'extended_from',
            ]);

        $this->assertDatabaseHas('video_jobs', [
            'user_id' => $this->user->id,
            'generator' => 'deforum',
            'status' => Videojob::STATUS_PENDING,
        ]);
    }

    public function test_extend_checks_authorization(): void
    {
        $otherUser = User::factory()->create();
        $videoJob = Videojob::factory()->for($otherUser, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
            'generator' => 'deforum',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/extend', [
                'video_job_id' => $videoJob->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_add_soundtrack_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/video-jobs/add-soundtrack', [
            'video_job_id' => 1,
            'soundtrack' => UploadedFile::fake()->create('audio.mp3', 1000),
        ]);

        $response->assertStatus(401);
    }

    public function test_add_soundtrack_validates_file_type(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/add-soundtrack', [
                'video_job_id' => $videoJob->id,
                'soundtrack' => UploadedFile::fake()->create('document.pdf', 1000),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_add_soundtrack_validates_file_size(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        // Create file larger than 50MB
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/add-soundtrack', [
                'video_job_id' => $videoJob->id,
                'soundtrack' => UploadedFile::fake()->create('audio.mp3', 60000),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_add_soundtrack_checks_authorization(): void
    {
        $otherUser = User::factory()->create();
        $videoJob = Videojob::factory()->for($otherUser, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/add-soundtrack', [
                'video_job_id' => $videoJob->id,
                'soundtrack' => UploadedFile::fake()->create('audio.mp3', 1000),
            ]);

        $response->assertStatus(403);
    }

    public function test_add_soundtrack_validates_time_range(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/video-jobs/add-soundtrack', [
                'video_job_id' => $videoJob->id,
                'soundtrack' => UploadedFile::fake()->create('audio.mp3', 1000),
                'start_seconds' => 10,
                'end_seconds' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_seconds']);
    }
}
