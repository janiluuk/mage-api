<?php

namespace Tests\Unit\Services;

use App\Models\GeneratorInstance;
use App\Models\InstanceJob;
use App\Models\Videojob;
use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadBalancerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LoadBalancerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LoadBalancerService::class);
    }

    public function test_select_instance_returns_null_when_no_instances_available(): void
    {
        $instance = $this->service->selectInstance('comfyui');
        $this->assertNull($instance);
    }

    public function test_select_instance_returns_enabled_instance(): void
    {
        GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => false,
        ]);

        $enabled = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $instance = $this->service->selectInstance('comfyui');
        $this->assertNotNull($instance);
        $this->assertEquals($enabled->id, $instance->id);
    }

    public function test_select_instance_filters_by_type(): void
    {
        $forge = GeneratorInstance::factory()->create([
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        $comfy = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $forgeInstance = $this->service->selectInstance('stable_diffusion_forge');
        $this->assertEquals($forge->id, $forgeInstance->id);

        $comfyInstance = $this->service->selectInstance('comfyui');
        $this->assertEquals($comfy->id, $comfyInstance->id);
    }

    public function test_select_least_loaded_instance(): void
    {
        $heavyInstance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 10,
            'processing_count' => 5,
        ]);

        $lightInstance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 2,
            'processing_count' => 1,
        ]);

        $selected = $this->service->selectInstance('comfyui', 'least_loaded');
        $this->assertEquals($lightInstance->id, $selected->id);
    }

    public function test_prefers_healthy_instances_over_degraded(): void
    {
        $degraded = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 0,
            'processing_count' => 0,
            'health_status' => 'degraded',
        ]);

        $healthy = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 2,
            'processing_count' => 1,
            'health_status' => 'online',
        ]);

        $selected = $this->service->selectInstance('comfyui', 'least_loaded', true);
        $this->assertEquals($healthy->id, $selected->id);
    }

    public function test_assign_job_to_instance_creates_instance_job(): void
    {
        $instance = GeneratorInstance::factory()->create(['enabled' => true]);
        $videoJob = Videojob::factory()->create();

        $instanceJob = $this->service->assignJobToInstance($videoJob->id, $instance);

        $this->assertInstanceOf(InstanceJob::class, $instanceJob);
        $this->assertEquals($instance->id, $instanceJob->instance_id);
        $this->assertEquals($videoJob->id, $instanceJob->video_job_id);
        $this->assertEquals(InstanceJob::STATUS_QUEUED, $instanceJob->status);
        $this->assertNotNull($instanceJob->assigned_at);
    }

    public function test_assign_job_increments_queue_size(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'enabled' => true,
            'queue_size' => 5,
        ]);
        $videoJob = Videojob::factory()->create();

        $this->service->assignJobToInstance($videoJob->id, $instance);

        $instance->refresh();
        $this->assertEquals(6, $instance->queue_size);
    }

    public function test_mark_job_as_started_updates_status(): void
    {
        $instance = GeneratorInstance::factory()->create(['queue_size' => 1]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        $instanceJob = $this->service->markJobAsStarted($videoJob->id);

        $this->assertNotNull($instanceJob);
        $this->assertEquals(InstanceJob::STATUS_PROCESSING, $instanceJob->status);
        $this->assertNotNull($instanceJob->started_at);
    }

    public function test_mark_job_as_started_updates_counters(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'queue_size' => 3,
            'processing_count' => 1,
        ]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        $this->service->markJobAsStarted($videoJob->id);

        $instance->refresh();
        $this->assertEquals(2, $instance->queue_size);
        $this->assertEquals(2, $instance->processing_count);
    }

    public function test_mark_job_as_completed_updates_status(): void
    {
        $instance = GeneratorInstance::factory()->create(['processing_count' => 1]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now()->subMinutes(5),
        ]);

        $instanceJob = $this->service->markJobAsCompleted($videoJob->id);

        $this->assertNotNull($instanceJob);
        $this->assertEquals(InstanceJob::STATUS_COMPLETED, $instanceJob->status);
        $this->assertNotNull($instanceJob->completed_at);
        $this->assertNotNull($instanceJob->processing_time_seconds);
    }

    public function test_mark_job_as_completed_decrements_processing_count(): void
    {
        $instance = GeneratorInstance::factory()->create(['processing_count' => 3]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now()->subMinutes(5),
        ]);

        $this->service->markJobAsCompleted($videoJob->id);

        $instance->refresh();
        $this->assertEquals(2, $instance->processing_count);
    }

    public function test_mark_job_as_failed_updates_status(): void
    {
        $instance = GeneratorInstance::factory()->create(['processing_count' => 1]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now()->subMinutes(5),
        ]);

        $instanceJob = $this->service->markJobAsFailed($videoJob->id);

        $this->assertNotNull($instanceJob);
        $this->assertEquals(InstanceJob::STATUS_FAILED, $instanceJob->status);
    }

    public function test_mark_job_as_failed_decrements_processing_count(): void
    {
        $instance = GeneratorInstance::factory()->create(['processing_count' => 2]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now()->subMinutes(5),
        ]);

        $this->service->markJobAsFailed($videoJob->id);

        $instance->refresh();
        $this->assertEquals(1, $instance->processing_count);
    }

    public function test_mark_job_as_cancelled_updates_status(): void
    {
        $instance = GeneratorInstance::factory()->create(['queue_size' => 1]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        $instanceJob = $this->service->markJobAsCancelled($videoJob->id);

        $this->assertNotNull($instanceJob);
        $this->assertEquals(InstanceJob::STATUS_CANCELLED, $instanceJob->status);
    }

    public function test_mark_job_as_cancelled_decrements_queue_size(): void
    {
        $instance = GeneratorInstance::factory()->create(['queue_size' => 5]);
        $videoJob = Videojob::factory()->create();

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        $this->service->markJobAsCancelled($videoJob->id);

        $instance->refresh();
        $this->assertEquals(4, $instance->queue_size);
    }

    public function test_refresh_queue_counts_updates_from_database(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'queue_size' => 0,
            'processing_count' => 0,
        ]);

        $videoJob1 = Videojob::factory()->create();
        $videoJob2 = Videojob::factory()->create();
        $videoJob3 = Videojob::factory()->create();

        // Create jobs directly in database (bypassing service to test refresh)
        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob1->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob2->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob3->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        // Select instance which triggers refresh
        $this->service->selectInstance();

        $instance->refresh();
        $this->assertEquals(2, $instance->queue_size);
        $this->assertEquals(1, $instance->processing_count);
    }
}
