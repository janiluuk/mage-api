<?php

namespace Tests\Unit\Services\ComfyUI;

use App\Services\ComfyUI\WorkflowProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processor = new WorkflowProcessor();
    }

    public function test_process_workflow_injects_prompt(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => 'default prompt',
                ],
            ],
        ];

        $inputs = [
            'prompt' => 'a beautiful sunset over mountains',
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals('a beautiful sunset over mountains', $processed['1']['inputs']['text']);
    }

    public function test_process_workflow_injects_negative_prompt(): void
    {
        $workflow = [
            '2' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'negative' => 'default negative',
                ],
            ],
        ];

        $inputs = [
            'negative_prompt' => 'blurry, low quality',
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals('blurry, low quality', $processed['2']['inputs']['negative']);
    }

    public function test_process_workflow_injects_seed(): void
    {
        $workflow = [
            '3' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'seed' => 12345,
                ],
            ],
        ];

        $inputs = [
            'seed' => 99999,
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals(99999, $processed['3']['inputs']['seed']);
    }

    public function test_process_workflow_injects_image(): void
    {
        $workflow = [
            '4' => [
                'class_type' => 'LoadImage',
                'inputs' => [
                    'image' => 'default.png',
                ],
            ],
        ];

        $inputs = [
            'image' => 'uploaded_image.png',
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals('uploaded_image.png', $processed['4']['inputs']['image']);
    }

    public function test_process_workflow_injects_numeric_parameters(): void
    {
        $workflow = [
            '5' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'steps' => 20,
                    'cfg' => 7.0,
                    'denoise' => 1.0,
                ],
            ],
        ];

        $inputs = [
            'steps' => 30,
            'cfg' => 8.5,
            'denoise' => 0.75,
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals(30, $processed['5']['inputs']['steps']);
        $this->assertEquals(8.5, $processed['5']['inputs']['cfg']);
        $this->assertEquals(0.75, $processed['5']['inputs']['denoise']);
    }

    public function test_process_workflow_handles_multiple_nodes(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => 'default prompt',
                ],
            ],
            '2' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'negative' => 'default negative',
                ],
            ],
            '3' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'seed' => 12345,
                    'steps' => 20,
                ],
            ],
        ];

        $inputs = [
            'prompt' => 'new prompt',
            'negative_prompt' => 'new negative',
            'seed' => 99999,
            'steps' => 30,
        ];

        $processed = $this->processor->processWorkflow($workflow, $inputs);

        $this->assertEquals('new prompt', $processed['1']['inputs']['text']);
        $this->assertEquals('new negative', $processed['2']['inputs']['negative']);
        $this->assertEquals(99999, $processed['3']['inputs']['seed']);
        $this->assertEquals(30, $processed['3']['inputs']['steps']);
    }

    public function test_validate_workflow_returns_true_for_valid_workflow(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $isValid = $this->processor->validateWorkflow($workflow);

        $this->assertTrue($isValid);
    }

    public function test_validate_workflow_returns_false_for_empty_workflow(): void
    {
        $workflow = [];

        $isValid = $this->processor->validateWorkflow($workflow);

        $this->assertFalse($isValid);
    }

    public function test_validate_workflow_returns_false_for_workflow_without_nodes(): void
    {
        $workflow = [
            'metadata' => ['version' => '1.0'],
        ];

        $isValid = $this->processor->validateWorkflow($workflow);

        $this->assertFalse($isValid);
    }

    public function test_extract_outputs_returns_image_outputs(): void
    {
        $historyData = [
            'outputs' => [
                '9' => [
                    'images' => [
                        [
                            'filename' => 'output_00001.png',
                            'subfolder' => 'batch_001',
                            'type' => 'output',
                        ],
                        [
                            'filename' => 'output_00002.png',
                            'subfolder' => 'batch_001',
                            'type' => 'output',
                        ],
                    ],
                ],
            ],
        ];

        $outputs = $this->processor->extractOutputs($historyData);

        $this->assertCount(2, $outputs);
        $this->assertEquals('image', $outputs[0]['type']);
        $this->assertEquals('output_00001.png', $outputs[0]['filename']);
        $this->assertEquals('batch_001', $outputs[0]['subfolder']);
    }

    public function test_extract_outputs_returns_video_outputs(): void
    {
        $historyData = [
            'outputs' => [
                '10' => [
                    'videos' => [
                        [
                            'filename' => 'output_video.mp4',
                            'subfolder' => '',
                            'type' => 'output',
                        ],
                    ],
                ],
            ],
        ];

        $outputs = $this->processor->extractOutputs($historyData);

        $this->assertCount(1, $outputs);
        $this->assertEquals('video', $outputs[0]['type']);
        $this->assertEquals('output_video.mp4', $outputs[0]['filename']);
    }

    public function test_extract_outputs_handles_mixed_output_types(): void
    {
        $historyData = [
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
                '10' => [
                    'videos' => [
                        [
                            'filename' => 'output_video.mp4',
                            'subfolder' => '',
                            'type' => 'output',
                        ],
                    ],
                ],
            ],
        ];

        $outputs = $this->processor->extractOutputs($historyData);

        $this->assertCount(2, $outputs);
        $this->assertEquals('image', $outputs[0]['type']);
        $this->assertEquals('video', $outputs[1]['type']);
    }

    public function test_extract_outputs_returns_empty_array_when_no_outputs(): void
    {
        $historyData = [
            'outputs' => [],
        ];

        $outputs = $this->processor->extractOutputs($historyData);

        $this->assertEmpty($outputs);
    }

    public function test_process_workflow_does_not_modify_original(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => 'original prompt',
                ],
            ],
        ];

        $originalWorkflow = $workflow;

        $inputs = [
            'prompt' => 'new prompt',
        ];

        $this->processor->processWorkflow($workflow, $inputs);

        // Original workflow should remain unchanged
        $this->assertEquals('original prompt', $originalWorkflow['1']['inputs']['text']);
    }
}
