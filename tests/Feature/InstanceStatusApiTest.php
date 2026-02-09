<?php

namespace Tests\Feature;

use App\Models\GeneratorInstance;
use App\Models\InstanceJob;
use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstanceStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        $this->adminUser->assignRole('administrator');
    }

    public function test_can_get_instance_status_without_authentication(): void
    {
        $response = $this->getJson('/api/administration/instances/status');

        // The middleware returns 401 for authentication failures on API routes
        $response->assertStatus(401);
    }

    public function test_can_get_instance_status_as_admin(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'name' => 'Test Instance',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instances' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'queue_size',
                        'processing_count',
                        'health_status',
                ],
                ],
                'ffmpeg',
                'summary',
            ]);

        $this->assertCount(1, $response->json('instances'));
        $this->assertEquals('Test Instance', $response->json('instances.0.name'));
    }

    public function test_status_includes_ffmpeg_information(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ffmpeg' => [
                    'active_encoding_count',
                    'pending_encoding_count',
                    'total_queue_size',
                    'active_jobs',
                ],
            ]);
    }

    public function test_can_get_metrics_history_without_authentication(): void
    {
        $instance = GeneratorInstance::factory()->create();
        
        $response = $this->getJson("/api/administration/instances/{$instance->id}/metrics-history");

        $response->assertStatus(401);
    }

    public function test_can_get_metrics_history_as_admin(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'name' => 'Test Instance',
            'type' => 'comfyui',
        ]);

        // Insert recent metrics (within 24 hours)
        DB::table('instance_metrics_history')->insert([
            'instance_id' => $instance->id,
            'health_status' => 'online',
            'gpu_utilization' => 75.5,
            'cpu_utilization' => 45.2,
            'memory_utilization' => 60.8,
            'queue_size' => 2,
            'processing_count' => 1,
            'current_model' => 'stable-diffusion-xl',
            'recorded_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        // Insert older metrics (outside 24 hours - should not be returned)
        DB::table('instance_metrics_history')->insert([
            'instance_id' => $instance->id,
            'health_status' => 'online',
            'gpu_utilization' => 70.0,
            'cpu_utilization' => 40.0,
            'memory_utilization' => 55.0,
            'queue_size' => 1,
            'processing_count' => 0,
            'current_model' => 'stable-diffusion-xl',
            'recorded_at' => now()->subHours(25),
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/metrics-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instance' => [
                    'id',
                    'name',
                ],
                'history' => [
                    '*' => [
                        'recorded_at',
                        'gpu_utilization',
                        'cpu_utilization',
                        'memory_utilization',
                        'queue_size',
                        'processing_count',
                        'health_status',
                        'current_model',
                    ],
                ],
            ]);

        $history = $response->json('history');
        $this->assertCount(1, $history);
        $this->assertEquals(75.5, $history[0]['gpu_utilization']);
        $this->assertEquals('Test Instance', $response->json('instance.name'));
    }

    public function test_metrics_history_returns_empty_array_when_no_data(): void
    {
        $instance = GeneratorInstance::factory()->create();

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/metrics-history");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('history'));
    }

    public function test_metrics_history_returns_404_for_nonexistent_instance(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/999/metrics-history');

        $response->assertStatus(404);
    }

    public function test_can_get_job_history_without_authentication(): void
    {
        $instance = GeneratorInstance::factory()->create();
        
        $response = $this->getJson("/api/administration/instances/{$instance->id}/job-history");

        $response->assertStatus(401);
    }

    public function test_can_get_job_history_as_admin(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'name' => 'Test Instance',
            'type' => 'comfyui',
        ]);

        $videoJob1 = Videojob::factory()->create([
            'prompt' => 'Test prompt 1',
            'generator' => 'comfyui',
        ]);

        $videoJob2 = Videojob::factory()->create([
            'prompt' => 'Test prompt 2',
            'generator' => 'comfyui',
        ]);

        // Create completed jobs
        $job1 = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob1->id,
            'status' => InstanceJob::STATUS_COMPLETED,
            'assigned_at' => now()->subHours(3),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(1),
            'processing_time_seconds' => 3600,
        ]);

        $job2 = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob2->id,
            'status' => InstanceJob::STATUS_COMPLETED,
            'assigned_at' => now()->subHours(5),
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHours(3),
            'processing_time_seconds' => 3600,
        ]);

        // Create a non-completed job (should not be returned)
        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => Videojob::factory()->create()->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(20),
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/job-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instance' => [
                    'id',
                    'name',
                ],
                'jobs' => [
                    '*' => [
                        'id',
                        'video_job_id',
                        'processing_time_seconds',
                        'assigned_at',
                        'started_at',
                        'completed_at',
                        'video_job' => [
                            'id',
                            'prompt',
                            'generator',
                        ],
                    ],
                ],
            ]);

        $jobs = $response->json('jobs');
        $this->assertCount(2, $jobs);
        $this->assertEquals('Test Instance', $response->json('instance.name'));
        $this->assertEquals($job1->id, $jobs[0]['id']);
        $this->assertEquals($job2->id, $jobs[1]['id']);
    }

    public function test_job_history_returns_empty_array_when_no_completed_jobs(): void
    {
        $instance = GeneratorInstance::factory()->create();

        // Create only processing jobs
        InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => Videojob::factory()->create()->id,
            'status' => InstanceJob::STATUS_PROCESSING,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/job-history");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('jobs'));
    }

    public function test_job_history_returns_404_for_nonexistent_instance(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/999/job-history');

        $response->assertStatus(404);
    }

    public function test_job_history_limits_to_50_jobs(): void
    {
        $instance = GeneratorInstance::factory()->create();

        // Create 55 completed jobs
        for ($i = 0; $i < 55; $i++) {
            InstanceJob::create([
                'instance_id' => $instance->id,
                'video_job_id' => Videojob::factory()->create()->id,
                'status' => InstanceJob::STATUS_COMPLETED,
                'assigned_at' => now()->subHours(55 - $i),
                'started_at' => now()->subHours(55 - $i)->addMinutes(5),
                'completed_at' => now()->subHours(55 - $i)->addMinutes(10),
                'processing_time_seconds' => 300,
            ]);
        }

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/job-history");

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('jobs'));
    }

    public function test_job_history_orders_by_completed_at_descending(): void
    {
        $instance = GeneratorInstance::factory()->create();

        $videoJob1 = Videojob::factory()->create();
        $videoJob2 = Videojob::factory()->create();
        $videoJob3 = Videojob::factory()->create();

        // Create jobs with different completion times
        $job1 = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob1->id,
            'status' => InstanceJob::STATUS_COMPLETED,
            'completed_at' => now()->subHours(3),
            'processing_time_seconds' => 100,
        ]);

        $job2 = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob2->id,
            'status' => InstanceJob::STATUS_COMPLETED,
            'completed_at' => now()->subHours(1), // Most recent
            'processing_time_seconds' => 200,
        ]);

        $job3 = InstanceJob::create([
            'instance_id' => $instance->id,
            'video_job_id' => $videoJob3->id,
            'status' => InstanceJob::STATUS_COMPLETED,
            'completed_at' => now()->subHours(2),
            'processing_time_seconds' => 150,
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/job-history");

        $response->assertStatus(200);
        $jobs = $response->json('jobs');
        
        // Should be ordered by completed_at descending (most recent first)
        $this->assertEquals($job2->id, $jobs[0]['id']);
        $this->assertEquals($job3->id, $jobs[1]['id']);
        $this->assertEquals($job1->id, $jobs[2]['id']);
    }
}


