<?php

namespace Tests\Feature;

use App\Models\GeneratorInstance;
use App\Models\InstanceJob;
use App\Models\Videojob;
use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceLoadBalancingTest extends TestCase
{
    use RefreshDatabase;

    public function test_load_balancer_selects_least_loaded_instance(): void
    {
        $instance1 = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 5,
            'processing_count' => 2,
        ]);

        $instance2 = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 1,
            'processing_count' => 0,
        ]);

        $loadBalancer = app(LoadBalancerService::class);
        $selected = $loadBalancer->selectInstance('comfyui');

        $this->assertNotNull($selected);
        $this->assertEquals($instance2->id, $selected->id); // Should select less loaded instance
    }

    public function test_job_assignment_updates_queue_count(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'enabled' => true,
            'queue_size' => 0,
        ]);

        $videoJob = Videojob::factory()->create();

        $loadBalancer = app(LoadBalancerService::class);
        $instanceJob = $loadBalancer->assignJobToInstance($videoJob->id, $instance);

        $this->assertNotNull($instanceJob);
        $this->assertEquals(InstanceJob::STATUS_QUEUED, $instanceJob->status);
        
        $instance->refresh();
        $this->assertEquals(1, $instance->queue_size);
    }

    public function test_job_start_updates_processing_count(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'queue_size' => 1,
            'processing_count' => 0,
        ]);

        $videoJob = Videojob::factory()->create();
        
        $instanceJob = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_QUEUED,
            'assigned_at' => now(),
        ]);

        $loadBalancer = app(LoadBalancerService::class);
        $loadBalancer->markJobAsStarted($videoJob->id);

        $instance->refresh();
        $this->assertEquals(0, $instance->queue_size);
        $this->assertEquals(1, $instance->processing_count);

        $instanceJob->refresh();
        $this->assertEquals(InstanceJob::STATUS_PROCESSING, $instanceJob->status);
    }

    public function test_job_completion_updates_counters(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'processing_count' => 1,
        ]);

        $videoJob = Videojob::factory()->create();
        
        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
            'started_at' => now()->subMinutes(5),
        ]);

        $loadBalancer = app(LoadBalancerService::class);
        $loadBalancer->markJobAsCompleted($videoJob->id);

        $instance->refresh();
        $this->assertEquals(0, $instance->processing_count);

        $instanceJob = InstanceJob::where('video_job_id', $videoJob->id)->first();
        $this->assertEquals(InstanceJob::STATUS_COMPLETED, $instanceJob->status);
        $this->assertNotNull($instanceJob->processing_time_seconds);
    }
}


