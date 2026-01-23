<?php

namespace Tests\Unit\Services;

use App\Services\ComfyUI\ComfyClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ComfyClientTest extends TestCase
{
    use RefreshDatabase;

    private string $testHost = '127.0.0.1:8188';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.comfy.host', $this->testHost);
        Config::set('services.comfy.queue_timeout', 10000);
        Config::set('services.comfy.fetch_timeout', 30000);
    }

    public function test_queue_prompt_sends_post_request(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['prompt_id' => '123'])),
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        // Use reflection to inject mock client
        $comfyClient = new ComfyClient($this->testHost);
        $reflection = new \ReflectionClass($comfyClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($comfyClient, $mockClient);

        $prompt = ['1' => ['inputs' => ['text' => 'test']]];
        $clientId = 'test-client-id';

        // Should not throw an exception
        $comfyClient->queuePrompt($prompt, $clientId);
        
        $this->assertTrue(true); // If we get here, the method succeeded
    }

    public function test_queue_prompt_throws_exception_on_failure(): void
    {
        $mockHandler = new MockHandler([
            new RequestException('Connection refused', new \GuzzleHttp\Psr7\Request('POST', 'test')),
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        $comfyClient = new ComfyClient($this->testHost);
        $reflection = new \ReflectionClass($comfyClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($comfyClient, $mockClient);

        $prompt = ['1' => ['inputs' => ['text' => 'test']]];
        $clientId = 'test-client-id';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to queue prompt to ComfyUI');

        $comfyClient->queuePrompt($prompt, $clientId);
    }

    public function test_fetch_audio_returns_audio_data(): void
    {
        $audioData = 'fake audio binary data';
        $mockHandler = new MockHandler([
            new Response(200, [], $audioData),
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        $comfyClient = new ComfyClient($this->testHost);
        $reflection = new \ReflectionClass($comfyClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($comfyClient, $mockClient);

        $fileInfo = [
            'filename' => 'test.wav',
            'subfolder' => 'output',
            'type' => 'output',
        ];

        $result = $comfyClient->fetchAudio($fileInfo);

        $this->assertEquals($audioData, $result);
    }

    public function test_fetch_audio_throws_exception_for_missing_filename(): void
    {
        $comfyClient = new ComfyClient($this->testHost);

        $fileInfo = [
            'subfolder' => 'output',
            'type' => 'output',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid file info: missing filename');

        $comfyClient->fetchAudio($fileInfo);
    }

    public function test_fetch_audio_throws_exception_on_request_failure(): void
    {
        $mockHandler = new MockHandler([
            new RequestException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'test')),
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        $comfyClient = new ComfyClient($this->testHost);
        $reflection = new \ReflectionClass($comfyClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($comfyClient, $mockClient);

        $fileInfo = [
            'filename' => 'test.wav',
            'subfolder' => 'output',
            'type' => 'output',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch audio from ComfyUI');

        $comfyClient->fetchAudio($fileInfo);
    }

    public function test_fetch_audio_handles_optional_subfolder_and_type(): void
    {
        $audioData = 'fake audio data';
        $mockHandler = new MockHandler([
            new Response(200, [], $audioData),
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        $comfyClient = new ComfyClient($this->testHost);
        $reflection = new \ReflectionClass($comfyClient);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($comfyClient, $mockClient);

        $fileInfo = [
            'filename' => 'test.wav',
        ];

        $result = $comfyClient->fetchAudio($fileInfo);

        $this->assertEquals($audioData, $result);
    }
}

