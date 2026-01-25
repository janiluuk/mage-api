<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Videojob;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VideoJobExtensionWithParamsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ModelFile $model;
    private Videojob $baseJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->model = ModelFile::factory()->create();

        $this->baseJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'model_id' => $this->model->id,
            'status' => Videojob::STATUS_FINISHED,
            'prompt' => 'Original prompt',
            'negative_prompt' => 'Original negative',
            'seed' => 12345,
            'width' => 512,
            'height' => 512,
            'fps' => 24,
            'frame_count' => 100,
            'denoising' => 0.75,
            'cfg_scale' => 7.5,
        ]);
    }

    public function test_can_extend_job_with_same_parameters()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'job_id',
            'base_job_id',
            'status',
        ]);

        $extendedJobId = $response->json('job_id');
        $extendedJob = Videojob::find($extendedJobId);

        // Verify parameters were copied
        $this->assertEquals($this->baseJob->prompt, $extendedJob->prompt);
        $this->assertEquals($this->baseJob->negative_prompt, $extendedJob->negative_prompt);
        $this->assertEquals($this->baseJob->seed, $extendedJob->seed);
        $this->assertEquals($this->baseJob->width, $extendedJob->width);
        $this->assertEquals($this->baseJob->height, $extendedJob->height);
        $this->assertEquals($this->baseJob->model_id, $extendedJob->model_id);
        $this->assertEquals(Videojob::STATUS_PENDING, $extendedJob->status);
    }

    public function test_can_override_parameters_when_extending()
    {
        $this->actingAs($this->user, 'api');

        $newPrompt = 'New extended prompt';
        $newSeed = 99999;

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params", [
            'override_params' => [
                'prompt' => $newPrompt,
                'seed' => $newSeed,
            ],
        ]);

        $response->assertStatus(201);

        $extendedJobId = $response->json('job_id');
        $extendedJob = Videojob::find($extendedJobId);

        // Verify overridden parameters
        $this->assertEquals($newPrompt, $extendedJob->prompt);
        $this->assertEquals($newSeed, $extendedJob->seed);
        
        // Verify non-overridden parameters stayed the same
        $this->assertEquals($this->baseJob->negative_prompt, $extendedJob->negative_prompt);
        $this->assertEquals($this->baseJob->width, $extendedJob->width);
    }

    public function test_can_override_model_when_extending()
    {
        $newModel = ModelFile::factory()->create();
        
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params", [
            'override_params' => [
                'model_id' => $newModel->id,
            ],
        ]);

        $response->assertStatus(201);

        $extendedJobId = $response->json('job_id');
        $extendedJob = Videojob::find($extendedJobId);

        $this->assertEquals($newModel->id, $extendedJob->model_id);
    }

    public function test_cannot_extend_unfinished_job()
    {
        $pendingJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'status' => Videojob::STATUS_PENDING,
        ]);

        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$pendingJob->id}/extend-with-params");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Base video job must be completed before extension'
        ]);
    }

    public function test_unauthorized_user_cannot_extend_job()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params");

        $response->assertStatus(403);
    }

    public function test_extended_job_stores_metadata()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params", [
            'override_params' => [
                'prompt' => 'New prompt',
            ],
        ]);

        $response->assertStatus(201);

        $extendedJobId = $response->json('job_id');
        $extendedJob = Videojob::find($extendedJobId);

        $metadata = json_decode($extendedJob->generation_parameters, true);
        
        $this->assertTrue($metadata['is_extension']);
        $this->assertEquals($this->baseJob->id, $metadata['extended_from_job_id']);
        $this->assertArrayHasKey('overridden_params', $metadata);
    }

    public function test_extended_job_has_unique_filename()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/extend-with-params");

        $extendedJobId = $response->json('job_id');
        $extendedJob = Videojob::find($extendedJobId);

        $this->assertNotEquals($this->baseJob->filename, $extendedJob->filename);
        $this->assertNotEquals($this->baseJob->outfile, $extendedJob->outfile);
        $this->assertStringContainsString('extended_', $extendedJob->filename);
    }
}
