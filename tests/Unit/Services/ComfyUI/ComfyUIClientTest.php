<?php

namespace Tests\Unit\Services\ComfyUI;

use App\Exceptions\SdInstanceUnavailableException;
use App\Models\SdInstance;
use App\Services\ComfyUI\ComfyUIClient;
use App\Services\SdInstanceService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComfyUIClientTest extends TestCase
{
    use RefreshDatabase;

    protected SdInstanceService $sdInstanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sdInstanceService = new SdInstanceService();
    }

    public function test_queue_prompt_sends_workflow_to_comfyui(): void
    {
        // Create a ComfyUI instance
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        // Mock HTTP response
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'prompt_id' => 'test-prompt-123',
                'number' => 1,
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        // Use reflection to inject mock HTTP client
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $workflow = [
            '1' => ['class_type' => 'KSampler'],
        ];

        $response = $client->queuePrompt($workflow);

        $this->assertEquals('test-prompt-123', $response['prompt_id']);
        $this->assertEquals(1, $response['number']);
    }

    public function test_get_history_retrieves_prompt_history(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'test-prompt-123' => [
                    'outputs' => [
                        '9' => [
                            'images' => [
                                [
                                    'filename' => 'output_00001.png',
                                    'subfolder' => '',
                                    'type' => 'output',
                                ],
                            ],
                        ],
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $history = $client->getHistory('test-prompt-123');

        $this->assertArrayHasKey('test-prompt-123', $history);
        $this->assertArrayHasKey('outputs', $history['test-prompt-123']);
    }

    public function test_is_prompt_complete_returns_true_when_in_history(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'test-prompt-123' => ['outputs' => []],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $isComplete = $client->isPromptComplete('test-prompt-123');

        $this->assertTrue($isComplete);
    }

    public function test_is_prompt_complete_returns_false_when_not_in_history(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $isComplete = $client->isPromptComplete('test-prompt-123');

        $this->assertFalse($isComplete);
    }

    public function test_throws_exception_when_no_comfyui_instance_available(): void
    {
        // Create only a forge instance, no comfyui
        SdInstance::factory()->create([
            'url' => 'http://forge.local:7860',
            'type' => 'stable_diffusion_forge',
            'enabled' => true,
        ]);

        $this->expectException(SdInstanceUnavailableException::class);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $workflow = ['1' => ['class_type' => 'KSampler']];
        $client->queuePrompt($workflow);
    }

    public function test_get_queue_returns_queue_status(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'queue_running' => [],
                'queue_pending' => [
                    [1, 'test-prompt-123', ['prompt' => []]],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $queue = $client->getQueue();

        $this->assertArrayHasKey('queue_running', $queue);
        $this->assertArrayHasKey('queue_pending', $queue);
    }

    public function test_cancel_prompt_sends_interrupt_request(): void
    {
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new ComfyUIClient($this->sdInstanceService);
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        $response = $client->cancelPrompt('test-prompt-123');

        $this->assertEquals('ok', $response['status']);
    }
}
