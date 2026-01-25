<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Videojob;
use App\Models\ModelFile;
use App\Models\VideoJobVariant;
use App\Services\VideoJobs\VideoJobVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class VideoJobVariantsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ModelFile $model1;
    private ModelFile $model2;
    private ModelFile $model3;
    private Videojob $baseJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $this->model1 = ModelFile::factory()->create(['name' => 'Model A']);
        $this->model2 = ModelFile::factory()->create(['name' => 'Model B']);
        $this->model3 = ModelFile::factory()->create(['name' => 'Model C']);

        $this->baseJob = Videojob::factory()->create([
            'user_id' => $this->user->id,
            'model_id' => $this->model1->id,
            'status' => Videojob::STATUS_FINISHED,
            'prompt' => 'Test prompt',
            'negative_prompt' => 'Test negative',
            'seed' => 12345,
            'width' => 512,
            'height' => 512,
            'fps' => 24,
            'frame_count' => 100,
        ]);
    }

    public function test_can_create_variants_with_different_models()
    {
        $service = new VideoJobVariantService();
        
        $variants = $service->createVariants(
            $this->baseJob,
            [$this->model2->id, $this->model3->id],
            0
        );

        $this->assertCount(2, $variants);
        
        // Verify variant jobs were created with correct model IDs
        $this->assertEquals($this->model2->id, $variants[0]['job']->model_id);
        $this->assertEquals($this->model3->id, $variants[1]['job']->model_id);
        
        // Verify parameters were copied
        $this->assertEquals($this->baseJob->prompt, $variants[0]['job']->prompt);
        $this->assertEquals($this->baseJob->seed, $variants[0]['job']->seed);
        $this->assertEquals($this->baseJob->width, $variants[0]['job']->width);
    }

    public function test_variant_relationships_are_created()
    {
        $service = new VideoJobVariantService();
        
        $variants = $service->createVariants($this->baseJob, [$this->model2->id], 0);
        
        $variantRelation = $variants[0]['variant'];
        
        $this->assertEquals($this->baseJob->id, $variantRelation->base_video_job_id);
        $this->assertEquals($variants[0]['job']->id, $variantRelation->variant_video_job_id);
        $this->assertEquals($this->model2->id, $variantRelation->model_id);
    }

    public function test_can_get_variants_status()
    {
        $service = new VideoJobVariantService();
        
        $service->createVariants($this->baseJob, [$this->model2->id, $this->model3->id], 0);
        
        $status = $service->getVariantsStatus($this->baseJob);
        
        $this->assertCount(2, $status);
        $this->assertEquals('Model B', $status[0]['model_name']);
        $this->assertEquals('Model C', $status[1]['model_name']);
        $this->assertEquals(Videojob::STATUS_PENDING, $status[0]['status']);
    }

    public function test_create_variants_api_endpoint()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/variants", [
            'model_ids' => [$this->model2->id, $this->model3->id],
            'preview_frames' => 10,
            'auto_process' => false,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'base_job_id',
            'variants' => [
                '*' => ['variant_id', 'job_id', 'model_id', 'variant_name', 'status']
            ]
        ]);

        $this->assertDatabaseHas('video_job_variants', [
            'base_video_job_id' => $this->baseJob->id,
        ]);
    }

    public function test_get_variants_status_api_endpoint()
    {
        $service = new VideoJobVariantService();
        $service->createVariants($this->baseJob, [$this->model2->id], 0);

        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/video-jobs/{$this->baseJob->id}/variants");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'base_job_id',
            'variants' => [
                '*' => [
                    'variant_id',
                    'variant_name',
                    'model_name',
                    'status',
                    'progress',
                ]
            ]
        ]);
    }

    public function test_unauthorized_user_cannot_create_variants()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'api');

        $response = $this->postJson("/api/v1/video-jobs/{$this->baseJob->id}/variants", [
            'model_ids' => [$this->model2->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_variant_jobs_have_unique_outfiles()
    {
        $service = new VideoJobVariantService();
        
        $variants = $service->createVariants($this->baseJob, [$this->model2->id, $this->model3->id], 0);

        $outfile1 = $variants[0]['job']->outfile;
        $outfile2 = $variants[1]['job']->outfile;

        $this->assertNotEquals($outfile1, $outfile2);
        $this->assertNotEquals($this->baseJob->outfile, $outfile1);
    }
}
