<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Videojob;
use App\Services\VideoProcessingService;
use App\Services\LoadBalancerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VideojobController extends Controller
{
    private VideoProcessingService $videoProcessingService;
    private LoadBalancerService $loadBalancer;

    public function __construct(VideoProcessingService $videoProcessingService, LoadBalancerService $loadBalancer)
    {
        $this->videoProcessingService = $videoProcessingService;
        $this->loadBalancer = $loadBalancer;
    }

    public function upload(Request $request): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'attachment' => 'required|mimes:webm,mp4,mov,ogg,qt,gif,jpg,jpeg,png,webp|max:200000',
            'soundtrack' => 'nullable|file|mimes:mp3,aac,wav|max:51200',
            'soundtrack_start_seconds' => 'nullable|numeric|min:0',
            'soundtrack_end_seconds' => 'nullable|numeric|gt:soundtrack_start_seconds',
            'type' => 'required|in:vid2vid,deforum',
        ]);

        return $validated['type'] === 'deforum'
            ? $this->handleDeforum($request)
            : $this->handleVid2Vid($request);
    }

    private function handleVid2Vid(Request $request): JsonResponse
    {
        $fileInfo = $this->persistUploadedFile($request);

        $videoJob = new Videojob();
        $videoJob->filename = $fileInfo['filename'];
        $videoJob->original_filename = $fileInfo['originalName'];
        $videoJob->outfile = $fileInfo['outfile'];
        $videoJob->model_id = 1;
        $videoJob->cfg_scale = 7;
        $videoJob->mimetype = $fileInfo['mimeType'];
        $videoJob->seed = -1;
        $videoJob->user_id = auth('api')->id();
        $videoJob->prompt = '';
        $videoJob->negative_prompt = '';
        $videoJob->queued_at = null;
        $videoJob->status = 'pending';

        $this->attachSoundtrack($videoJob, $request);

        $videoJob = $this->videoProcessingService->parseJob($videoJob, $fileInfo['publicPath']);
        $this->persistMedia($videoJob, $fileInfo['path']);

        return response()->json([
            'url' => $videoJob->original_url,
            'status' => $videoJob->status,
            'id' => $videoJob->id,
        ]);
    }

    private function handleDeforum(Request $request): JsonResponse
    {
        $fileInfo = $this->persistUploadedFile($request);

        $videoJob = new Videojob();
        $videoJob->filename = $fileInfo['filename'];
        $videoJob->original_filename = $fileInfo['originalName'];
        $videoJob->generator = 'deforum';
        $videoJob->outfile = $fileInfo['outfile'];
        $videoJob->model_id = 1;
        $videoJob->mimetype = $fileInfo['mimeType'];
        $videoJob->queued_at = null;
        $videoJob->seed = -1;
        $videoJob->frame_count = 90;
        $videoJob->user_id = auth('api')->id();
        $videoJob->prompt = '';
        $videoJob->negative_prompt = '';
        $videoJob->status = 'pending';

        $this->attachSoundtrack($videoJob, $request);

        $videoJob->save();
        $this->persistMedia($videoJob, $fileInfo['path']);

        return response()->json([
            'url' => $videoJob->original_url,
            'status' => $videoJob->status,
            'id' => $videoJob->id,
        ]);
    }

    
    public function generate(Request $request): JsonResponse
    {
        // Validate common parameters first
        $request->validate([
            'videoId' => 'required|integer|exists:video_jobs,id',
            'type' => 'required|in:vid2vid,deforum',
            'variants' => 'nullable|integer|min:1|max:10',
        ]);

        $type = $request->input('type');

        return $type === 'deforum'
            ? $this->generateDeforum($request)
            : $this->generateVid2Vid($request);
    }


private function generateDeforum(Request $request): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $request->validate([
            'modelId' => 'required|integer',
            'prompt' => 'required|string',
            'frameCount' => 'numeric|between:1,20',
            'preset' => 'required|string',
            'length' => 'numeric|between:1,20',
            'extendFromJobId' => 'nullable|integer|exists:video_jobs,id',
            'soundtrack_start_seconds' => 'nullable|numeric|min:0',
            'soundtrack_end_seconds' => 'nullable|numeric|gt:soundtrack_start_seconds',
        ]);

        $variants = (int) $request->input('variants', 1);
        $frameCount = $request->input('frameCount', 1);
        $videoJob = Videojob::findOrFail($request->input('videoId'));
        $extendFromJobId = $request->input('extendFromJobId');

        if ($response = $this->assertOwner($videoJob)) {
            return $response;
        }

        // Validate base job if extending
        if ($extendFromJobId) {
            $baseJob = Videojob::findOrFail($extendFromJobId);

            if ($baseJob->generator !== 'deforum') {
                return response()->json(['message' => 'Only deforum jobs can be extended'], 422);
            }

            if ($response = $this->assertOwner($baseJob)) {
                return $response;
            }
        }

        // Handle multiple variants
        if ($variants > 1) {
            return $this->generateDeforumVariants($request, $videoJob, $variants, $frameCount, $extendFromJobId);
        }

        // Single variant processing
        return $this->processDeforumJob($request, $videoJob, $frameCount, $extendFromJobId);
    }

    private function processDeforumJob(Request $request, Videojob $videoJob, int $frameCount, ?int $extendFromJobId): JsonResponse
    {
        if ($extendFromJobId) {
            $baseJob = Videojob::findOrFail($extendFromJobId);
            $persistedParameters = json_decode((string) $baseJob->generation_parameters, true) ?? [];

            // When extending, inherit model_id from base job (not overridable)
            $videoJob->model_id = $persistedParameters['model_id'] ?? $baseJob->model_id;
            
            // These parameters can be overridden by request
            $videoJob->prompt = $request->input('prompt', $persistedParameters['prompts']['positive'] ?? $baseJob->prompt);
            $videoJob->negative_prompt = $request->input('negative_prompt', $persistedParameters['prompts']['negative'] ?? $baseJob->negative_prompt);
            $videoJob->length = $request->input('length', $persistedParameters['length'] ?? $baseJob->length);
            
            // These parameters come from base job only
            $videoJob->seed = $request->input('seed', $persistedParameters['seed'] ?? $baseJob->seed);
            $videoJob->denoising = $request->input('denoising', $persistedParameters['denoising'] ?? $baseJob->denoising);
            $videoJob->fps = $persistedParameters['fps'] ?? $baseJob->fps;
            $videoJob->frame_count = $persistedParameters['frame_count'] ?? $baseJob->frame_count;
            $videoJob->width = $baseJob->width;
            $videoJob->height = $baseJob->height;

            // Set initial image to last frame of base job
            $this->setInitImageFromBaseJob($videoJob, $baseJob);
        } else {
            $videoJob->model_id = $request->input('modelId', $videoJob->model_id);
            $videoJob->prompt = trim((string) $request->input('prompt', $videoJob->prompt));
            $videoJob->negative_prompt = trim((string) $request->input('negative_prompt', $videoJob->negative_prompt));
            $videoJob->length = $request->input('length', $videoJob->length ?? 4);
            $videoJob->denoising = $request->input('denoising', $videoJob->denoising);
        }

        $this->applySoundtrackWindow($videoJob, $request);

        $videoJob->status = 'processing';
        $videoJob->progress = 5;
        $seed = $this->normalizeSeed((int) $request->input('seed', $videoJob->seed ?? -1));

        $videoJob->fps = $videoJob->fps ?? 24;
        $videoJob->generator = 'deforum';
        $videoJob->seed = $seed;
        $videoJob->frame_count = round($videoJob->length * $videoJob->fps);
        $videoJob->job_time = 3;
        $videoJob->estimated_time_left = ($videoJob->frame_count * 6) + 6;
        $videoJob->queued_at = Carbon::now();
        $videoJob->save();

        // Assign instance for load balancing
        $instanceType = 'stable_diffusion_forge'; // Deforum uses SD instances
        $instance = $this->loadBalancer->selectInstance($instanceType);
        if ($instance) {
            $this->loadBalancer->assignJobToInstance($videoJob->id, $instance);
        }

        $queueName = $frameCount > 1
            ? $this->resolveQueueName('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->resolveQueueName('HIGH_PRIORITY_QUEUE', 'high');
        Log::info("Dispatching job with framecount {$frameCount} to queue {$queueName}");
        ProcessDeforumJob::dispatch($videoJob, $frameCount, $extendFromJobId)->onQueue($queueName);

        return response()->json([
            'id' => $videoJob->id,
            'status' => $videoJob->status,
            'seed' => $videoJob->seed,
            'job_time' => $videoJob->job_time,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'width' => $videoJob->width,
            'height' => $videoJob->height,
            'length' => $videoJob->length,
            'fps' => $videoJob->fps,
        ]);
    }

    private function generateDeforumVariants(Request $request, Videojob $originalJob, int $variants, int $frameCount, ?int $extendFromJobId): JsonResponse
    {
        $jobs = [];
        $queueName = $frameCount > 1
            ? $this->resolveQueueName('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->resolveQueueName('HIGH_PRIORITY_QUEUE', 'high');

        for ($i = 0; $i < $variants; $i++) {
            // Clone the original job for each variant
            if ($i === 0) {
                // Use the original job for the first variant
                $variantJob = $originalJob;
            } else {
                // Create a new job by replicating the original
                $variantJob = $originalJob->replicate();
                $variantJob->save();
                
                // Copy media files to the new job if needed
                foreach ($originalJob->getMedia('original') as $media) {
                    $variantJob->addMedia($media->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('original');
                }
            }

            // Generate unique seed for each variant
            $seed = $this->normalizeSeed(-1);

            // Set job parameters
            if ($extendFromJobId) {
                $baseJob = Videojob::findOrFail($extendFromJobId);
                $persistedParameters = json_decode((string) $baseJob->generation_parameters, true) ?? [];

                $variantJob->model_id = $persistedParameters['model_id'] ?? $baseJob->model_id;
                $variantJob->prompt = $request->input('prompt', $persistedParameters['prompts']['positive'] ?? $baseJob->prompt);
                $variantJob->negative_prompt = $request->input('negative_prompt', $persistedParameters['prompts']['negative'] ?? $baseJob->negative_prompt);
                $variantJob->length = $request->input('length', $persistedParameters['length'] ?? $baseJob->length);
                $variantJob->denoising = $request->input('denoising', $persistedParameters['denoising'] ?? $baseJob->denoising);
                $variantJob->fps = $persistedParameters['fps'] ?? $baseJob->fps;
                $variantJob->frame_count = $persistedParameters['frame_count'] ?? $baseJob->frame_count;
                $variantJob->width = $baseJob->width;
                $variantJob->height = $baseJob->height;

                if ($i > 0) {
                    $this->setInitImageFromBaseJob($variantJob, $baseJob);
                }
            } else {
                $variantJob->model_id = $request->input('modelId', $variantJob->model_id);
                $variantJob->prompt = trim((string) $request->input('prompt', $variantJob->prompt));
                $variantJob->negative_prompt = trim((string) $request->input('negative_prompt', $variantJob->negative_prompt));
                $variantJob->length = $request->input('length', $variantJob->length ?? 4);
                $variantJob->denoising = $request->input('denoising', $variantJob->denoising);
            }

            $variantJob->status = 'processing';
            $variantJob->progress = 5;
            $variantJob->fps = $variantJob->fps ?? 24;
            $variantJob->generator = 'deforum';
            $variantJob->seed = $seed;
            $variantJob->frame_count = round($variantJob->length * $variantJob->fps);
            $variantJob->job_time = 3;
            $variantJob->estimated_time_left = ($variantJob->frame_count * 6) + 6;
            $variantJob->queued_at = Carbon::now();
            $variantJob->save();

            // Assign instance for load balancing
            $instanceType = 'stable_diffusion_forge';
            $instance = $this->loadBalancer->selectInstance($instanceType);
            if ($instance) {
                $this->loadBalancer->assignJobToInstance($variantJob->id, $instance);
            }

            // Dispatch the job
            ProcessDeforumJob::dispatch($variantJob, $frameCount, $extendFromJobId)->onQueue($queueName);

            Log::info("Dispatched deforum variant job {$i+1}/{$variants}", [
                'job_id' => $variantJob->id,
                'seed' => $seed,
                'queue' => $queueName
            ]);

            $jobs[] = [
                'id' => $variantJob->id,
                'status' => $variantJob->status,
                'seed' => $variantJob->seed,
                'job_time' => $variantJob->job_time,
                'progress' => $variantJob->progress,
                'estimated_time_left' => $variantJob->estimated_time_left,
                'width' => $variantJob->width,
                'height' => $variantJob->height,
                'length' => $variantJob->length,
                'fps' => $variantJob->fps,
            ];
        }

        return response()->json([
            'variants' => $jobs,
            'count' => count($jobs),
        ]);
    }

    private function generateVid2Vid(Request $request): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $request->validate([
            'modelId' => 'nullable|integer',
            'cfgScale' => 'nullable|integer|between:2,10',
            'prompt' => 'nullable|string',
            'frameCount' => 'numeric|between:1,20',
            'denoising' => 'nullable|numeric|between:0.1,1.0',
            'soundtrack_start_seconds' => 'nullable|numeric|min:0',
            'soundtrack_end_seconds' => 'nullable|numeric|gt:soundtrack_start_seconds',
            'seed' => 'nullable|integer',
            'negative_prompt' => 'nullable|string',
            'controlnet' => 'nullable|array',
            'extendFromJobId' => 'nullable|integer|exists:video_jobs,id',
        ]);

        // Validate that required fields are present if not extending
        if (!$request->input('extendFromJobId')) {
            $request->validate([
                'modelId' => 'required|integer',
                'cfgScale' => 'required|integer|between:2,10',
                'prompt' => 'required|string',
                'denoising' => 'required|numeric|between:0.1,1.0',
            ]);
        }

        $variants = (int) $request->input('variants', 1);
        $frameCount = $request->input('frameCount', 1);
        $extendFromJobId = $request->input('extendFromJobId');

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->assertOwner($videoJob)) {
            return $response;
        }

        // Handle job extension - validate base job
        if ($extendFromJobId) {
            $baseJob = Videojob::findOrFail($extendFromJobId);

            if ($baseJob->generator === 'deforum') {
                return response()->json(['message' => 'Cannot extend deforum jobs with vid2vid'], 422);
            }

            if ($response = $this->assertOwner($baseJob)) {
                return $response;
            }
        }

        // Handle multiple variants
        if ($variants > 1) {
            return $this->generateVid2VidVariants($request, $videoJob, $variants, $frameCount, $extendFromJobId);
        }

        // Single variant processing
        return $this->processVid2VidJob($request, $videoJob, $frameCount, $extendFromJobId);
    }

    private function processVid2VidJob(Request $request, Videojob $videoJob, int $frameCount, ?int $extendFromJobId): JsonResponse
    {
        $seed = $this->normalizeSeed((int) $request->input('seed', -1));
        $extendFromJobId = $request->input('extendFromJobId');

        // Handle job extension
        if ($extendFromJobId) {
            $baseJob = Videojob::findOrFail($extendFromJobId);

            // Use last frame of base job as init image for extension
            if (!empty($baseJob->last_frame_path) && file_exists($baseJob->last_frame_path)) {
                // Copy the last frame to use as the new job's original video
                $videosPath = config('app.paths.videos', 'videos');
                $targetPath = public_path($videosPath . '/' . $videoJob->id . '_extend_init.png');
                
                // Ensure directory exists
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (copy($baseJob->last_frame_path, $targetPath)) {
                    Log::info('Using last frame from base job as init image', [
                        'base_job_id' => $baseJob->id,
                        'last_frame' => $baseJob->last_frame_path,
                        'target_path' => $targetPath,
                    ]);
                } else {
                    Log::warning('Failed to copy last frame for init image', [
                        'base_job_id' => $baseJob->id,
                        'last_frame' => $baseJob->last_frame_path,
                        'target_path' => $targetPath,
                    ]);
                }
            }

            $persistedParameters = json_decode((string) $baseJob->generation_parameters, true) ?? [];

            // Set defaults from base job (these will be overridden if provided in request)
            $videoJob->model_id = $request->input('modelId', $persistedParameters['model_id'] ?? $baseJob->model_id);
            $videoJob->cfg_scale = $request->input('cfgScale', $persistedParameters['cfg_scale'] ?? $baseJob->cfg_scale);
            $videoJob->denoising = $request->input('denoising', $persistedParameters['denoising_strength'] ?? $baseJob->denoising);
            $videoJob->prompt = $request->input('prompt', $persistedParameters['prompt'] ?? $baseJob->prompt);
            $videoJob->negative_prompt = $request->input('negative_prompt', $persistedParameters['negative_prompt'] ?? $baseJob->negative_prompt);
            $videoJob->seed = $seed;
            $videoJob->fps = $persistedParameters['fps'] ?? $baseJob->fps;
            $videoJob->width = $baseJob->width;
            $videoJob->height = $baseJob->height;
        } else {
            $videoJob->model_id = $request->input('modelId');
            $videoJob->cfg_scale = $request->input('cfgScale');
            $videoJob->denoising = $request->input('denoising');
            $videoJob->prompt = trim((string) $request->input('prompt'));
            $videoJob->negative_prompt = trim((string) $request->input('negative_prompt', ''));
            $videoJob->seed = $seed;
        }

        $this->applySoundtrackWindow($videoJob, $request);

        $controlnet = $request->input('controlnet', []);

        if (! empty($controlnet)) {
            $videoJob->controlnet = json_encode($controlnet);
            Log::info('Got controlnet params: ' . json_encode($controlnet), ['controlnet' => json_decode($videoJob->controlnet)]);
        }
        $videoJob->status = 'processing';
        $videoJob->progress = 5;
        $videoJob->job_time = 3;
        $videoJob->estimated_time_left = ($frameCount * 6) + 6;
        $videoJob->queued_at = Carbon::now();
        $videoJob->save();

        // Assign instance for load balancing (vid2vid uses SD instances)
        $instanceType = 'stable_diffusion_forge';
        $instance = $this->loadBalancer->selectInstance($instanceType);
        if ($instance) {
            $this->loadBalancer->assignJobToInstance($videoJob->id, $instance);
        }

        $queueName = $frameCount > 1
            ? $this->resolveQueueName('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->resolveQueueName('HIGH_PRIORITY_QUEUE', 'high');
        Log::info("Dispatching job with framecount {$frameCount} to queue {$queueName}");
        ProcessVideoJob::dispatch($videoJob, $frameCount, $extendFromJobId)->onQueue($queueName);

        return response()->json([
            'id' => $videoJob->id,
            'status' => $videoJob->status,
            'seed' => $videoJob->seed,
            'job_time' => $videoJob->job_time,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'width' => $videoJob->width,
            'height' => $videoJob->height,
            'length' => $videoJob->length,
            'fps' => $videoJob->fps,
        ]);
    }

    private function generateVid2VidVariants(Request $request, Videojob $originalJob, int $variants, int $frameCount, ?int $extendFromJobId): JsonResponse
    {
        $jobs = [];
        $queueName = $frameCount > 1
            ? $this->resolveQueueName('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->resolveQueueName('HIGH_PRIORITY_QUEUE', 'high');

        for ($i = 0; $i < $variants; $i++) {
            // Clone the original job for each variant
            if ($i === 0) {
                // Use the original job for the first variant
                $variantJob = $originalJob;
            } else {
                // Create a new job by replicating the original
                $variantJob = $originalJob->replicate();
                $variantJob->save();
                
                // Copy media files to the new job if needed
                foreach ($originalJob->getMedia('original') as $media) {
                    $variantJob->addMedia($media->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('original');
                }
            }

            // Generate unique seed for each variant
            $seed = $this->normalizeSeed(-1);

            // Set job parameters
            if ($extendFromJobId) {
                $baseJob = Videojob::findOrFail($extendFromJobId);
                $persistedParameters = json_decode((string) $baseJob->generation_parameters, true) ?? [];

                $variantJob->model_id = $request->input('modelId', $persistedParameters['model_id'] ?? $baseJob->model_id);
                $variantJob->cfg_scale = $request->input('cfgScale', $persistedParameters['cfg_scale'] ?? $baseJob->cfg_scale);
                $variantJob->denoising = $request->input('denoising', $persistedParameters['denoising_strength'] ?? $baseJob->denoising);
                $variantJob->prompt = $request->input('prompt', $persistedParameters['prompt'] ?? $baseJob->prompt);
                $variantJob->negative_prompt = $request->input('negative_prompt', $persistedParameters['negative_prompt'] ?? $baseJob->negative_prompt);
                $variantJob->fps = $persistedParameters['fps'] ?? $baseJob->fps;
                $variantJob->width = $baseJob->width;
                $variantJob->height = $baseJob->height;
            } else {
                $variantJob->model_id = $request->input('modelId');
                $variantJob->cfg_scale = $request->input('cfgScale');
                $variantJob->denoising = $request->input('denoising');
                $variantJob->prompt = trim((string) $request->input('prompt'));
                $variantJob->negative_prompt = trim((string) $request->input('negative_prompt', ''));
            }

            $variantJob->seed = $seed;
            $variantJob->status = 'processing';
            $variantJob->progress = 5;
            $variantJob->job_time = 3;
            $variantJob->estimated_time_left = ($frameCount * 6) + 6;
            $variantJob->queued_at = Carbon::now();

            $controlnet = $request->input('controlnet', []);
            if (! empty($controlnet)) {
                $variantJob->controlnet = json_encode($controlnet);
            }

            $variantJob->save();

            // Assign instance for load balancing
            $instanceType = 'stable_diffusion_forge';
            $instance = $this->loadBalancer->selectInstance($instanceType);
            if ($instance) {
                $this->loadBalancer->assignJobToInstance($variantJob->id, $instance);
            }

            // Dispatch the job
            ProcessVideoJob::dispatch($variantJob, $frameCount, $extendFromJobId)->onQueue($queueName);

            Log::info("Dispatched variant job {$i+1}/{$variants}", [
                'job_id' => $variantJob->id,
                'seed' => $seed,
                'queue' => $queueName
            ]);

            $jobs[] = [
                'id' => $variantJob->id,
                'status' => $variantJob->status,
                'seed' => $variantJob->seed,
                'job_time' => $variantJob->job_time,
                'progress' => $variantJob->progress,
                'estimated_time_left' => $variantJob->estimated_time_left,
                'width' => $variantJob->width,
                'height' => $variantJob->height,
                'length' => $variantJob->length,
                'fps' => $variantJob->fps,
            ];
        }

        return response()->json([
            'variants' => $jobs,
            'count' => count($jobs),
        ]);
    }

    public function finalize(Request $request): JsonResponse
{
    if ($response = $this->guardAuthenticated()) {
        return $response;
    }

    $videoJob = Videojob::findOrFail($request->input('videoId'));

    if ($response = $this->assertOwner($videoJob)) {
        return $response;
    }

    if ($videoJob->generator === 'deforum') {
        $request->validate([
            'modelId' => 'integer',
            'prompt' => 'string',
            'preset' => 'string',
            'length' => 'numeric|between:1,20',
        ]);

        $videoJob->resetProgress('approved');
        $videoJob->fps = 24;
        $videoJob->seed = $this->normalizeSeed((int) $request->input('seed', -1));
        $videoJob->model_id = $request->input('modelId', $videoJob->model_id);
        $videoJob->prompt = trim((string) $request->input('prompt', $videoJob->prompt));
        $videoJob->negative_prompt = trim((string) $request->input('negative_prompt', $videoJob->negative_prompt));
        $videoJob->length = $request->input('length', $videoJob->length);
        $videoJob->frame_count = round($videoJob->length * $videoJob->fps);
        $videoJob->save();

        // Assign instance for load balancing
        $instanceType = 'stable_diffusion_forge';
        $instance = $this->loadBalancer->selectInstance($instanceType);
        if ($instance) {
            $this->loadBalancer->assignJobToInstance($videoJob->id, $instance);
        }
        
        $videoJob->refresh();
        ProcessDeforumJob::dispatch($videoJob, 0, null)->onQueue($this->resolveQueueName('LOW_PRIORITY_QUEUE', 'low'));
    } else {
        $videoJob->resetProgress('approved');

        // Assign instance for load balancing
        $instanceType = 'stable_diffusion_forge';
        $instance = $this->loadBalancer->selectInstance($instanceType);
        if ($instance) {
            $this->loadBalancer->assignJobToInstance($videoJob->id, $instance);
        }

        $videoJob->refresh();
        ProcessVideoJob::dispatch($videoJob, 0, null)->onQueue($this->resolveQueueName('LOW_PRIORITY_QUEUE', 'low'));
    }

    return response()->json([
        'status' => $videoJob->status,
        'progress' => $videoJob->progress,
        'job_time' => $videoJob->job_time,
        'retries' => $videoJob->retries,
        'queued_at' => $this->queuedAtTimestamp($videoJob->queued_at),
        'estimated_time_left' => $videoJob->estimated_time_left,
    ]);
}

    public function cancelJob($videoId): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $videoJob = Videojob::findOrFail($videoId);

        if ($response = $this->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob->resetProgress('cancelled');
        
        // Mark instance job as cancelled for load balancing
        $this->loadBalancer->markJobAsCancelled($videoJob->id);

        return response()->json([
            'status' => $videoJob->status,
            'progress' => 0,
            'job_time' => 0,
            'estimated_time_left' => 0,
        ]);
    }

    public function status($id): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $videoJob = Videojob::findOrFail($id);

        if ($response = $this->assertOwner($videoJob)) {
            return $response;
        }

        return response()->json([
            'id' => $videoJob->id,
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'job_time' => $videoJob->job_time,
            'queued_at' => $this->queuedAtTimestamp($videoJob->queued_at),
            'queue' => $videoJob->status === 'approved' ? $videoJob->getQueueInfo() : [],
            'generator' => $videoJob->generator,
            'model_id' => $videoJob->model_id,
            'prompt' => $videoJob->prompt,
            'negative_prompt' => $videoJob->negative_prompt,
            'cfg_scale' => $videoJob->cfg_scale,
            'seed' => $videoJob->seed,
            'denoising' => $videoJob->denoising,
            'fps' => $videoJob->fps,
            'frame_count' => $videoJob->frame_count,
            'length' => $videoJob->length,
            'width' => $videoJob->width,
            'height' => $videoJob->height,
            'generation_parameters' => $videoJob->generation_parameters,
        ]);
    }

    private function queuedAtTimestamp($queuedAt): ?int
    {
        if (is_null($queuedAt)) {
            return null;
        }

        if (is_numeric($queuedAt)) {
            return (int) $queuedAt;
        }

        return $queuedAt instanceof \Carbon\CarbonInterface ? $queuedAt->timestamp : null;
    }

    public function getVideoJobs(): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $userId = auth('api')->id();
        $videoJobs = Videojob::where('user_id', $userId)->get();

        return response()->json($videoJobs);
    }

    public function processingStatus(): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $userId = auth('api')->id();

        $processingJobs = Videojob::where('user_id', $userId)
            ->where('status', Videojob::STATUS_PROCESSING)
            ->orderByDesc('updated_at')
            ->get();

        $queuedJobs = Videojob::where('user_id', $userId)
            ->where('status', Videojob::STATUS_APPROVED)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();

        // Optimize: Get global counts in a single query using conditional aggregation
        $counts = \DB::table('video_jobs')
            ->selectRaw('
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as queued
            ', [Videojob::STATUS_PROCESSING, Videojob::STATUS_APPROVED])
            ->first();

        return response()->json([
            'processing' => $processingJobs->map(fn (Videojob $job) => $this->serializeJobStatus($job)),
            'queue' => $queuedJobs->map(fn (Videojob $job) => $this->serializeJobStatus($job, true)),
            'counts' => [
                'processing' => $counts->processing ?? 0,
                'queued' => $counts->queued ?? 0,
            ],
        ]);
    }

    public function processingQueue(): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $userId = auth('api')->id();

        $queueJobs = Videojob::where('user_id', $userId)
            ->whereIn('status', [Videojob::STATUS_APPROVED, Videojob::STATUS_PROCESSING])
            ->orderByRaw('queued_at IS NULL')
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();

        return response()->json(
            $queueJobs->map(fn (Videojob $job) => $this->serializeJobStatus($job, true))
        );
    }

    /**
     * Attach audio file to an existing video job.
     * PATCH /api/video-jobs/{videoId}/audio
     */
    public function attachAudio(Request $request, int $videoId): JsonResponse
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        $videoJob = Videojob::findOrFail($videoId);

        if ($response = $this->assertOwner($videoJob)) {
            return $response;
        }

        // Only allow attaching audio to pending jobs
        if ($videoJob->status !== Videojob::STATUS_PENDING) {
            return response()->json([
                'error' => 'Audio can only be attached to pending jobs',
            ], 422);
        }

        $request->validate([
            'soundtrack' => 'required|file|mimes:mp3,aac,wav|max:51200',
        ]);

        $this->attachSoundtrack($videoJob, $request);
        $videoJob->save();

        return response()->json([
            'id' => $videoJob->id,
            'soundtrack_url' => $videoJob->soundtrack_url,
            'soundtrack_mimetype' => $videoJob->soundtrack_mimetype,
            'message' => 'Audio file attached successfully',
        ]);
    }

    private function persistUploadedFile(Request $request): array
    {
        $uploadedFile = $request->file('attachment');
        $path = $uploadedFile->store('videos', 'public');
        $filename = basename($path);

        $publicDirectory = public_path('videos');
        if (! is_dir($publicDirectory)) {
            mkdir($publicDirectory, 0755, true);
        }

        $storagePath = Storage::disk('public')->path($path);
        copy($storagePath, $publicDirectory . '/' . $filename);

        return [
            'filename' => $filename,
            'originalName' => $uploadedFile->getClientOriginalName(),
            'outfile' => pathinfo($filename, PATHINFO_FILENAME) . '.mp4',
            'path' => $path,
            'publicPath' => $publicDirectory . '/' . $filename,
            'mimeType' => $uploadedFile->getMimeType(),
        ];
    }

    private function persistMedia(Videojob $videoJob, string $path): void
    {
        $videoJob->save();
        
        // Convert storage-relative path to absolute filesystem path
        $absolutePath = Storage::disk('public')->path($path);
        
        $fileAdder = $videoJob->addMedia($absolutePath)
            ->preservingOriginal();
        
        // Skip responsive images in testing to avoid queue issues
        if (!app()->environment('testing')) {
            $fileAdder->withResponsiveImages();
        }
        
        $fileAdder->toMediaCollection(Videojob::MEDIA_ORIGINAL);

        $videoJob->original_url = $videoJob->getMedia(Videojob::MEDIA_ORIGINAL)->first()?->getFullUrl();
        $videoJob->save();
    }

    private function attachSoundtrack(Videojob $videoJob, Request $request): void
    {
        $soundtrack = $this->persistSoundtrack($request);

        if (! $soundtrack) {
            return;
        }

        $videoJob->soundtrack_path = $soundtrack['absolutePath'];
        $videoJob->soundtrack_url = $soundtrack['url'];
        $videoJob->soundtrack_mimetype = $soundtrack['mimeType'];
        $videoJob->soundtrack_start_seconds = $request->input('soundtrack_start_seconds');
        $videoJob->soundtrack_end_seconds = $request->input('soundtrack_end_seconds');
    }

    private function persistSoundtrack(Request $request): ?array
    {
        if (! $request->hasFile('soundtrack')) {
            return null;
        }

        $soundtrack = $request->file('soundtrack');
        $path = $soundtrack->store('soundtracks', 'public');

        return [
            'absolutePath' => Storage::disk('public')->path($path),
            'url' => Storage::disk('public')->url($path),
            'mimeType' => $soundtrack->getMimeType(),
        ];
    }

    private function applySoundtrackWindow(Videojob $videoJob, Request $request): void
    {
        if ($request->has('soundtrack_start_seconds')) {
            $videoJob->soundtrack_start_seconds = $request->input('soundtrack_start_seconds');
        }

        if ($request->has('soundtrack_end_seconds')) {
            $videoJob->soundtrack_end_seconds = $request->input('soundtrack_end_seconds');
        }
    }

    private function guardAuthenticated(): ?JsonResponse
    {
        if (! auth('api')->id()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return null;
    }

    private function assertOwner(Videojob $videoJob): ?JsonResponse
    {
        if ($videoJob->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized. Not your video.'], 403);
        }

        return null;
    }

    /**
     * Set the initial image for a job to be the last frame of the base job.
     * This is used when extending a deforum job.
     */
    private function setInitImageFromBaseJob(Videojob $videoJob, Videojob $baseJob): void
    {
        $lastFramePath = null;

        // First, try to use the saved last_frame_path if it exists and is valid
        if (!empty($baseJob->last_frame_path) && file_exists($baseJob->last_frame_path)) {
            $lastFramePath = $baseJob->last_frame_path;
            Log::info('Using saved last frame from base job', [
                'base_job_id' => $baseJob->id,
                'last_frame_path' => $lastFramePath,
            ]);
        } else {
            // Extract last frame from base job's video
            $sourceVideoPath = $baseJob->hasFinishedVideo() 
                ? $baseJob->getFinishedVideoPath() 
                : $baseJob->getOriginalVideoPath();

            if (!file_exists($sourceVideoPath)) {
                Log::warning('Source video not found for extending job', [
                    'base_job_id' => $baseJob->id,
                    'source_path' => $sourceVideoPath,
                ]);
                return;
            }

            // Generate path for the init image
            $targetDir = dirname($videoJob->getOriginalVideoPath());
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $initImagePath = sprintf('%s/%d_extend_init.png', $targetDir, $videoJob->id);

            $frameExtractor = app(FrameExtractor::class);
            $success = $frameExtractor->extractLastFrame($sourceVideoPath, $initImagePath);

            if ($success && file_exists($initImagePath)) {
                $lastFramePath = $initImagePath;
                Log::info('Extracted last frame from base job video', [
                    'base_job_id' => $baseJob->id,
                    'init_image_path' => $lastFramePath,
                ]);
            } else {
                Log::warning('Failed to extract last frame from base job', [
                    'base_job_id' => $baseJob->id,
                    'source_path' => $sourceVideoPath,
                    'target_path' => $initImagePath,
                ]);
                return;
            }
        }

        // Copy the last frame to the new job's original file location
        if ($lastFramePath && file_exists($lastFramePath)) {
            $targetOriginalPath = $videoJob->getOriginalVideoPath();
            $targetDir = dirname($targetOriginalPath);
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Generate a new filename for the init image
            $extension = pathinfo($lastFramePath, PATHINFO_EXTENSION);
            $newFilename = sprintf('%d_extend_init.%s', $videoJob->id, $extension ?: 'png');
            $targetPath = sprintf('%s/%s', $targetDir, $newFilename);

            if (copy($lastFramePath, $targetPath)) {
                // Update the job's filename and related fields
                $videoJob->filename = $newFilename;
                $videoJob->original_filename = basename($newFilename);
                $videoJob->mimetype = function_exists('mime_content_type') 
                    ? mime_content_type($targetPath) 
                    : 'image/png';
                
                // Store the file in storage for media library
                $storagePath = 'videos/' . $newFilename;
                $storageFullPath = Storage::disk('public')->path($storagePath);
                $storageDir = dirname($storageFullPath);
                if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
                    Log::warning('Failed to create storage directory for init image', [
                        'video_job_id' => $videoJob->id,
                        'target_directory' => $storageDir,
                    ]);
                    return;
                }

                if (!copy($targetPath, $storageFullPath)) {
                    Log::warning('Failed to copy init image into storage', [
                        'video_job_id' => $videoJob->id,
                        'source' => $targetPath,
                        'target' => $storageFullPath,
                    ]);
                    return;
                }

                // Update media library if job has existing original media
                try {
                    $originalMedia = $videoJob->getMedia(Videojob::MEDIA_ORIGINAL)->first();
                    if ($originalMedia) {
                        $originalMedia->delete();
                    }
                    $this->persistMedia($videoJob, $storagePath);
                } catch (\Exception $e) {
                    Log::warning('Failed to update media library for extended job', [
                        'video_job_id' => $videoJob->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                Log::info('Set init image from base job last frame', [
                    'video_job_id' => $videoJob->id,
                    'base_job_id' => $baseJob->id,
                    'init_image_path' => $targetPath,
                ]);
            } else {
                Log::warning('Failed to copy last frame to new job location', [
                    'video_job_id' => $videoJob->id,
                    'base_job_id' => $baseJob->id,
                    'source' => $lastFramePath,
                    'target' => $targetPath,
                ]);
            }
        }
    }

    private function normalizeSeed(int $seed): int
    {
        return $seed > 0 ? $seed : rand(1, 4294967295);
    }

    private function resolveQueueName(string $envKey, string $default): string
    {
        // Note: Queue names should be defined in config/queue.php for proper config caching
        // For now, using env() with a fallback. Consider moving to config file.
        $queue = config("queue.names.{$envKey}", env($envKey));

        return ! empty($queue) ? $queue : $default;
    }

    private function serializeJobStatus(Videojob $videoJob, bool $includeQueueInfo = false): array
    {
        return [
            'id' => $videoJob->id,
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'job_time' => $videoJob->job_time,
            'queued_at' => $this->queuedAtTimestamp($videoJob->queued_at),
            'generator' => $videoJob->generator,
            'model_id' => $videoJob->model_id,
            'prompt' => $videoJob->prompt,
            'negative_prompt' => $videoJob->negative_prompt,
            'frame_count' => $videoJob->frame_count,
            'queue' => $includeQueueInfo ? $videoJob->getQueueInfo() : [],
        ];
    }
}
