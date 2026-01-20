<?php

namespace Tests\Feature;

use App\Jobs\ProcessAudioTrackSplitJob;
use App\Models\User;
use App\Models\Videojob;
use App\Models\UserFile;
use App\Services\AudioTrackSplitService;
use App\Services\UVR5Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class AudioTrackSplitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Queue::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_audio_track_split_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files',
            'options' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_audio_track_split_process_creates_job_with_file_input(): void
    {
        $user = User::factory()->create();

        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100); // 100KB

        $this->actingAs($user, 'api');

        $response = $this->call('POST', '/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files',
            'options' => json_encode([
                'model' => 'MDX-Net-InstVoc_HQ_3',
                'output_format' => 'wav',
            ]),
        ], [], [
            'audio_file' => $audioFile,
        ], [
            'Accept' => 'application/json',
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
        $this->assertEquals('audio-track-split', $videoJob->generator);
        $this->assertEquals(Videojob::STATUS_APPROVED, $videoJob->status);
        
        // Verify generation parameters
        $params = $videoJob->generation_parameters;
        $this->assertEquals('MDX-Net-InstVoc_HQ_3', $params['options']['model'] ?? null);
        $this->assertEquals('wav', $params['options']['output_format'] ?? null);
        
        // Verify job was queued
        Queue::assertPushed(ProcessAudioTrackSplitJob::class, function ($job) use ($videoJob) {
            return $job->videoJob->id === $videoJob->id;
        });
    }

    public function test_audio_track_split_process_creates_job_with_job_id_input(): void
    {
        $user = User::factory()->create();

        // Create a source job with audio output
        $sourceJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_FINISHED,
                'outfile' => 'test-output.wav',
                'mimetype' => 'audio/wav',
            ])
            ->create();

        // Create output file for source job
        $outputDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $outputFile = $outputDir . '/test-output.wav';
        file_put_contents($outputFile, 'fake audio content');

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files', // Can be files even with job_id
            'options' => [
                'model' => 'MDX-Net-InstVoc_HQ_3',
                'output_format' => 'wav',
                'job_id' => $sourceJob->id,
            ],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Clean up
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }

    public function test_audio_track_split_service_splits_audio_file(): void
    {
        $user = User::factory()->create();

        // Create mock UVR5 client
        $mockUVR5Client = Mockery::mock(UVR5Client::class);
        $mockOutputFiles = [
            storage_path('app/temp/test-vocals.wav'),
            storage_path('app/temp/test-instrumental.wav'),
        ];

        $mockUVR5Client->shouldReceive('splitAudio')
            ->once()
            ->andReturn($mockOutputFiles);

        // Create test audio file
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $audioFile = $tempDir . '/test-input.wav';
        file_put_contents($audioFile, 'fake audio content');

        // Create output files
        foreach ($mockOutputFiles as $outputFile) {
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            file_put_contents($outputFile, 'fake output content');
        }

        // Create video job
        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => $audioFile,
                    ],
                    'options' => [
                        'model' => 'MDX-Net-InstVoc_HQ_3',
                        'output_format' => 'wav',
                    ],
                ],
            ])
            ->create();

        // Ensure processed directory exists
        $processedDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        // Run service
        $service = new AudioTrackSplitService($mockUVR5Client);
        $service->startProcess($videoJob);

        // Verify job was updated
        $videoJob->refresh();
        $this->assertEquals(Videojob::STATUS_FINISHED, $videoJob->status);
        $this->assertNotNull($videoJob->url);
        $this->assertNotNull($videoJob->outfile);

        // Verify output files were created
        $params = $videoJob->generation_parameters;
        $this->assertArrayHasKey('output_files', $params);
        $this->assertNotEmpty($params['output_files']);

        // Clean up
        if (file_exists($audioFile)) {
            unlink($audioFile);
        }
        foreach ($mockOutputFiles as $outputFile) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function test_audio_track_split_validates_file_types(): void
    {
        $user = User::factory()->create();
        
        $this->actingAs($user, 'api');

        $response = $this->call('POST', '/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files',
            'options' => json_encode([
                'model' => 'MDX-Net-InstVoc_HQ_3',
                'output_format' => 'wav',
            ]),
        ], [], [
            'audio_file' => UploadedFile::fake()->create('test.txt', 100), // Invalid type
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio_file']);
    }

    public function test_uvr5_client_test_connection(): void
    {
        $client = new UVR5Client();
        
        // This will test if Docker is available
        $result = $client->testConnection();
        
        // We can't guarantee Docker is available in test environment,
        // so just verify the method exists and doesn't throw
        $this->assertIsBool($result);
    }

    public function test_audio_track_split_service_handles_missing_audio_file(): void
    {
        $user = User::factory()->create();

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => '/nonexistent/file.wav',
                    ],
                ],
            ])
            ->create();

        $service = new AudioTrackSplitService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Audio file not found');
        $service->startProcess($videoJob);
    }

    public function test_audio_track_split_service_resolves_audio_from_job_id(): void
    {
        $user = User::factory()->create();

        // Create source job with output file
        $sourceJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_FINISHED,
                'outfile' => 'source-output.wav',
                'mimetype' => 'audio/wav',
            ])
            ->create();

        // Create the source output file
        $outputDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $sourceOutputFile = $outputDir . '/source-output.wav';
        file_put_contents($sourceOutputFile, 'fake audio content');

        // Create mock UVR5 client
        $mockUVR5Client = Mockery::mock(UVR5Client::class);
        $mockOutputFiles = [
            storage_path('app/temp/test-vocals.wav'),
            storage_path('app/temp/test-instrumental.wav'),
        ];

        $mockUVR5Client->shouldReceive('splitAudio')
            ->once()
            ->andReturn($mockOutputFiles);

        // Create output files
        foreach ($mockOutputFiles as $outputFile) {
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            file_put_contents($outputFile, 'fake output content');
        }

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'job_id' => $sourceJob->id,
                    ],
                    'options' => [
                        'model' => 'MDX-Net-InstVoc_HQ_3',
                        'output_format' => 'wav',
                    ],
                ],
            ])
            ->create();

        $processedDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $service = new AudioTrackSplitService($mockUVR5Client);
        $service->startProcess($videoJob);

        $videoJob->refresh();
        $this->assertEquals(Videojob::STATUS_FINISHED, $videoJob->status);

        // Clean up
        if (file_exists($sourceOutputFile)) {
            unlink($sourceOutputFile);
        }
        foreach ($mockOutputFiles as $outputFile) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function test_audio_track_split_service_creates_user_files(): void
    {
        $user = User::factory()->create();

        $mockUVR5Client = Mockery::mock(UVR5Client::class);
        $mockOutputFiles = [
            storage_path('app/temp/test-vocals.wav'),
            storage_path('app/temp/test-instrumental.wav'),
        ];

        $mockUVR5Client->shouldReceive('splitAudio')
            ->once()
            ->andReturn($mockOutputFiles);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $audioFile = $tempDir . '/test-input.wav';
        file_put_contents($audioFile, 'fake audio content');

        foreach ($mockOutputFiles as $outputFile) {
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            file_put_contents($outputFile, 'fake output content');
        }

        $videoJob = Videojob::factory()
            ->for($user, 'user')
            ->state([
                'generator' => 'audio-track-split',
                'status' => Videojob::STATUS_PROCESSING,
                'generation_parameters' => [
                    'input_files' => [
                        'audio_file' => $audioFile,
                    ],
                    'options' => [
                        'output_format' => 'wav',
                    ],
                ],
            ])
            ->create();

        $processedDir = storage_path('app' . config('app.paths.processed'));
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $initialUserFileCount = UserFile::where('user_id', $user->id)->count();

        $service = new AudioTrackSplitService($mockUVR5Client);
        $service->startProcess($videoJob);

        // Verify UserFile entries were created
        $finalUserFileCount = UserFile::where('user_id', $user->id)->count();
        $this->assertGreaterThan($initialUserFileCount, $finalUserFileCount);

        // Verify UserFile entries have correct properties
        $userFiles = UserFile::where('user_id', $user->id)
            ->where('type', 'audio')
            ->latest()
            ->take(count($mockOutputFiles))
            ->get();

        $this->assertGreaterThanOrEqual(count($mockOutputFiles), $userFiles->count());
        foreach ($userFiles as $userFile) {
            $this->assertEquals('audio/wav', $userFile->mime_type);
            $this->assertNotNull($userFile->path);
            $this->assertGreaterThan(0, $userFile->size);
        }

        // Clean up
        if (file_exists($audioFile)) {
            unlink($audioFile);
        }
        foreach ($mockOutputFiles as $outputFile) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function test_audio_track_split_process_with_project_input(): void
    {
        $user = User::factory()->create();

        // Create a project with audio file
        $projectId = 123;
        
        // Create the actual file on disk
        Storage::fake('local');
        $audioPath = 'processed/test-audio.wav';
        Storage::disk('local')->put($audioPath, 'fake audio content');
        
        $userFile = UserFile::factory()
            ->for($user, 'user')
            ->state([
                'project_id' => $projectId,
                'mime_type' => 'audio/wav',
                'disk' => 'local',
                'path' => $audioPath,
            ])
            ->create();

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'project',
            'options' => [
                'model' => 'MDX-Net-InstVoc_HQ_3',
                'output_format' => 'wav',
            ],
            'project_id' => $projectId,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $jobId = $response->json('job_id');
        $videoJob = Videojob::find($jobId);
        
        $this->assertNotNull($videoJob);
        $this->assertEquals('audio-track-split', $videoJob->generator);
    }

    public function test_audio_track_split_validates_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $response = $this->call('POST', '/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files',
            'options' => json_encode([
                'output_format' => 'invalid_format', // Invalid format
            ]),
        ], [], [
            'audio_file' => UploadedFile::fake()->create('test.wav', 100),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['output_format']);
    }
}

