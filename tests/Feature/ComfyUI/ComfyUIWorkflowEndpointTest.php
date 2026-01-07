<?php

namespace Tests\Feature\ComfyUI;

use App\Models\SdInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComfyUIWorkflowEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create a ComfyUI instance
        SdInstance::factory()->create([
            'url' => 'http://comfyui.local:8188',
            'type' => 'comfyui',
            'enabled' => true,
        ]);
    }

    public function test_process_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/comfyui/workflow/process', [
            'workflow' => ['1' => ['class_type' => 'KSampler']],
        ]);

        $response->assertUnauthorized();
    }

    public function test_process_endpoint_validates_workflow_is_required(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workflow']);
    }

    public function test_process_endpoint_validates_workflow_is_array(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => 'not-an-array',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workflow']);
    }

    public function test_process_endpoint_rejects_invalid_workflow_structure(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => ['invalid' => 'structure'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'Invalid workflow structure',
        ]);
    }

    public function test_process_endpoint_accepts_valid_workflow(): void
    {
        $this->markTestSkipped('Requires actual ComfyUI instance or more complex mocking');
        
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [
                    'seed' => 12345,
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'success',
            'prompt_id',
            'status',
        ]);
    }

    public function test_process_endpoint_accepts_workflow_with_inputs(): void
    {
        $this->markTestSkipped('Requires actual ComfyUI instance or more complex mocking');
        
        $workflow = [
            '1' => [
                'class_type' => 'CLIPTextEncode',
                'inputs' => [
                    'text' => 'default',
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'prompt' => 'a beautiful landscape',
                    'seed' => 42,
                    'steps' => 30,
                ],
            ]);

        $response->assertStatus(202);
    }

    public function test_process_endpoint_validates_prompt_max_length(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'prompt' => str_repeat('a', 2001),
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.prompt']);
    }

    public function test_process_endpoint_validates_seed_is_integer(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'seed' => 'not-a-number',
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.seed']);
    }

    public function test_process_endpoint_validates_steps_range(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'steps' => 0,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.steps']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'steps' => 151,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.steps']);
    }

    public function test_process_endpoint_validates_cfg_range(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'cfg' => 0.5,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.cfg']);
    }

    public function test_process_endpoint_validates_denoise_range(): void
    {
        $workflow = [
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => [],
            ],
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow' => $workflow,
                'inputs' => [
                    'denoise' => 1.5,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inputs.denoise']);
    }

    public function test_process_endpoint_accepts_json_workflow_file(): void
    {
        $this->markTestSkipped('Requires actual ComfyUI instance or more complex mocking');
        
        $workflowContent = json_encode([
            '1' => [
                'class_type' => 'KSampler',
                'inputs' => ['seed' => 12345],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('workflow.json', $workflowContent);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/process', [
                'workflow_file' => $file,
            ]);

        $response->assertStatus(202);
    }

    public function test_status_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/comfyui/workflow/status/test-prompt-123');

        $response->assertUnauthorized();
    }

    public function test_status_endpoint_returns_processing_for_incomplete_prompt(): void
    {
        $this->markTestSkipped('Requires actual ComfyUI instance or more complex mocking');
        
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/comfyui/workflow/status/test-prompt-123');

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'processing',
        ]);
    }

    public function test_cancel_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/comfyui/workflow/cancel/test-prompt-123');

        $response->assertUnauthorized();
    }

    public function test_cancel_endpoint_cancels_prompt(): void
    {
        $this->markTestSkipped('Requires actual ComfyUI instance or more complex mocking');
        
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/comfyui/workflow/cancel/test-prompt-123');

        $response->assertOk();
        $response->assertJsonFragment([
            'success' => true,
        ]);
    }

    public function test_get_image_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/comfyui/image?filename=test.png');

        $response->assertUnauthorized();
    }

    public function test_get_image_endpoint_validates_filename_required(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/comfyui/image');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['filename']);
    }

    public function test_get_image_endpoint_validates_type_values(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/comfyui/image?filename=test.png&type=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }
}
