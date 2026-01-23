<?php

namespace Tests\Unit\Services;

use App\Services\ComfyUI\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $workflowPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->workflowPath = storage_path('app/comfy/audio-workflow.json');
        
        // Ensure directory exists
        $directory = dirname($this->workflowPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test file if created
        if (file_exists($this->workflowPath) && strpos($this->workflowPath, 'test_') !== false) {
            unlink($this->workflowPath);
        }
        parent::tearDown();
    }

    public function test_prompt_builder_loads_workflow_file(): void
    {
        // Create a valid workflow file
        $workflow = [
            '1' => [
                'class_type' => 'TextToAudio',
                'inputs' => [
                    'text' => '',
                    'duration' => 45,
                ],
            ],
        ];
        file_put_contents($this->workflowPath, json_encode($workflow));

        $builder = new PromptBuilder();
        
        // If no exception is thrown, the file was loaded successfully
        $this->assertInstanceOf(PromptBuilder::class, $builder);
    }

    public function test_prompt_builder_throws_exception_for_missing_file(): void
    {
        $nonExistentPath = storage_path('app/comfy/non-existent-workflow.json');
        
        // Temporarily rename the file if it exists
        $backupPath = $this->workflowPath . '.backup';
        if (file_exists($this->workflowPath)) {
            rename($this->workflowPath, $backupPath);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audio workflow file not found');

        try {
            new PromptBuilder();
        } finally {
            // Restore the file if it was backed up
            if (file_exists($backupPath)) {
                rename($backupPath, $this->workflowPath);
            }
        }
    }

    public function test_build_prompt_sets_text_input(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'TextToAudio',
                'inputs' => [
                    'text' => '',
                    'duration' => 45,
                ],
            ],
        ];
        file_put_contents($this->workflowPath, json_encode($workflow));

        $builder = new PromptBuilder();
        $prompt = $builder->buildPrompt('test audio text');

        $this->assertIsArray($prompt);
        $this->assertArrayHasKey('1', $prompt);
        $this->assertArrayHasKey('inputs', $prompt['1']);
        $this->assertEquals('test audio text', $prompt['1']['inputs']['text']);
    }

    public function test_build_prompt_creates_deep_copy(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'TextToAudio',
                'inputs' => [
                    'text' => '',
                    'duration' => 45,
                ],
            ],
        ];
        file_put_contents($this->workflowPath, json_encode($workflow));

        $builder = new PromptBuilder();
        $prompt1 = $builder->buildPrompt('text1');
        $prompt2 = $builder->buildPrompt('text2');

        // Each call should return a new instance, not modify the original
        $this->assertEquals('text1', $prompt1['1']['inputs']['text']);
        $this->assertEquals('text2', $prompt2['1']['inputs']['text']);
    }

    public function test_build_prompt_preserves_other_inputs(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'TextToAudio',
                'inputs' => [
                    'text' => '',
                    'duration' => 45,
                    'other_param' => 'value',
                ],
            ],
        ];
        file_put_contents($this->workflowPath, json_encode($workflow));

        $builder = new PromptBuilder();
        $prompt = $builder->buildPrompt('test');

        $this->assertEquals('test', $prompt['1']['inputs']['text']);
        $this->assertEquals(45, $prompt['1']['inputs']['duration']);
        $this->assertEquals('value', $prompt['1']['inputs']['other_param']);
    }

    public function test_build_prompt_handles_complex_workflow_structure(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'TextToAudio',
                'inputs' => [
                    'text' => '',
                ],
            ],
            '2' => [
                'class_type' => 'SaveAudio',
                'inputs' => [
                    'audio' => ['1', 0],
                ],
            ],
        ];
        file_put_contents($this->workflowPath, json_encode($workflow));

        $builder = new PromptBuilder();
        $prompt = $builder->buildPrompt('test text');

        $this->assertArrayHasKey('1', $prompt);
        $this->assertArrayHasKey('2', $prompt);
        $this->assertEquals('test text', $prompt['1']['inputs']['text']);
        $this->assertEquals(['1', 0], $prompt['2']['inputs']['audio']);
    }
}

