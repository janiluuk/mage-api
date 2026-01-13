<?php

namespace Tests\Unit\Services;

use App\Models\SdInstance;
use App\Services\SdInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SdInstanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SdInstanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SdInstanceService();
    }

    public function test_get_enabled_instance_url_returns_url_of_enabled_instance(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://test.local:7860',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://test.local:7860', $url);
    }

    public function test_get_enabled_instance_url_returns_null_when_no_enabled_instances(): void
    {
        SdInstance::factory()->create(['enabled' => false]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertNull($url);
    }

    public function test_get_enabled_instance_url_ignores_disabled_instances(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://disabled.local:7860',
            'enabled' => false,
        ]);

        SdInstance::factory()->create([
            'url' => 'http://enabled.local:7860',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://enabled.local:7860', $url);
    }

    public function test_get_enabled_instance_url_removes_trailing_slash(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://test.local:7860/',
            'enabled' => true,
        ]);

        $url = $this->service->getEnabledInstanceUrl();

        $this->assertEquals('http://test.local:7860', $url);
    }

    public function test_get_enabled_instance_url_filters_by_type(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://forge.local:7860',
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        SdInstance::factory()->create([
            'url' => 'http://comfy.local:7860',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $forgeUrl = $this->service->getEnabledInstanceUrl('stable_diffusion_forge');
        $this->assertEquals('http://forge.local:7860', $forgeUrl);

        $comfyUrl = $this->service->getEnabledInstanceUrl('comfyui');
        $this->assertEquals('http://comfy.local:7860', $comfyUrl);
    }

    public function test_get_enabled_instance_url_returns_random_instance_when_multiple_enabled(): void
    {
        SdInstance::factory()->count(5)->create([
            'enabled' => true,
            'type' => 'stable_diffusion_forge',
        ]);

        $urls = [];
        for ($i = 0; $i < 20; $i++) {
            $url = $this->service->getEnabledInstanceUrl('stable_diffusion_forge');
            $urls[$url] = true;
        }

        // With 20 iterations and 5 instances, we should get at least 2 different URLs
        // (statistically almost certain)
        $this->assertGreaterThanOrEqual(2, count($urls));
    }

    public function test_get_enabled_instance_returns_instance_model(): void
    {
        $created = SdInstance::factory()->create([
            'name' => 'Test Instance',
            'enabled' => true,
        ]);

        $instance = $this->service->getEnabledInstance();

        $this->assertInstanceOf(SdInstance::class, $instance);
        $this->assertEquals($created->id, $instance->id);
        $this->assertEquals('Test Instance', $instance->name);
    }

    public function test_get_enabled_instance_returns_null_when_no_enabled_instances(): void
    {
        SdInstance::factory()->create(['enabled' => false]);

        $instance = $this->service->getEnabledInstance();

        $this->assertNull($instance);
    }

    public function test_get_enabled_instance_filters_by_type(): void
    {
        $forge = SdInstance::factory()->create([
            'name' => 'Forge Instance',
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        $comfy = SdInstance::factory()->create([
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
