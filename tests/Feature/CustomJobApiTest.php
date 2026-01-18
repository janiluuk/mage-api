<?php

namespace Tests\Feature;

use App\Jobs\ProcessBeatMatchMusicVideoJob;
use App\Models\User;
use App\Models\UserFile;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomJobApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Queue::fake();
        Storage::fake('local');
    }

    public function test_custom_job_process_with_files_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/custom-jobs/process');
        $response->assertStatus(401);
    }

    public function test_custom_job_process_with_files_creates_job(): void
    {
        $user = User::factory()->create();

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
            UploadedFile::fake()->create('test-video-2.mp4', 500),
            UploadedFile::fake()->create('test-video-3.mp4', 500),
            UploadedFile::fake()->create('test-video-4.mp4', 500),
        ];

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'files',
            'options' => [
                'cut_intensity' => 2,
                'direction' => 'random',
                'speed_factor' => 1.0,
            ],
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'job_id',
                'status',
            ]);

        // Verify job was created
        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $this->assertNotNull($videoJob);
        $this->assertEquals('beat-match', $videoJob->generator);
        $this->assertEquals(Videojob::STATUS_APPROVED, $videoJob->status);
        $this->assertEquals($user->id, $videoJob->user_id);
        
        // Verify generation parameters
        $params = $videoJob->generation_parameters;
        $this->assertEquals('files', $params['input_type']);
        $this->assertEquals('beat-match', $params['job_type']);
        $this->assertEquals(2, $params['options']['cut_intensity']);
        $this->assertEquals('random', $params['options']['direction']);
        
        // Verify job was queued
        Queue::assertPushed(ProcessBeatMatchMusicVideoJob::class, function ($job) use ($videoJob) {
            return $job->videoJob->id === $videoJob->id;
        });
    }

    public function test_custom_job_process_with_project_id_creates_job(): void
    {
        $user = User::factory()->create();
        Storage::fake('local');

        // Create a project with video files
        $projectId = 123;
        
        // Create video files in project
        $videoFile1 = UserFile::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'mime_type' => 'video/mp4',
            'path' => 'projects/123/video1.mp4',
            'disk' => 'local',
        ]);
        
        $videoFile2 = UserFile::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'mime_type' => 'video/mp4',
            'path' => 'projects/123/video2.mp4',
            'disk' => 'local',
        ]);

        $videoFile3 = UserFile::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'mime_type' => 'video/mp4',
            'path' => 'projects/123/video3.mp4',
            'disk' => 'local',
        ]);

        $videoFile4 = UserFile::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'mime_type' => 'video/mp4',
            'path' => 'projects/123/video4.mp4',
            'disk' => 'local',
        ]);

        // Create audio file in project
        $audioFile = UserFile::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'mime_type' => 'audio/wav',
            'path' => 'projects/123/audio.wav',
            'disk' => 'local',
        ]);

        // Create actual files on disk
        Storage::disk('local')->put($videoFile1->path, 'fake video content 1');
        Storage::disk('local')->put($videoFile2->path, 'fake video content 2');
        Storage::disk('local')->put($videoFile3->path, 'fake video content 3');
        Storage::disk('local')->put($videoFile4->path, 'fake video content 4');
        Storage::disk('local')->put($audioFile->path, 'fake audio content');

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'project',
            'project_id' => $projectId,
            'options' => [
                'cut_intensity' => 1,
                'direction' => 'forward',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Verify job was created
        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $this->assertNotNull($videoJob);
        $this->assertEquals('beat-match', $videoJob->generator);
        $this->assertEquals($user->id, $videoJob->user_id);
        
        // Verify generation parameters
        $params = $videoJob->generation_parameters;
        $this->assertEquals('project', $params['input_type']);
        $this->assertEquals($projectId, $params['project_id']);
        $this->assertCount(4, $params['input_files']['video_files']); // Should have 4 video files
        $this->assertNotNull($params['input_files']['audio_file']);
    }

    public function test_custom_job_process_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_type', 'input_type']);
    }

    public function test_custom_job_process_validates_file_types(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'files',
            'options' => [],
            'audio_file' => UploadedFile::fake()->create('test.txt', 100), // Invalid type
            'video_files' => [UploadedFile::fake()->create('test.pdf', 100)], // Invalid type
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio_file', 'video_files.0']);
    }

    public function test_custom_job_process_validates_project_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'project',
            'project_id' => 999, // Non-existent project
            'options' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_custom_job_status_endpoint_returns_job_status(): void
    {
        $user = User::factory()->create();

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'beat-match',
                'status' => Videojob::STATUS_PROCESSING,
                'progress' => 50,
                'job_time' => 30,
            ])
            ->create();

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/custom-jobs/{$videoJob->id}/status");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'status',
                'progress',
                'estimated_time_left',
                'job_time',
                'url',
                'error',
            ])
            ->assertJson([
                'id' => $videoJob->id,
                'status' => Videojob::STATUS_PROCESSING,
                'progress' => 50,
                'job_time' => 30,
            ]);
    }

    public function test_custom_job_status_requires_authorization(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $videoJob = Videojob::factory()
            ->for($user1, 'user')
            ->create();

        $response = $this->actingAs($user2, 'api')->getJson("/api/v1/custom-jobs/{$videoJob->id}/status");

        $response->assertStatus(403);
    }

    public function test_custom_job_output_is_visible_in_user_job_list(): void
    {
        $user = User::factory()->create();

        // Create a custom job
        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
        ];

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'files',
            'options' => [],
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
        ]);

        $response->assertOk();
        $jobId = $response->json('job_id');

        // Verify job is visible in user's video jobs
        $userJobs = Videojob::where('user_id', $user->id)->get();
        $this->assertTrue($userJobs->contains('id', $jobId));

        // Verify job can be retrieved via status endpoint
        $statusResponse = $this->actingAs($user, 'api')->getJson("/api/v1/custom-jobs/{$jobId}/status");
        $statusResponse->assertOk()
            ->assertJson([
                'id' => $jobId,
                'status' => Videojob::STATUS_APPROVED,
            ]);
    }
}

