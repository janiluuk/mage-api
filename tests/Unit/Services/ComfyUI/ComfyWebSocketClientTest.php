<?php

namespace Tests\Unit\Services\ComfyUI;

use App\Services\ComfyUI\ComfyWebSocketClient;
use ReflectionMethod;
use Tests\TestCase;

class ComfyWebSocketClientTest extends TestCase
{
    private ComfyWebSocketClient $client;
    private ReflectionMethod $matchesClientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new ComfyWebSocketClient('localhost:8188');

        // Make the private method accessible for testing
        $this->matchesClientId = new ReflectionMethod(ComfyWebSocketClient::class, 'matchesClientId');
        $this->matchesClientId->setAccessible(true);
    }

    public function test_matches_top_level_client_id(): void
    {
        $jobData = ['client_id' => 'abc-123'];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_does_not_match_different_client_id(): void
    {
        $jobData = ['client_id' => 'abc-123'];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'xyz-999'));
    }

    public function test_matches_meta_client_id(): void
    {
        $jobData = ['meta' => ['client_id' => 'abc-123']];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_matches_status_client_id(): void
    {
        $jobData = ['status' => ['client_id' => 'abc-123']];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_matches_prompt_associative_client_id(): void
    {
        $jobData = ['prompt' => ['client_id' => 'abc-123']];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_matches_prompt_extra_client_id(): void
    {
        $jobData = ['prompt' => ['extra' => ['client_id' => 'abc-123']]];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_matches_indexed_prompt_array_format(): void
    {
        // ComfyUI format: [queue_number, prompt_id, prompt_object, extra_data, output_node_ids]
        $jobData = [
            'prompt' => [
                1,                      // queue_number
                'prompt-id-456',        // prompt_id
                ['1' => ['class_type' => 'KSampler']], // prompt_object
                ['client_id' => 'abc-123'], // extra_data
                ['9'],                  // output_node_ids
            ],
        ];
        $this->assertTrue($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_indexed_prompt_array_does_not_match_wrong_client(): void
    {
        $jobData = [
            'prompt' => [
                1,
                'prompt-id-456',
                ['1' => ['class_type' => 'KSampler']],
                ['client_id' => 'abc-123'],
                ['9'],
            ],
        ];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'xyz-999'));
    }

    public function test_indexed_prompt_with_fewer_than_4_elements(): void
    {
        // Only 3 elements — index 3 does not exist
        $jobData = [
            'prompt' => [
                1,
                'prompt-id-456',
                ['1' => ['class_type' => 'KSampler']],
            ],
        ];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_indexed_prompt_with_scalar_at_index_3(): void
    {
        // Index 3 is a string, not an array
        $jobData = [
            'prompt' => [
                1,
                'prompt-id-456',
                ['1' => ['class_type' => 'KSampler']],
                'not-an-array',
                ['9'],
            ],
        ];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_associative_prompt_is_not_treated_as_indexed(): void
    {
        // Associative array — array_is_list returns false
        $jobData = [
            'prompt' => [
                'node_1' => ['class_type' => 'KSampler'],
                'node_2' => ['class_type' => 'VAEDecode'],
            ],
        ];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }

    public function test_empty_job_data_returns_false(): void
    {
        $this->assertFalse($this->matchesClientId->invoke($this->client, [], 'abc-123'));
    }

    public function test_missing_prompt_key_returns_false(): void
    {
        $jobData = ['outputs' => ['9' => ['images' => []]]];
        $this->assertFalse($this->matchesClientId->invoke($this->client, $jobData, 'abc-123'));
    }
}

