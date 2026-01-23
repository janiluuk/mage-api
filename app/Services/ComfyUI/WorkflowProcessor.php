<?php

namespace App\Services\ComfyUI;

use Illuminate\Support\Facades\Log;

class WorkflowProcessor
{
    /**
     * Process a workflow file with inputs
     *
     * @param array $workflow The workflow data
     * @param array $inputs User inputs to fill into the workflow
     * @return array Processed workflow ready for submission
     */
    public function processWorkflow(array $workflow, array $inputs): array
    {
        Log::info('WorkflowProcessor: Processing workflow', [
            'workflow_keys' => array_keys($workflow),
            'inputs_keys' => array_keys($inputs),
        ]);

        // Make a deep copy to avoid modifying the original
        $processedWorkflow = $this->deepCopy($workflow);

        // Process text inputs (prompts)
        if (isset($inputs['prompt'])) {
            $processedWorkflow = $this->injectPrompt($processedWorkflow, $inputs['prompt']);
        }

        if (isset($inputs['negative_prompt'])) {
            $processedWorkflow = $this->injectNegativePrompt($processedWorkflow, $inputs['negative_prompt']);
        }

        // Process image inputs
        if (isset($inputs['image'])) {
            $processedWorkflow = $this->injectImage($processedWorkflow, $inputs['image']);
        }

        // Process seed if provided
        if (isset($inputs['seed'])) {
            $processedWorkflow = $this->injectSeed($processedWorkflow, $inputs['seed']);
        }

        // Process other numeric parameters
        $numericParams = ['steps', 'cfg', 'denoise', 'width', 'height'];
        foreach ($numericParams as $param) {
            if (isset($inputs[$param])) {
                $processedWorkflow = $this->injectNumericParameter($processedWorkflow, $param, $inputs[$param]);
            }
        }

        Log::info('WorkflowProcessor: Workflow processed successfully');

        return $processedWorkflow;
    }

    /**
     * Inject prompt text into workflow
     *
     * @param array $workflow The workflow
     * @param string $prompt The prompt text
     * @return array Modified workflow
     */
    protected function injectPrompt(array $workflow, string $prompt): array
    {
        return $this->findAndReplace($workflow, ['positive', 'text', 'prompt'], $prompt);
    }

    /**
     * Inject negative prompt text into workflow
     *
     * @param array $workflow The workflow
     * @param string $negativePrompt The negative prompt text
     * @return array Modified workflow
     */
    protected function injectNegativePrompt(array $workflow, string $negativePrompt): array
    {
        return $this->findAndReplace($workflow, ['negative', 'negative_prompt'], $negativePrompt);
    }

    /**
     * Inject image reference into workflow
     *
     * @param array $workflow The workflow
     * @param string $imageName The image filename or reference
     * @return array Modified workflow
     */
    protected function injectImage(array $workflow, string $imageName): array
    {
        return $this->findAndReplace($workflow, ['image', 'input_image'], $imageName);
    }

    /**
     * Inject seed into workflow
     *
     * @param array $workflow The workflow
     * @param int $seed The seed value
     * @return array Modified workflow
     */
    protected function injectSeed(array $workflow, int $seed): array
    {
        return $this->findAndReplace($workflow, ['seed'], $seed);
    }

    /**
     * Inject numeric parameter into workflow
     *
     * @param array $workflow The workflow
     * @param string $paramName Parameter name
     * @param mixed $value The value
     * @return array Modified workflow
     */
    protected function injectNumericParameter(array $workflow, string $paramName, $value): array
    {
        return $this->findAndReplace($workflow, [$paramName], $value);
    }

    /**
     * Find and replace values in workflow recursively
     *
     * @param array $workflow The workflow data
     * @param array $searchKeys Keys to search for
     * @param mixed $value Value to inject
     * @return array Modified workflow
     */
    protected function findAndReplace(array $workflow, array $searchKeys, $value): array
    {
        foreach ($workflow as $key => &$node) {
            if (is_array($node)) {
                // Check if this node has 'inputs' which is common in ComfyUI workflows
                if (isset($node['inputs']) && is_array($node['inputs'])) {
                    foreach ($searchKeys as $searchKey) {
                        if (array_key_exists($searchKey, $node['inputs'])) {
                            Log::debug('WorkflowProcessor: Injecting value', [
                                'node_key' => $key,
                                'search_key' => $searchKey,
                                'value_type' => gettype($value),
                            ]);
                            $node['inputs'][$searchKey] = $value;
                        }
                    }
                }

                // Recursively process nested arrays
                $node = $this->findAndReplace($node, $searchKeys, $value);
            }
        }

        return $workflow;
    }

    /**
     * Extract output information from completed workflow
     *
     * @param array $historyData History data from ComfyUI
     * @return array Array of output information
     */
    public function extractOutputs(array $historyData): array
    {
        $outputs = [];

        if (isset($historyData['outputs'])) {
            foreach ($historyData['outputs'] as $nodeId => $nodeOutputs) {
                if (isset($nodeOutputs['images'])) {
                    foreach ($nodeOutputs['images'] as $image) {
                        $outputs[] = [
                            'type' => 'image',
                            'filename' => $image['filename'],
                            'subfolder' => $image['subfolder'] ?? '',
                            'type_name' => $image['type'] ?? 'output',
                        ];
                    }
                }

                if (isset($nodeOutputs['videos'])) {
                    foreach ($nodeOutputs['videos'] as $video) {
                        $outputs[] = [
                            'type' => 'video',
                            'filename' => $video['filename'],
                            'subfolder' => $video['subfolder'] ?? '',
                            'type_name' => $video['type'] ?? 'output',
                        ];
                    }
                }
            }
        }

        Log::info('WorkflowProcessor: Extracted outputs', [
            'output_count' => count($outputs),
        ]);

        return $outputs;
    }

    /**
     * Validate a workflow structure
     *
     * @param array $workflow The workflow to validate
     * @return bool True if valid
     */
    public function validateWorkflow(array $workflow): bool
    {
        // Basic validation - workflow should be a non-empty array with node structure
        if (empty($workflow)) {
            Log::warning('WorkflowProcessor: Workflow is empty');
            return false;
        }

        // Check if workflow has at least one node
        $hasNodes = false;
        foreach ($workflow as $key => $node) {
            if (is_array($node) && isset($node['class_type'])) {
                $hasNodes = true;
                break;
            }
        }

        if (!$hasNodes) {
            Log::warning('WorkflowProcessor: Workflow has no valid nodes');
            return false;
        }

        Log::info('WorkflowProcessor: Workflow validation passed');
        return true;
    }

    /**
     * Create a deep copy of an array
     *
     * @param array $array The array to copy
     * @return array Deep copied array
     */
    protected function deepCopy(array $array): array
    {
        return unserialize(serialize($array));
    }
}
