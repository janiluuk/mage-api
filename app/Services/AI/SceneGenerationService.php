<?php

namespace App\Services\AI;

use App\Models\Shot;
use App\Services\ComfyUI\ComfyWebSocketClient;
use App\Services\DeforumProcessingService;
use App\Services\LoadBalancerService;
use App\Models\GeneratorInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating scenes for shots
 * Supports ComfyUI workflows (ltx-2-i2v, wan2.2-t2v) and Deforum
 */
class SceneGenerationService
{
    private ComfyWebSocketClient $comfyClient;
    private DeforumProcessingService $deforumService;
    private LoadBalancerService $loadBalancer;

    public function __construct(
        ComfyWebSocketClient $comfyClient,
        DeforumProcessingService $deforumService,
        LoadBalancerService $loadBalancer
    ) {
        $this->comfyClient = $comfyClient;
        $this->deforumService = $deforumService;
        $this->loadBalancer = $loadBalancer;
    }

    /**
     * Generate scene for a shot
     * 
     * @param Shot $shot The shot to generate scene for
     * @param string $prompt Scene description prompt
     * @param string $generator 'comfyui' or 'deforum'
     * @param string $workflow 'ltx-2-i2v' or 'wan2.2-t2v' (for ComfyUI)
     * @param bool $generateReferenceShots Generate first and last shot as reference
     * @param array $options Additional generation options
     */
    public function generateScene(
        Shot $shot,
        string $prompt,
        string $generator = 'comfyui',
        string $workflow = 'ltx-2-i2v',
        bool $generateReferenceShots = false,
        array $options = []
    ): array {
        Log::info('Generating scene for shot', [
            'shot_id' => $shot->id,
            'generator' => $generator,
            'workflow' => $workflow,
            'reference_shots' => $generateReferenceShots,
        ]);

        $sceneData = [
            'prompt' => $prompt,
            'generator' => $generator,
            'workflow' => $workflow,
            'generated_at' => now()->toISOString(),
            'options' => $options,
        ];

        if ($generator === 'comfyui') {
            $sceneData = array_merge($sceneData, $this->generateWithComfyUI(
                $shot,
                $prompt,
                $workflow,
                $generateReferenceShots,
                $options
            ));
        } else {
            $sceneData = array_merge($sceneData, $this->generateWithDeforum(
                $shot,
                $prompt,
                $generateReferenceShots,
                $options
            ));
        }

        return $sceneData;
    }

    /**
     * Generate scene using ComfyUI
     */
    private function generateWithComfyUI(
        Shot $shot,
        string $prompt,
        string $workflow,
        bool $generateReferenceShots,
        array $options
    ): array {
        // Get available ComfyUI instance using LoadBalancer
        $instance = $this->loadBalancer->selectInstance('comfyui', 'least_loaded');
        if (!$instance) {
            throw new \Exception('No available ComfyUI instances');
        }

        Log::info('Using ComfyUI instance', [
            'instance_id' => $instance->id,
            'instance_name' => $instance->name,
            'workflow' => $workflow,
        ]);

        $sceneData = [
            'comfyui_instance_id' => $instance->id,
            'comfyui_instance_name' => $instance->name,
        ];

        // Load workflow based on type
        $workflowData = $this->loadComfyUIWorkflow($workflow, $prompt, $options);

        // Submit job to ComfyUI
        $clientId = $this->submitComfyUIJob($instance->url, $workflowData);

        // Wait for result
        $result = $this->comfyClient->waitForResult($clientId);

        $sceneData['video_url'] = $result['url'] ?? null;
        $sceneData['video_path'] = $result['filename'] ?? null;
        $sceneData['comfyui_job_id'] = $clientId;

        // Generate reference shots if requested
        if ($generateReferenceShots) {
            $sceneData['reference_shots'] = $this->generateReferenceShots(
                $shot,
                $prompt,
                $workflow,
                $instance,
                $options
            );
        }

        return $sceneData;
    }

    /**
     * Generate scene using Deforum
     */
    private function generateWithDeforum(
        Shot $shot,
        string $prompt,
        bool $generateReferenceShots,
        array $options
    ): array {
        // Get available SD-Forge instance using LoadBalancer
        $instance = $this->loadBalancer->selectInstance('stable_diffusion_forge', 'least_loaded');
        if (!$instance) {
            throw new \Exception('No available SD-Forge instances');
        }

        Log::info('Using Deforum instance', [
            'instance_id' => $instance->id,
            'instance_name' => $instance->name,
        ]);

        // Create a temporary video job for Deforum processing
        $videoJob = $this->createVideoJobForDeforum($shot, $prompt, $options);

        // Process with Deforum
        $this->deforumService->startProcess($videoJob);

        $sceneData = [
            'deforum_instance_id' => $instance->id,
            'deforum_instance_name' => $instance->name,
            'video_job_id' => $videoJob->id,
        ];

        // Generate reference shots if requested
        if ($generateReferenceShots) {
            $sceneData['reference_shots'] = $this->generateDeforumReferenceShots(
                $shot,
                $prompt,
                $instance,
                $options
            );
        }

        return $sceneData;
    }

    /**
     * Load ComfyUI workflow template
     */
    private function loadComfyUIWorkflow(string $workflow, string $prompt, array $options): array
    {
        $workflowPath = storage_path("app/comfy/workflows/{$workflow}.json");
        
        if (!file_exists($workflowPath)) {
            throw new \Exception("Workflow file not found: {$workflowPath}");
        }

        $workflowData = json_decode(file_get_contents($workflowPath), true);
        
        if (!$workflowData) {
            throw new \Exception("Invalid workflow file: {$workflowPath}");
        }

        // Inject prompt and options into workflow
        $workflowData = $this->injectPromptIntoWorkflow($workflowData, $prompt, $options);

        return $workflowData;
    }

    /**
     * Inject prompt and options into ComfyUI workflow
     */
    private function injectPromptIntoWorkflow(array $workflow, string $prompt, array $options): array
    {
        // Find text input nodes and update them with prompt
        foreach ($workflow as $nodeId => &$node) {
            if (isset($node['class_type']) && $node['class_type'] === 'CLIPTextEncode') {
                if (isset($node['inputs']['text'])) {
                    $node['inputs']['text'] = $prompt;
                }
            }
            
            // Inject other options if needed
            if (isset($options['seed'])) {
                $node['inputs']['seed'] = $options['seed'] ?? null;
            }
            if (isset($options['steps'])) {
                $node['inputs']['steps'] = $options['steps'] ?? 20;
            }
            if (isset($options['cfg_scale'])) {
                $node['inputs']['cfg_scale'] = $options['cfg_scale'] ?? 7.0;
            }
        }

        return $workflow;
    }

    /**
     * Submit job to ComfyUI
     */
    private function submitComfyUIJob(string $baseUrl, array $workflowData): string
    {
        $client = new \GuzzleHttp\Client(['timeout' => 30]);
        
        $response = $client->post("http://{$baseUrl}/prompt", [
            'json' => [
                'prompt' => $workflowData,
                'client_id' => 'mage-api-' . uniqid(),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to submit ComfyUI job: " . $response->getBody());
        }

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['prompt_id'] ?? uniqid();
    }

    /**
     * Generate first and last reference shots
     */
    private function generateReferenceShots(
        Shot $shot,
        string $prompt,
        string $workflow,
        GeneratorInstance $instance,
        array $options
    ): array {
        $referenceShots = [];

        // Generate first shot
        $firstPrompt = $this->buildReferencePrompt($prompt, 'first');
        $firstWorkflow = $this->loadComfyUIWorkflow($workflow, $firstPrompt, $options);
        $firstClientId = $this->submitComfyUIJob($instance->url, $firstWorkflow);
        $firstResult = $this->comfyClient->waitForResult($firstClientId);

        $referenceShots['first'] = [
            'prompt' => $firstPrompt,
            'url' => $firstResult['url'] ?? null,
            'path' => $firstResult['filename'] ?? null,
        ];

        // Generate last shot
        $lastPrompt = $this->buildReferencePrompt($prompt, 'last');
        $lastWorkflow = $this->loadComfyUIWorkflow($workflow, $lastPrompt, $options);
        $lastClientId = $this->submitComfyUIJob($instance->url, $lastWorkflow);
        $lastResult = $this->comfyClient->waitForResult($lastClientId);

        $referenceShots['last'] = [
            'prompt' => $lastPrompt,
            'url' => $lastResult['url'] ?? null,
            'path' => $lastResult['filename'] ?? null,
        ];

        return $referenceShots;
    }

    /**
     * Build reference shot prompt
     */
    private function buildReferencePrompt(string $basePrompt, string $position): string
    {
        return match($position) {
            'first' => "Opening shot: {$basePrompt}. Show the initial scene setup, establishing the setting and mood.",
            'last' => "Closing shot: {$basePrompt}. Show the final scene, resolution, or transition out.",
            default => $basePrompt,
        };
    }

    /**
     * Generate Deforum reference shots
     */
    private function generateDeforumReferenceShots(
        Shot $shot,
        string $prompt,
        GeneratorInstance $instance,
        array $options
    ): array {
        // Similar to ComfyUI but using Deforum
        // This would create separate video jobs for first and last shots
        return [
            'first' => ['note' => 'First shot generation queued'],
            'last' => ['note' => 'Last shot generation queued'],
        ];
    }

    /**
     * Create video job for Deforum processing
     */
    private function createVideoJobForDeforum(Shot $shot, string $prompt, array $options)
    {
        // This would create a Videojob model instance
        // Implementation depends on your Videojob model structure
        // For now, return a placeholder
        return (object) [
            'id' => uniqid(),
            'prompt' => $prompt,
            'options' => $options,
        ];
    }
}

