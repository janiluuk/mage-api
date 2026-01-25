<?php

namespace Tests\Unit\Services;

use App\Models\GeneratorInstance;
use App\Services\InstanceMetricsService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstanceMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function createServiceWithMockClient(array $responses): InstanceMetricsService
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);

        $service = new InstanceMetricsService();
        
        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($service, $mockClient);

        return $service;
    }

    public function test_collect_metrics_updates_instance_health_status_online(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'health_status' => 'offline',
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        $service = $this->createServiceWithMockClient([$queueResponse]);
        $metrics = $service->collectMetrics($instance);

        $this->assertEquals('online', $metrics['health_status']);
        
        $instance->refresh();
        $this->assertEquals('online', $instance->health_status);
        $this->assertNotNull($instance->last_health_check_at);
    }

    public function test_collect_metrics_marks_instance_offline_on_connection_error(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'health_status' => 'online',
        ]);

        $connectException = new ConnectException(
            'Connection failed',
            new Request('GET', 'test')
        );

        $service = $this->createServiceWithMockClient([$connectException]);

        try {
            $service->collectMetrics($instance);
        } catch (\Exception $e) {
            // Expected
        }

        $instance->refresh();
        $this->assertEquals('offline', $instance->health_status);
    }

    public function test_collect_comfyui_metrics_parses_queue_data(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [['job_id' => 1], ['job_id' => 2]],
            'queue_pending' => [['job_id' => 3]],
        ]));

        $service = $this->createServiceWithMockClient([$queueResponse]);
        $metrics = $service->collectMetrics($instance);

        $this->assertEquals('online', $metrics['health_status']);
        
        $instance->refresh();
        $this->assertEquals(2, $instance->processing_count);
    }

    public function test_collect_comfyui_metrics_parses_system_stats(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        $systemResponse = new Response(200, [], json_encode([
            'gpu_utilization' => 75,
            'cpu_utilization' => 45,
            'memory_utilization' => 60,
            'current_model' => 'stable-diffusion-xl',
        ]));

        $service = $this->createServiceWithMockClient([$queueResponse, $systemResponse]);
        $metrics = $service->collectMetrics($instance);

        $this->assertEquals(75, $metrics['gpu_utilization']);
        $this->assertEquals(45, $metrics['cpu_utilization']);
        $this->assertEquals(60, $metrics['memory_utilization']);
        $this->assertEquals('stable-diffusion-xl', $metrics['current_model']);

        $instance->refresh();
        $this->assertEquals(75, $instance->gpu_utilization);
        $this->assertEquals('stable-diffusion-xl', $instance->current_model);
    }

    public function test_collect_sd_forge_metrics_checks_health_endpoint(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        $healthResponse = new Response(200, [], json_encode([
            'gpu_utilization' => 80,
            'cpu_utilization' => 50,
            'memory_utilization' => 70,
            'current_model' => 'sd-v1.5',
        ]));

        $service = $this->createServiceWithMockClient([$healthResponse]);
        $metrics = $service->collectMetrics($instance);

        $this->assertEquals('online', $metrics['health_status']);
        $this->assertEquals(80, $metrics['gpu_utilization']);
        $this->assertEquals('sd-v1.5', $metrics['current_model']);
    }

    public function test_collect_metrics_marks_degraded_on_high_queue_load(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 40,
            'processing_count' => 15,
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        $service = $this->createServiceWithMockClient([$queueResponse]);
        $metrics = $service->collectMetrics($instance);

        // Total load is 55 (40 + 15), should be marked as degraded
        $this->assertEquals('degraded', $metrics['health_status']);
    }

    public function test_store_metrics_history_creates_record(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'queue_size' => 5,
            'processing_count' => 2,
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        $systemResponse = new Response(200, [], json_encode([
            'gpu_utilization' => 75,
            'cpu_utilization' => 45,
            'memory_utilization' => 60,
            'current_model' => 'stable-diffusion-xl',
        ]));

        $service = $this->createServiceWithMockClient([$queueResponse, $systemResponse]);
        $service->collectMetrics($instance);

        // Check that metrics history was created
        $history = DB::table('instance_metrics_history')
            ->where('instance_id', $instance->id)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(75, $history->gpu_utilization);
        $this->assertEquals(45, $history->cpu_utilization);
        $this->assertEquals(60, $history->memory_utilization);
        $this->assertEquals('stable-diffusion-xl', $history->current_model);
        $this->assertEquals('online', $history->health_status);
    }

    public function test_collect_metrics_handles_missing_system_stats_gracefully(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        // System stats endpoint returns 404
        $systemError = new RequestException(
            'Not found',
            new Request('GET', 'test'),
            new Response(404)
        );

        $service = $this->createServiceWithMockClient([$queueResponse, $systemError]);
        $metrics = $service->collectMetrics($instance);

        // Should still be online even if system stats fail
        $this->assertEquals('online', $metrics['health_status']);
        $this->assertNull($metrics['gpu_utilization']);
        $this->assertNull($metrics['current_model']);
    }

    public function test_collect_metrics_for_all_instances_processes_enabled_only(): void
    {
        $enabled1 = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => true,
            'health_status' => 'offline',
        ]);

        $disabled = GeneratorInstance::factory()->create([
            'type' => 'comfyui',
            'enabled' => false,
            'health_status' => 'offline',
        ]);

        $queueResponse = new Response(200, [], json_encode([
            'queue_running' => [],
            'queue_pending' => [],
        ]));

        // Create mock for single call (only one enabled instance)
        $service = $this->createServiceWithMockClient([
            $queueResponse,
        ]);

        $service->collectMetricsForAllInstances();

        $enabled1->refresh();
        $disabled->refresh();

        $this->assertEquals('online', $enabled1->health_status);
        $this->assertEquals('offline', $disabled->health_status); // Should not be updated
    }

    public function test_sd_forge_marks_degraded_on_error(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
            'health_status' => 'online',
        ]);

        // Health endpoint returns 500
        $healthError = new RequestException(
            'Server error',
            new Request('GET', 'test'),
            new Response(500)
        );

        // Fallback check succeeds
        $fallbackResponse = new Response(200);

        $service = $this->createServiceWithMockClient([$healthError, $fallbackResponse]);
        $metrics = $service->collectMetrics($instance);

        $this->assertEquals('degraded', $metrics['health_status']);
    }
}
