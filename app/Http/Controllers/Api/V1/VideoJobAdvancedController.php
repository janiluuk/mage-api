<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Videojob;
use App\Models\ModelFile;
use App\Services\VideoJobs\VideoJobVariantService;
use App\Services\VideoJobs\VideoPostProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for advanced video job operations: variants and post-processing
 */
class VideoJobAdvancedController extends Controller
{
    public function __construct(
        private readonly VideoJobVariantService $variantService,
        private readonly VideoPostProcessor $postProcessor
    ) {
    }

    /**
     * Create multiple variants of a video job with different models.
     * POST /api/v1/video-jobs/{id}/variants
     */
    public function createVariants(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'model_ids' => 'required|array|min:1|max:10',
            'model_ids.*' => 'required|integer|exists:model_files,id',
            'preview_frames' => 'nullable|integer|min:0|max:100',
            'auto_process' => 'nullable|boolean',
        ]);

        $baseJob = Videojob::findOrFail($id);

        // Check authorization
        if ($baseJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $previewFrames = $validated['preview_frames'] ?? 0;
            $autoProcess = $validated['auto_process'] ?? true;

            // Create variants
            $variants = $this->variantService->createVariants(
                $baseJob,
                $validated['model_ids'],
                $previewFrames
            );

            // Optionally start processing immediately
            if ($autoProcess) {
                $this->variantService->processVariantsInParallel($baseJob, $previewFrames);
            }

            Log::info('Created video job variants', [
                'base_job_id' => $baseJob->id,
                'variant_count' => count($variants),
                'auto_process' => $autoProcess,
            ]);

            return response()->json([
                'message' => 'Variants created successfully',
                'base_job_id' => $baseJob->id,
                'variants' => array_map(function ($variant) {
                    return [
                        'variant_id' => $variant['variant']->id,
                        'job_id' => $variant['job']->id,
                        'model_id' => $variant['variant']->model_id,
                        'variant_name' => $variant['variant']->variant_name,
                        'status' => $variant['job']->status,
                    ];
                }, $variants),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating variants', [
                'error' => $e->getMessage(),
                'base_job_id' => $baseJob->id,
            ]);

            return response()->json([
                'message' => 'Failed to create variants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status of all variants for a video job.
     * GET /api/v1/video-jobs/{id}/variants
     */
    public function getVariantsStatus(Request $request, int $id): JsonResponse
    {
        $baseJob = Videojob::findOrFail($id);

        // Check authorization
        if ($baseJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $variants = $this->variantService->getVariantsStatus($baseJob);

        return response()->json([
            'base_job_id' => $baseJob->id,
            'variants' => $variants,
        ]);
    }

    /**
     * Process all pending variants.
     * POST /api/v1/video-jobs/{id}/variants/process
     */
    public function processVariants(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'preview_frames' => 'nullable|integer|min:0|max:100',
        ]);

        $baseJob = Videojob::findOrFail($id);

        // Check authorization
        if ($baseJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $previewFrames = $validated['preview_frames'] ?? 0;
            
            $this->variantService->processVariantsInParallel($baseJob, $previewFrames);

            return response()->json([
                'message' => 'Variants processing started',
                'base_job_id' => $baseJob->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing variants', [
                'error' => $e->getMessage(),
                'base_job_id' => $baseJob->id,
            ]);

            return response()->json([
                'message' => 'Failed to process variants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply post-processing effects to a finished video.
     * POST /api/v1/video-jobs/{id}/post-process
     */
    public function postProcess(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'effects' => 'required|array|min:1',
            'effects.*.name' => 'required|string|in:' . implode(',', [
                VideoPostProcessor::EFFECT_FADE_IN,
                VideoPostProcessor::EFFECT_FADE_OUT,
                VideoPostProcessor::EFFECT_BRIGHTNESS,
                VideoPostProcessor::EFFECT_CONTRAST,
                VideoPostProcessor::EFFECT_SATURATION,
                VideoPostProcessor::EFFECT_SHARPEN,
                VideoPostProcessor::EFFECT_BLUR,
                VideoPostProcessor::EFFECT_DENOISE,
                VideoPostProcessor::EFFECT_SCALE,
                VideoPostProcessor::EFFECT_CROP,
                VideoPostProcessor::EFFECT_ROTATE,
            ]),
            'effects.*.params' => 'nullable|array',
        ]);

        $videoJob = Videojob::findOrFail($id);

        // Check authorization
        if ($videoJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Transform effects array to the format expected by postProcessor
            $effects = [];
            foreach ($validated['effects'] as $effect) {
                $effects[$effect['name']] = $effect['params'] ?? [];
            }

            // Apply effects - service will validate status
            $success = $this->postProcessor->applyEffects($videoJob, $effects);

            if ($success) {
                Log::info('Post-processing completed', [
                    'job_id' => $videoJob->id,
                    'effects' => array_keys($effects),
                ]);

                return response()->json([
                    'message' => 'Post-processing completed successfully',
                    'video_job_id' => $videoJob->id,
                    'video_url' => $videoJob->url,
                ]);
            } else {
                return response()->json([
                    'message' => 'Post-processing failed - video must be in finished status'
                ], 422);
            }

        } catch (\Exception $e) {
            Log::error('Error during post-processing', [
                'error' => $e->getMessage(),
                'video_job_id' => $videoJob->id,
            ]);

            return response()->json([
                'message' => 'Failed to post-process video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available post-processing effects.
     * GET /api/v1/video-jobs/post-process/effects
     */
    public function getAvailableEffects(): JsonResponse
    {
        return response()->json([
            'effects' => $this->postProcessor->getAvailableEffects(),
        ]);
    }

    /**
     * Extend an existing job with same parameters.
     * POST /api/v1/video-jobs/{id}/extend-with-params
     */
    public function extendWithParams(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'override_params' => 'nullable|array',
            'override_params.prompt' => 'nullable|string|max:2000',
            'override_params.negative_prompt' => 'nullable|string|max:2000',
            'override_params.seed' => 'nullable|integer',
            'override_params.denoising' => 'nullable|numeric|between:0,1',
            'override_params.model_id' => 'nullable|integer|exists:model_files,id',
        ]);

        $baseJob = Videojob::findOrFail($id);

        // Check authorization
        if ($baseJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if base job is completed
        if ($baseJob->status !== Videojob::STATUS_FINISHED) {
            return response()->json([
                'message' => 'Base video job must be completed before extension'
            ], 422);
        }

        try {
            // Create new job with same parameters
            $extendedJob = new Videojob();
            
            // Copy parameters from base job
            $extendedJob->user_id = $request->user()->id;
            $extendedJob->generator = $baseJob->generator;
            $extendedJob->model_id = $validated['override_params']['model_id'] ?? $baseJob->model_id;
            $extendedJob->filename = 'extended_' . $baseJob->id . '_' . uniqid() . '.mp4';
            $extendedJob->original_filename = $baseJob->original_filename;
            $extendedJob->outfile = 'out_extended_' . $baseJob->id . '_' . uniqid() . '.mp4';
            $extendedJob->prompt = $validated['override_params']['prompt'] ?? $baseJob->prompt;
            $extendedJob->negative_prompt = $validated['override_params']['negative_prompt'] ?? $baseJob->negative_prompt;
            $extendedJob->seed = $validated['override_params']['seed'] ?? $baseJob->seed;
            $extendedJob->denoising = $validated['override_params']['denoising'] ?? $baseJob->denoising;
            $extendedJob->width = $baseJob->width;
            $extendedJob->height = $baseJob->height;
            $extendedJob->fps = $baseJob->fps;
            $extendedJob->frame_count = $baseJob->frame_count;
            $extendedJob->length = $baseJob->length;
            $extendedJob->cfg_scale = $baseJob->cfg_scale;
            $extendedJob->controlnet = $baseJob->controlnet;
            $extendedJob->status = Videojob::STATUS_PENDING;
            
            // Store metadata about extension
            $extensionMetadata = [
                'extended_from_job_id' => $baseJob->id,
                'is_extension' => true,
                'overridden_params' => $validated['override_params'] ?? [],
            ];
            $extendedJob->generation_parameters = json_encode($extensionMetadata);
            
            $extendedJob->save();

            Log::info('Created extended video job', [
                'extended_job_id' => $extendedJob->id,
                'base_job_id' => $baseJob->id,
            ]);

            return response()->json([
                'message' => 'Extended job created successfully',
                'job_id' => $extendedJob->id,
                'base_job_id' => $baseJob->id,
                'status' => $extendedJob->status,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error extending video job', [
                'error' => $e->getMessage(),
                'base_job_id' => $baseJob->id,
            ]);

            return response()->json([
                'message' => 'Failed to extend job: ' . $e->getMessage()
            ], 500);
        }
    }
}
