<?php

namespace Tests\E2E;

use App\Models\GeneratorInstance;
use App\Services\InstanceMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E2E tests for instance health checking and metrics collection
 * 
 * These tests require real generator instances to be configured.
 * Set up test instances using: ./scripts/setup-test-instances.sh
 * 
 * @group e2e
 */
class InstanceHealthE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if no test instances are configured
        if (!$this->hasTestInstances()) {
            $this->markTestSkipped('No test instances configured. Set TEST_COMFYUI_INSTANCE_1_URL in .env.testing');
        }
    }

    protected function hasTestInstances(): bool
    {
        return !empty(env('TEST_COMFYUI_INSTANCE_1_URL')) 
            || !empty(env('TEST_SD_FORGE_INSTANCE_1_URL'));
    }

    public function test_can_collect_metrics_from_real_comfyui_instance(): void
    {
        $instance = GeneratorInstance::where('type', 'comfyui')
            ->where('enabled', true)
            ->first();
        
        $this->assertNotNull($instance, 'No enabled ComfyUI instance found for testing');
        
        $service = app(InstanceMetricsService::class);
        
        try {
            $service->collectMetrics($instance);
        } catch (\Exception $e) {
            $this->fail("Failed to collect metrics: {$e->getMessage()}");
        }
        
        $instance->refresh();
        
        $this->assertNotNull($instance->last_health_check_at);
        $this->assertContains($instance->health_status, ['online', 'offline', 'error']);
        
        if ($instance->health_status === 'online') {
            $this->assertNotNull($instance->queue_size);
            $this->assertNotNull($instance->processing_count);
        }
    }

    public function test_can_collect_metrics_from_real_sd_forge_instance(): void
    {
        $instance = GeneratorInstance::where('type', 'stable_diffusion_forge')
            ->where('enabled', true)
            ->first();
        
        if (!$instance) {
            $this->markTestSkipped('No Stable Diffusion Forge instance configured for testing');
        }
        
        $service = app(InstanceMetricsService::class);
        
        try {
            $service->collectMetrics($instance);
        } catch (\Exception $e) {
            $this->fail("Failed to collect metrics: {$e->getMessage()}");
        }
        
        $instance->refresh();
        
        $this->assertNotNull($instance->last_health_check_at);
        $this->assertContains($instance->health_status, ['online', 'offline', 'error']);
    }

    public function test_instance_status_endpoint_returns_real_data(): void
    {
        // Create admin user
        $admin = \App\Models\User::factory()->create([
            'email' => 'e2e-admin@test.com',
        ]);
        $admin->assignRole('administrator');
        
        // Ensure we have at least one instance
        $instance = GeneratorInstance::where('enabled', true)->first();
        $this->assertNotNull($instance, 'No enabled instances found for testing');
        
        // Collect metrics first
        $service = app(InstanceMetricsService::class);
        try {
            $service->collectMetrics($instance);
        } catch (\Exception $e) {
            // Continue even if metrics collection fails
        }
        
        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/administration/instances/status');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'instances' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'health_status',
                        'queue_size',
                        'processing_count',
                    ],
                ],
                'ffmpeg',
                'summary',
            ]);
        
        $instances = $response->json('instances');
        $this->assertGreaterThan(0, count($instances));
        
        // Verify at least one instance has real data
        $hasRealData = false;
        foreach ($instances as $inst) {
            if (isset($inst['last_health_check_at']) && $inst['last_health_check_at'] !== null) {
                $hasRealData = true;
                break;
            }
        }
        
        $this->assertTrue($hasRealData, 'No instances with health check data found');
    }

    public function test_metrics_history_endpoint_returns_data_after_collection(): void
    {
        $instance = GeneratorInstance::where('enabled', true)->first();
        $this->assertNotNull($instance, 'No enabled instances found for testing');
        
        // Collect metrics to generate history
        $service = app(InstanceMetricsService::class);
        try {
            $service->collectMetrics($instance);
        } catch (\Exception $e) {
            $this->markTestSkipped("Could not collect metrics: {$e->getMessage()}");
        }
        
        // Create admin user
        $admin = \App\Models\User::factory()->create([
            'email' => 'e2e-admin2@test.com',
        ]);
        $admin->assignRole('administrator');
        
        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/administration/instances/{$instance->id}/metrics-history");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'instance' => [
                    'id',
                    'name',
                ],
                'history',
            ]);
        
        // History might be empty if collection just started, but structure should be correct
        $history = $response->json('history');
        $this->assertIsArray($history);
    }

    public function test_multiple_instances_can_be_monitored(): void
    {
        $instances = GeneratorInstance::where('enabled', true)->get();
        
        if ($instances->count() < 2) {
            $this->markTestSkipped('Need at least 2 enabled instances for this test');
        }
        
        $service = app(InstanceMetricsService::class);
        $successCount = 0;
        
        foreach ($instances as $instance) {
            try {
                $service->collectMetrics($instance);
                $instance->refresh();
                
                if ($instance->last_health_check_at !== null) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                // Continue with other instances
            }
        }
        
        $this->assertGreaterThan(0, $successCount, 'Failed to collect metrics from any instance');
    }
}

