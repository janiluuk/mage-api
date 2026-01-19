<?php

namespace Tests\Unit\Services;

use App\Models\GeneratorInstance;
use App\Services\GeneratorInstanceService;
use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratorInstanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GeneratorInstanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $loadBalancer = app(LoadBalancerService::class);
        $this->service = new GeneratorInstanceService($loadBalancer);
    }

    public function test_get_enabled_instance_url_returns_url_of_enabled_instance(): void
    {
        GeneratorInstance::factory()->create([
            'url' => 'http://test.local:7860',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://test.local:7860', $url);
    }

    public function test_get_enabled_instance_url_returns_null_when_no_enabled_instances(): void
    {
        GeneratorInstance::factory()->create(['enabled' => false]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertNull($url);
    }

    public function test_get_enabled_instance_url_ignores_disabled_instances(): void
    {
        GeneratorInstance::factory()->create([
            'url' => 'http://disabled.local:7860',
            'enabled' => false,
        ]);

        GeneratorInstance::factory()->create([
            'url' => 'http://enabled.local:7860',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://enabled.local:7860', $url);
    }

    public function test_get_enabled_instance_url_removes_trailing_slash(): void
    {
        GeneratorInstance::factory()->create([
            'url' => 'http://test.local:7860/',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://test.local:7860', $url);
    }

    public function test_get_enabled_instance_url_filters_by_type(): void
    {
        GeneratorInstance::factory()->create([
            'url' => 'http://forge.local:7860',
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        GeneratorInstance::factory()->create([
            'url' => 'http://comfy.local:7860',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $forgeUrl = $this->service->getEnabledInstanceUrl('stable_diffusion_forge');
        $this->assertEquals('http://forge.local:7860', $forgeUrl);

        $comfyUrl = $this->service->getEnabledInstanceUrl('comfyui');
        $this->assertEquals('http://comfy.local:7860', $comfyUrl);
    }

    public function test_get_enabled_instance_url_uses_load_balancing_when_multiple_enabled(): void
    {
        // Create multiple enabled instances with different load levels
        $instance1 = GeneratorInstance::factory()->create([
            'enabled' => true,
            'type' => 'stable_diffusion_forge',
            'queue_size' => 5,
            'processing_count' => 2,
        ]);
        
        $instance2 = GeneratorInstance::factory()->create([
            'enabled' => true,
            'type' => 'stable_diffusion_forge',
            'queue_size' => 2,
            'processing_count' => 1,
        ]);

        // With load balancing, the least loaded instance should be selected
        $url = $this->service->getEnabledInstanceUrl('stable_diffusion_forge');
        
        // Should return instance2 as it has lower load (2+1=3 vs 5+2=7)
        $this->assertStringContainsString($instance2->url, $url);
    }

    public function test_get_enabled_instance_returns_instance_model(): void
    {
        $created = GeneratorInstance::factory()->create([
            'name' => 'Test Instance',
            'enabled' => true,
        ]);

        $instance = $this->service->getEnabledInstance();

        $this->assertInstanceOf(GeneratorInstance::class, $instance);
        $this->assertEquals($created->id, $instance->id);
        $this->assertEquals('Test Instance', $instance->name);
    }

    public function test_get_enabled_instance_returns_null_when_no_enabled_instances(): void
    {
        GeneratorInstance::factory()->create(['enabled' => false]);

        $instance = $this->service->getEnabledInstance();

        $this->assertNull($instance);
    }

    public function test_get_enabled_instance_filters_by_type(): void
    {
        $forge = GeneratorInstance::factory()->create([
            'name' => 'Forge Instance',
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        $comfy = GeneratorInstance::factory()->create([
            'name' => 'ComfyUI Instance',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $forgeInstance = $this->service->getEnabledInstance('stable_diffusion_forge');
        $this->assertEquals($forge->id, $forgeInstance->id);

        $comfyInstance = $this->service->getEnabledInstance('comfyui');
        $this->assertEquals($comfy->id, $comfyInstance->id);
    }
}
