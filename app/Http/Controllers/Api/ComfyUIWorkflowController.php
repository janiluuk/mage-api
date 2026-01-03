<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComfyUI\ComfyUIClient;
use App\Services\ComfyUI\WorkflowProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComfyUIWorkflowController extends Controller
{
    protected ComfyUIClient $client;
    protected WorkflowProcessor $processor;

    public function __construct(ComfyUIClient $client, WorkflowProcessor $processor)
    {
        $this->client = $client;
        $this->processor = $processor;
    }

    /**
     * Process a ComfyUI workflow
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function process(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workflow' => 'required|array',
            'workflow_file' => 'sometimes|file|mimes:json',
            'inputs' => 'sometimes|array',
            'inputs.prompt' => 'sometimes|string|max:2000',
            'inputs.negative_prompt' => 'sometimes|string|max:2000',
            'inputs.image' => 'sometimes|file|image|max:20480', // 20MB max
            'inputs.seed' => 'sometimes|integer|min:-1',
            'inputs.steps' => 'sometimes|integer|min:1|max:150',
            'inputs.cfg' => 'sometimes|numeric|min:1|max:30',
            'inputs.denoise' => 'sometimes|numeric|min:0|max:1',
            'inputs.width' => 'sometimes|integer|min:64|max:2048',
            'inputs.height' => 'sometimes|integer|min:64|max:2048',
            'wait_for_completion' => 'sometimes|boolean',
            'max_wait_seconds' => 'sometimes|integer|min:10|max:600',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Load workflow from file or request
            $workflow = $this->loadWorkflow($request);

            // Validate workflow structure
            if (!$this->processor->validateWorkflow($workflow)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid workflow structure',
                ], 422);
            }

            // Prepare inputs
            $inputs = $this->prepareInputs($request);

            // Process workflow with inputs
            $processedWorkflow = $this->processor->processWorkflow($workflow, $inputs);

            // Queue the workflow
            $queueResponse = $this->client->queuePrompt($processedWorkflow);

            if (!isset($queueResponse['prompt_id'])) {
                throw new \Exception('Failed to queue workflow: Invalid response from ComfyUI');
            }

            $promptId = $queueResponse['prompt_id'];

            Log::info('ComfyUI workflow queued', [
                'prompt_id' => $promptId,
                'user_id' => auth()->id(),
            ]);

            // If wait_for_completion is true, poll until complete
            $waitForCompletion = $request->boolean('wait_for_completion', false);
            $maxWaitSeconds = $request->integer('max_wait_seconds', 300);

            if ($waitForCompletion) {
                $historyData = $this->client->waitForPrompt($promptId, $maxWaitSeconds);

                if ($historyData === null) {
                    return response()->json([
                        'success' => false,
                        'prompt_id' => $promptId,
                        'error' => 'Workflow processing timeout',
                        'message' => 'The workflow is still processing. Use the prompt_id to check status later.',
                    ], 202);
                }

                // Extract outputs
                $outputs = $this->processor->extractOutputs($historyData);

                return response()->json([
                    'success' => true,
                    'prompt_id' => $promptId,
                    'status' => 'completed',
                    'outputs' => $outputs,
                    'history' => $historyData,
                ], 200);
            }

            // Return immediately with prompt_id
            return response()->json([
                'success' => true,
                'prompt_id' => $promptId,
                'status' => 'queued',
                'message' => 'Workflow queued successfully. Use the prompt_id to check status.',
            ], 202);

        } catch (\Exception $e) {
            Log::error('ComfyUI workflow processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow status and results
     *
     * @param Request $request
     * @param string $promptId
     * @return JsonResponse
     */
    public function status(Request $request, string $promptId): JsonResponse
    {
        try {
            $history = $this->client->getHistory($promptId);

            if (!isset($history[$promptId])) {
                return response()->json([
                    'success' => true,
                    'prompt_id' => $promptId,
                    'status' => 'processing',
                    'message' => 'Workflow is still processing',
                ], 200);
            }

            $historyData = $history[$promptId];
            $outputs = $this->processor->extractOutputs($historyData);

            return response()->json([
                'success' => true,
                'prompt_id' => $promptId,
                'status' => 'completed',
                'outputs' => $outputs,
                'history' => $historyData,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ComfyUI status check error', [
                'prompt_id' => $promptId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a workflow
     *
     * @param Request $request
     * @param string $promptId
     * @return JsonResponse
     */
    public function cancel(Request $request, string $promptId): JsonResponse
    {
        try {
            $response = $this->client->cancelPrompt($promptId);

            Log::info('ComfyUI workflow cancelled', [
                'prompt_id' => $promptId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'prompt_id' => $promptId,
                'message' => 'Workflow cancelled successfully',
                'response' => $response,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ComfyUI cancel error', [
                'prompt_id' => $promptId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get output image from ComfyUI
     *
     * @param Request $request
     * @return mixed
     */
    public function getImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'subfolder' => 'sometimes|string',
            'type' => 'sometimes|string|in:output,input,temp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $filename = $request->input('filename');
            $subfolder = $request->input('subfolder', '');
            $type = $request->input('type', 'output');

            $imageData = $this->client->getImage($filename, $subfolder, $type);

            return response($imageData)
                ->header('Content-Type', 'image/png');

        } catch (\Exception $e) {
            Log::error('ComfyUI get image error', [
                'filename' => $request->input('filename'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load workflow from request
     *
     * @param Request $request
     * @return array
     */
    protected function loadWorkflow(Request $request): array
    {
        // If workflow_file is uploaded, parse it
        if ($request->hasFile('workflow_file')) {
            $file = $request->file('workflow_file');
            $contents = file_get_contents($file->getRealPath());
            return json_decode($contents, true);
        }

        // Otherwise use workflow from request body
        return $request->input('workflow', []);
    }

    /**
     * Prepare inputs from request
     *
     * @param Request $request
     * @return array
     */
    protected function prepareInputs(Request $request): array
    {
        $inputs = $request->input('inputs', []);

        // Handle image upload
        if ($request->hasFile('inputs.image')) {
            $imageFile = $request->file('inputs.image');
            
            // Save temporarily
            $tempPath = $imageFile->getRealPath();
            
            // Upload to ComfyUI
            $uploadResponse = $this->client->uploadImage($tempPath);
            
            // Store the uploaded filename in inputs
            if (isset($uploadResponse['name'])) {
                $inputs['image'] = $uploadResponse['name'];
            }
        }

        return $inputs;
    }
}
