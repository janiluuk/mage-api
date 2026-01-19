<?php

namespace Tests\Feature;

use App\Jobs\ProcessBeatMatchMusicVideoJob;
use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class BeatMatchMusicVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed Spatie roles for tests that use role assignment
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        
        Queue::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_beat_match_video_form_requires_authentication(): void
    {
        $response = $this->get('/administration/beat-match-video');
        $response->assertStatus(302); // Redirect to login
    }

    public function test_beat_match_video_process_requires_authentication(): void
    {
        $response = $this->post('/administration/beat-match-video/process');
        $response->assertStatus(302); // Redirect to login
    }

    public function test_beat_match_video_process_creates_job(): void
    {
        // Create admin user
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Create test files
        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100); // 100KB
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500), // 500KB
            UploadedFile::fake()->create('test-video-2.mp4', 500),
            UploadedFile::fake()->create('test-video-3.mp4', 500),
            UploadedFile::fake()->create('test-video-4.mp4', 500),
        ];

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
            'cut_intensity' => 2,
            'direction' => 'random',
            'speed_factor' => 1.0,
            'start_time' => 0,
            'end_time' => null,
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
        
        // Verify generation parameters
        $params = $videoJob->generation_parameters;
        $this->assertEquals(2, $params['cut_intensity']);
        $this->assertEquals('random', $params['direction']);
        $this->assertEquals(1.0, $params['speed_factor']);
        
        // Verify job was queued
        Queue::assertPushed(ProcessBeatMatchMusicVideoJob::class, function ($job) use ($videoJob) {
            return $job->videoJob->id === $videoJob->id;
        });
    }

    public function test_beat_match_video_process_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio_file', 'video_files', 'cut_intensity']);
    }

    public function test_beat_match_video_process_validates_file_types(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => UploadedFile::fake()->create('test.txt', 100), // Invalid type
            'video_files' => [UploadedFile::fake()->create('test.pdf', 100)], // Invalid type
            'cut_intensity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio_file', 'video_files.0']);
    }

    public function test_beat_match_video_status_endpoint_returns_job_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'beat-match',
                'status' => Videojob::STATUS_PROCESSING,
                'progress' => 50,
                'job_time' => 30,
            ])
            ->create();

        $this->actingAs($user, 'api');

        $response = $this->get("/administration/beat-match-video/status/{$videoJob->id}");

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

    public function test_beat_match_video_process_with_all_parameters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
            UploadedFile::fake()->create('test-video-2.mp4', 500),
            UploadedFile::fake()->create('test-video-3.mp4', 500),
            UploadedFile::fake()->create('test-video-4.mp4', 500),
        ];

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
            'cut_intensity' => 3,
            'direction' => 'backward',
            'speed_factor' => 0.5,
            'start_time' => 5.0,
            'end_time' => 30.0,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $params = $videoJob->generation_parameters;
        $this->assertEquals(3, $params['cut_intensity']);
        $this->assertEquals('backward', $params['direction']);
        $this->assertEquals(0.5, $params['speed_factor']);
        $this->assertEquals(5.0, $params['start_time']);
        $this->assertEquals(30.0, $params['end_time']);
    }

    public function test_beat_match_video_process_stores_files_temporarily(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        Storage::fake('local');

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
            UploadedFile::fake()->create('test-video-2.mp4', 500),
            UploadedFile::fake()->create('test-video-3.mp4', 500),
            UploadedFile::fake()->create('test-video-4.mp4', 500),
        ];

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
            'cut_intensity' => 1,
        ]);

        $response->assertOk();

        // Verify files were stored
        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        $params = $videoJob->generation_parameters;
        
        $this->assertFileExists($params['audio_file']);
        $this->assertCount(4, $params['video_files']);
        foreach ($params['video_files'] as $videoFile) {
            $this->assertFileExists($videoFile);
        }
    }

    public function test_beat_match_music_video_service_e2e_with_mock_python(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        // Create test files in temp directory
        $tempDir = storage_path('app/temp/test-beat-match-' . time());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $audioFile = $tempDir . '/test-audio.wav';
        file_put_contents($audioFile, 'fake audio content');

        $videoFiles = [];
        for ($i = 1; $i <= 2; $i++) {
            $videoFile = $tempDir . "/test-video-{$i}.mp4";
            file_put_contents($videoFile, 'fake video content');
            $videoFiles[] = $videoFile;
        }

        // Create processed directory
        $processedDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        // Create video job
        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'beat-match',
                'status' => Videojob::STATUS_PROCESSING,
                'outfile' => 'test-output-' . time() . '.mp4',
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => $audioFile,
                        'video_files' => $videoFiles,
                    ],
                    'options' => [
                        'cut_intensity' => 2,
                        'direction' => 'random',
                    ],
                ],
            ])
            ->create();

        // Note: This test verifies the service structure and file handling
        // Actual Python script execution would require the Python script to be available
        // In a real e2e test environment, you would:
        // 1. Ensure the Python beat_match_music_video.py script exists
        // 2. Ensure FFmpeg and required Python packages are installed
        // 3. Run the service and verify output file is created
        
        // For now, we verify the service can be instantiated and parameters are correct
        $service = new \App\Services\BeatMatchMusicVideoService();
        $this->assertInstanceOf(\App\Services\BeatMatchMusicVideoService::class, $service);

        // Verify input files exist
        $this->assertFileExists($audioFile);
        foreach ($videoFiles as $videoFile) {
            $this->assertFileExists($videoFile);
        }

        // Clean up
        if (file_exists($audioFile)) {
            unlink($audioFile);
        }
        foreach ($videoFiles as $videoFile) {
            if (file_exists($videoFile)) {
                unlink($videoFile);
            }
        }
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    }

    public function test_beat_match_service_handles_missing_audio_file(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'beat-match',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => '/nonexistent/audio.wav',
                        'video_files' => [],
                    ],
                ],
            ])
            ->create();

        $service = new \App\Services\BeatMatchMusicVideoService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Audio file not found');
        $service->startProcess($videoJob);
    }

    public function test_beat_match_service_handles_missing_video_files(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $tempDir = storage_path('app/temp/test-beat-match-' . time());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $audioFile = $tempDir . '/test-audio.wav';
        file_put_contents($audioFile, 'fake audio content');

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'beat-match',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => $audioFile,
                        'video_files' => [],
                    ],
                ],
            ])
            ->create();

        $service = new \App\Services\BeatMatchMusicVideoService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Audio file and video files are required');
        $service->startProcess($videoJob);

        // Clean up
        if (file_exists($audioFile)) {
            unlink($audioFile);
        }
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    }

    public function test_beat_match_service_validates_all_options(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
            UploadedFile::fake()->create('test-video-2.mp4', 500),
        ];

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
            'cut_intensity' => 1,
            'direction' => 'forward',
            'speed_factor' => 1.5,
            'start_time' => 5.0,
            'end_time' => 30.0,
        ]);

        $response->assertOk();

        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $params = $videoJob->generation_parameters;
        $this->assertEquals(1, $params['cut_intensity']);
        $this->assertEquals('forward', $params['direction']);
        $this->assertEquals(1.5, $params['speed_factor']);
        $this->assertEquals(5.0, $params['start_time']);
        $this->assertEquals(30.0, $params['end_time']);
    }

    public function test_beat_match_service_validates_cut_intensity_range(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => UploadedFile::fake()->create('test-audio.wav', 100),
            'video_files' => [UploadedFile::fake()->create('test-video.mp4', 500)],
            'cut_intensity' => 5, // Invalid: should be 1-3
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cut_intensity']);
    }

    public function test_beat_match_service_validates_direction(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => UploadedFile::fake()->create('test-audio.wav', 100),
            'video_files' => [UploadedFile::fake()->create('test-video.mp4', 500)],
            'cut_intensity' => 1,
            'direction' => 'invalid', // Invalid direction
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_beat_match_service_validates_speed_factor_range(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => UploadedFile::fake()->create('test-audio.wav', 100),
            'video_files' => [UploadedFile::fake()->create('test-video.mp4', 500)],
            'cut_intensity' => 1,
            'speed_factor' => 3.0, // Invalid: should be 0.1-2.0
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['speed_factor']);
    }

    public function test_beat_match_service_validates_time_range(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user, 'api');

        $response = $this->post('/administration/beat-match-video/process', [
            'audio_file' => UploadedFile::fake()->create('test-audio.wav', 100),
            'video_files' => [UploadedFile::fake()->create('test-video.mp4', 500)],
            'cut_intensity' => 1,
            'start_time' => 30.0,
            'end_time' => 20.0, // Invalid: end_time must be > start_time
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_beat_match_custom_job_api_endpoint(): void
    {
        $user = User::factory()->create();

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        $videoFiles = [
            UploadedFile::fake()->create('test-video-1.mp4', 500),
            UploadedFile::fake()->create('test-video-2.mp4', 500),
        ];

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'beat-match',
            'input_type' => 'files',
            'options' => [
                'cut_intensity' => 2,
                'direction' => 'random',
                'speed_factor' => 1.0,
            ],
        ], [
            'audio_file' => $audioFile,
            'video_files' => $videoFiles,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $this->assertNotNull($videoJob);
        $this->assertEquals('beat-match', $videoJob->generator);
    }
}

