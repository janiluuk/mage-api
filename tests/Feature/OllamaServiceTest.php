<?php

namespace Tests\Feature;

use App\Models\GeneratorInstance;
use App\Models\User;
use App\Services\AI\OllamaService;
use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OllamaService $ollamaService;
    protected LoadBalancerService $loadBalancer;
    protected GeneratorInstance $ollamaInstance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadBalancer = app(LoadBalancerService::class);
        $this->ollamaService = app(OllamaService::class);
        
        // Create a test Ollama instance
        $this->ollamaInstance = GeneratorInstance::factory()->create([
            'name' => 'Test Ollama Instance',
            'url' => 'localhost:11434',
            'type' => 'ollama',
            'enabled' => true,
            'health_status' => 'online',
        ]);
    }

    // ============================================================================
    // Get Available Models Tests
    // ============================================================================

    public function testGetAvailableModelsRequiresInstance(): void
    {
        // Clear cache
        Cache::flush();
        
        // Mock HTTP response
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen3-18b', 'size' => 1000000000],
                    ['name' => 'llama2', 'size' => 2000000000],
                ],
            ], 200),
        ]);

        $models = $this->ollamaService->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertCount(2, $models);
        $this->assertEquals('qwen3-18b', $models[0]['name']);
        $this->assertEquals('llama2', $models[1]['name']);
    }

    public function testGetAvailableModelsUsesSpecificInstance(): void
    {
        Cache::flush();
        
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen3-18b', 'size' => 1000000000],
                ],
            ], 200),
        ]);

        $models = $this->ollamaService->getAvailableModels($this->ollamaInstance);

        $this->assertIsArray($models);
        $this->assertCount(1, $models);
        $this->assertEquals('qwen3-18b', $models[0]['name']);
    }

    public function testGetAvailableModelsHandlesHttpErrors(): void
    {
        Cache::flush();
        
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $models = $this->ollamaService->getAvailableModels($this->ollamaInstance);

        $this->assertIsArray($models);
        $this->assertEmpty($models);
    }

    public function testGetAvailableModelsCachesResults(): void
    {
        Cache::flush();
        
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [['name' => 'qwen3-18b']],
            ], 200),
        ]);

        // First call
        $models1 = $this->ollamaService->getAvailableModels($this->ollamaInstance);
        
        // Second call should use cache (no additional HTTP call)
        $models2 = $this->ollamaService->getAvailableModels($this->ollamaInstance);

        $this->assertEquals($models1, $models2);
        Http::assertSentCount(1); // Only one HTTP call due to caching
    }

    // ============================================================================
    // Generate Tests
    // ============================================================================

    public function testGenerateRequiresInstance(): void
    {
        // Disable the instance
        $this->ollamaInstance->update(['enabled' => false]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No available Ollama instances');

        $this->ollamaService->generate('Test prompt', 'qwen3-18b');
    }

    public function testGenerateSuccessfully(): void
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'response' => 'Generated script text here',
                'done' => true,
            ], 200),
        ]);

        $result = $this->ollamaService->generate(
            'Write a short story',
            'qwen3-18b',
            [],
            $this->ollamaInstance
        );

        $this->assertEquals('Generated script text here', $result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/generate'
                && $request->method() === 'POST'
                && $request->data()['model'] === 'qwen3-18b'
                && $request->data()['prompt'] === 'Write a short story';
        });
    }

    public function testGenerateWithSystemPrompt(): void
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'response' => 'Formatted response',
                'done' => true,
            ], 200),
        ]);

        $result = $this->ollamaService->generateWithSystem(
            'You are a helpful assistant',
            'What is 2+2?',
            'qwen3-18b',
            [],
            $this->ollamaInstance
        );

        $this->assertEquals('Formatted response', $result);
        
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['system'] === 'You are a helpful assistant'
                && $data['prompt'] === 'What is 2+2?';
        });
    }

    public function testGenerateWithOptions(): void
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'response' => 'Response',
                'done' => true,
            ], 200),
        ]);

        $this->ollamaService->generate(
            'Test',
            'qwen3-18b',
            [
                'temperature' => 0.5,
                'top_p' => 0.8,
                'top_k' => 20,
            ],
            $this->ollamaInstance
        );

        Http::assertSent(function ($request) {
            $options = $request->data()['options'];
            return $options['temperature'] === 0.5
                && $options['top_p'] === 0.8
                && $options['top_k'] === 20;
        });
    }

    public function testGenerateHandlesHttpErrors(): void
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'error' => 'Model not found',
            ], 404),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama service returned error');

        $this->ollamaService->generate('Test', 'invalid-model', [], $this->ollamaInstance);
    }

    public function testGenerateHandlesEmptyResponse(): void
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'response' => '',
                'done' => true,
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Empty response from Ollama service');

        $this->ollamaService->generate('Test', 'qwen3-18b', [], $this->ollamaInstance);
    }

    // ============================================================================
    // Health Check Tests
    // ============================================================================

    public function testCheckHealthReturnsTrueForHealthyInstance(): void
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        $isHealthy = $this->ollamaService->checkHealth($this->ollamaInstance);

        $this->assertTrue($isHealthy);
    }

    public function testCheckHealthReturnsFalseForUnhealthyInstance(): void
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $isHealthy = $this->ollamaService->checkHealth($this->ollamaInstance);

        $this->assertFalse($isHealthy);
    }

    public function testCheckHealthHandlesConnectionErrors(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $isHealthy = $this->ollamaService->checkHealth($this->ollamaInstance);

        $this->assertFalse($isHealthy);
    }

    // ============================================================================
    // Pull Model Tests
    // ============================================================================

    public function testPullModelSuccessfully(): void
    {
        Http::fake([
            'localhost:11434/api/pull' => Http::response([
                'status' => 'success',
            ], 200),
        ]);

        $result = $this->ollamaService->pullModel('qwen3-18b', $this->ollamaInstance);

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/pull'
                && $request->data()['name'] === 'qwen3-18b';
        });
    }

    public function testPullModelHandlesErrors(): void
    {
        Http::fake([
            'localhost:11434/api/pull' => Http::response([
                'error' => 'Model not found',
            ], 404),
        ]);

        $result = $this->ollamaService->pullModel('invalid-model', $this->ollamaInstance);

        $this->assertFalse($result);
    }

    // ============================================================================
    // URL Handling Tests
    // ============================================================================

    public function testHandlesUrlWithoutProtocol(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'url' => 'localhost:11434',
            'type' => 'ollama',
        ]);

        Http::fake([
            'http://localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        $this->ollamaService->getAvailableModels($instance);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://localhost:11434');
        });
    }

    public function testHandlesUrlWithProtocol(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'url' => 'http://localhost:11434',
            'type' => 'ollama',
        ]);

        Http::fake([
            'http://localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        $this->ollamaService->getAvailableModels($instance);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://localhost:11434');
        });
    }

    public function testHandlesUrlWithTrailingSlash(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'url' => 'http://localhost:11434/',
            'type' => 'ollama',
        ]);

        Http::fake([
            'http://localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        $this->ollamaService->getAvailableModels($instance);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/tags';
        });
    }
}

