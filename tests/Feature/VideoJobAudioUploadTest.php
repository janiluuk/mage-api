<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Videojob;
use App\Services\VideoProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class VideoJobAudioUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
<<<<<<< HEAD
        
        // Mock VideoProcessingService to avoid FFMpeg dependency in tests
        $this->mock(VideoProcessingService::class, function ($mock) {
            $mock->shouldReceive('parseJob')
                ->andReturnUsing(function ($videoJob, $path) {
                    // Set dummy video properties
                    $videoJob->fps = 30;
                    $videoJob->codec = 'h264';
                    $videoJob->frame_count = 90;
                    $videoJob->size = 1000000;
                    $videoJob->width = 1920;
                    $videoJob->height = 1080;
                    $videoJob->bitrate = 4000000;
                    $videoJob->audio_codec = 'aac';
                    $videoJob->length = 3.0;
                    return $videoJob;
                });
        });
=======
        Storage::fake('public');

        $mockVideoService = Mockery::mock(VideoProcessingService::class);
        $mockVideoService->shouldIgnoreMissing();
        $mockVideoService->shouldReceive('parseJob')
            ->andReturnUsing(fn ($job) => $job);
        $this->app->instance(VideoProcessingService::class, $mockVideoService);
>>>>>>> 925d55f (chore: update .gitignore and enhance docker-compose configuration)
    }

    public function test_user_can_upload_audio_file_during_job_creation_vid2vid(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000); // 1MB
        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'soundtrack' => $audioFile,
                'type' => 'vid2vid',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'url',
            'status',
        ]);

        // Verify job was created
        $jobId = $response->json('id');
        $videoJob = Videojob::findOrFail($jobId);

        // Verify audio file was attached
        $this->assertNotNull($videoJob->soundtrack_path);
        $this->assertNotNull($videoJob->soundtrack_url);
        $this->assertNotNull($videoJob->soundtrack_mimetype);
        $this->assertEquals('audio/mpeg', $videoJob->soundtrack_mimetype);

        // Verify file was stored
        Storage::disk('public')->assertExists('soundtracks/' . basename($videoJob->soundtrack_path));
    }

    public function test_user_can_upload_audio_file_during_job_creation_deforum(): void
    {
        $user = User::factory()->create();
        
        $imageFile = UploadedFile::fake()->image('test-image.jpg', 800, 600);
        $audioFile = UploadedFile::fake()->create('test-audio.wav', 300, 'audio/wav');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $imageFile,
                'soundtrack' => $audioFile,
                'type' => 'deforum',
            ]);

        $response->assertOk();
        
        $jobId = $response->json('id');
        $videoJob = Videojob::findOrFail($jobId);

        // Verify audio file was attached
        $this->assertNotNull($videoJob->soundtrack_path);
        $this->assertNotNull($videoJob->soundtrack_url);
        $this->assertEquals('audio/wav', $videoJob->soundtrack_mimetype);
    }

    public function test_audio_file_upload_is_optional(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'type' => 'vid2vid',
            ]);

        $response->assertOk();
        
        $jobId = $response->json('id');
        $videoJob = Videojob::findOrFail($jobId);

        // Verify audio fields are null when not provided
        $this->assertNull($videoJob->soundtrack_path);
        $this->assertNull($videoJob->soundtrack_url);
        $this->assertNull($videoJob->soundtrack_mimetype);
    }

    public function test_audio_file_validation_rejects_invalid_mime_types(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);
        $invalidAudioFile = UploadedFile::fake()->create('test-audio.txt', 100, 'text/plain');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'soundtrack' => $invalidAudioFile,
                'type' => 'vid2vid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_audio_file_validation_rejects_files_too_large(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);
        // Create a file larger than 51200 KB (50 MB)
        $largeAudioFile = UploadedFile::fake()->create('test-audio.mp3', 60000, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'soundtrack' => $largeAudioFile,
                'type' => 'vid2vid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_audio_file_accepts_valid_formats(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);

        $formats = [
            ['mp3', 'audio/mpeg'],
            ['aac', 'audio/aac'],
            ['wav', 'audio/wav'],
        ];

        foreach ($formats as [$extension, $mimeType]) {
            $audioFile = UploadedFile::fake()->create("test-audio.{$extension}", 500, $mimeType);

            $response = $this->actingAs($user, 'api')
                ->postJson('/api/upload', [
                    'attachment' => $videoFile,
                    'soundtrack' => $audioFile,
                    'type' => 'vid2vid',
                ]);

            $response->assertOk();
            
            $jobId = $response->json('id');
            $videoJob = Videojob::findOrFail($jobId);
            $this->assertEquals($mimeType, $videoJob->soundtrack_mimetype);
            
            // Clean up for next iteration
            $videoJob->delete();
        }
    }

    public function test_audio_file_path_is_stored_correctly(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);
        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'soundtrack' => $audioFile,
                'type' => 'vid2vid',
            ]);

        $response->assertOk();
        
        $jobId = $response->json('id');
        $videoJob = Videojob::findOrFail($jobId);

        // Verify path structure
        $this->assertStringContainsString('soundtracks', $videoJob->soundtrack_path);
        $this->assertStringContainsString('soundtracks', $videoJob->soundtrack_url);
        
        // Verify file exists at the path
        $this->assertFileExists($videoJob->soundtrack_path);
    }

    public function test_audio_file_is_stored_in_soundtracks_directory(): void
    {
        $user = User::factory()->create();
        
        $videoFile = UploadedFile::fake()->create('test-video.mp4', 1000);
        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/upload', [
                'attachment' => $videoFile,
                'soundtrack' => $audioFile,
                'type' => 'vid2vid',
            ]);

        $response->assertOk();
        
        $jobId = $response->json('id');
        $videoJob = Videojob::findOrFail($jobId);

        // Verify the file path contains 'soundtracks'
        $this->assertStringContainsString('soundtracks', $videoJob->soundtrack_path);
        Storage::disk('public')->assertExists('soundtracks/' . basename($videoJob->soundtrack_path));
    }

    public function test_user_can_attach_audio_to_existing_pending_job(): void
    {
        $user = User::factory()->create();
        
        // Create a job without audio
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
            'soundtrack_path' => null,
            'soundtrack_url' => null,
            'soundtrack_mimetype' => null,
        ]);

        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", [
                'soundtrack' => $audioFile,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'soundtrack_url',
            'soundtrack_mimetype',
            'message',
        ]);

        $videoJob->refresh();
        $this->assertNotNull($videoJob->soundtrack_path);
        $this->assertNotNull($videoJob->soundtrack_url);
        $this->assertEquals('audio/mpeg', $videoJob->soundtrack_mimetype);
    }

    public function test_user_cannot_attach_audio_to_processing_job(): void
    {
        $user = User::factory()->create();
        
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PROCESSING,
        ]);

        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", [
                'soundtrack' => $audioFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'Audio can only be attached to pending jobs',
        ]);
    }

    public function test_user_cannot_attach_audio_to_another_users_job(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $videoJob = Videojob::factory()->for($otherUser, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $audioFile = UploadedFile::fake()->create('test-audio.mp3', 500, 'audio/mpeg');

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", [
                'soundtrack' => $audioFile,
            ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => 'Unauthorized. Not your video.',
        ]);
    }

    public function test_attach_audio_validates_file_required(): void
    {
        $user = User::factory()->create();
        
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_attach_audio_validates_file_format(): void
    {
        $user = User::factory()->create();
        
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
        ]);

        $invalidFile = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", [
                'soundtrack' => $invalidFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['soundtrack']);
    }

    public function test_attach_audio_can_replace_existing_audio(): void
    {
        $user = User::factory()->create();
        
        // Create job with existing audio
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
            'soundtrack_path' => '/existing/path/audio.mp3',
            'soundtrack_url' => '/storage/soundtracks/audio.mp3',
            'soundtrack_mimetype' => 'audio/mpeg',
        ]);

        $newAudioFile = UploadedFile::fake()->create('new-audio.wav', 300, 'audio/wav');

        $response = $this->actingAs($user, 'api')
            ->patchJson("/api/video-jobs/{$videoJob->id}/audio", [
                'soundtrack' => $newAudioFile,
            ]);

        $response->assertOk();
        
        $videoJob->refresh();
        $this->assertNotNull($videoJob->soundtrack_path);
        $this->assertEquals('audio/wav', $videoJob->soundtrack_mimetype);
    }
}

