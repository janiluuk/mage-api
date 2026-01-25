<?php

namespace App\Services\VideoJobs;

use App\Models\Videojob;
use App\Models\VideoJobVariant;
use App\Models\ModelFile;
use App\Jobs\ProcessVideoJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VideoJobVariantService
{
    /**
     * Create multiple variants of a video job with different models.
     *
     * @param Videojob $baseJob The base video job to create variants from
     * @param array $models Array of model IDs or ModelFile instances to use for variants
     * @param int $previewFrames Number of preview frames to generate (0 for full video)
     * @return array Array of created variant jobs
     */
    public function createVariants(Videojob $baseJob, array $models, int $previewFrames = 0): array
    {
        $variants = [];
        
        DB::transaction(function () use ($baseJob, $models, $previewFrames, &$variants) {
            foreach ($models as $index => $model) {
                $modelId = $model instanceof ModelFile ? $model->id : $model;
                $modelFile = $model instanceof ModelFile ? $model : ModelFile::find($modelId);
                
                if (!$modelFile) {
                    Log::warning('Skipping variant creation - model not found', ['model_id' => $modelId]);
                    continue;
                }
                
                // Create a new video job with same parameters but different model
                $variantJob = $this->cloneJobWithModel($baseJob, $modelFile);
                
                // Create the variant relationship
                $variant = VideoJobVariant::create([
                    'base_video_job_id' => $baseJob->id,
                    'variant_video_job_id' => $variantJob->id,
                    'model_id' => $modelFile->id,
                    'variant_name' => $modelFile->name ?? "Variant " . ($index + 1),
                    'variant_order' => $index,
                    'status' => 'pending',
                ]);
                
                $variants[] = [
                    'variant' => $variant,
                    'job' => $variantJob,
                ];
                
                Log::info('Created variant job', [
                    'base_job_id' => $baseJob->id,
                    'variant_job_id' => $variantJob->id,
                    'model_id' => $modelFile->id,
                    'model_name' => $modelFile->name,
                ]);
            }
        });
        
        return $variants;
    }

    /**
     * Clone a video job with a different model.
     *
     * @param Videojob $baseJob
     * @param ModelFile $modelFile
     * @return Videojob
     */
    private function cloneJobWithModel(Videojob $baseJob, ModelFile $modelFile): Videojob
    {
        $variantJob = new Videojob();
        
        // Copy all relevant attributes from base job
        $variantJob->user_id = $baseJob->user_id;
        $variantJob->filename = $baseJob->filename;
        $variantJob->original_filename = $baseJob->original_filename;
        $variantJob->original_url = $baseJob->original_url;
        $variantJob->model_id = $modelFile->id;
        $variantJob->prompt = $baseJob->prompt;
        $variantJob->cfg_scale = $baseJob->cfg_scale;
        $variantJob->negative_prompt = $baseJob->negative_prompt;
        $variantJob->seed = $baseJob->seed;
        $variantJob->controlnet = $baseJob->controlnet;
        $variantJob->denoising = $baseJob->denoising;
        $variantJob->width = $baseJob->width;
        $variantJob->height = $baseJob->height;
        $variantJob->generator = $baseJob->generator;
        $variantJob->audio_codec = $baseJob->audio_codec;
        $variantJob->bitrate = $baseJob->bitrate;
        $variantJob->length = $baseJob->length;
        $variantJob->fps = $baseJob->fps;
        $variantJob->mimetype = $baseJob->mimetype;
        $variantJob->frame_count = $baseJob->frame_count;
        $variantJob->codec = $baseJob->codec;
        $variantJob->soundtrack_path = $baseJob->soundtrack_path;
        $variantJob->soundtrack_url = $baseJob->soundtrack_url;
        $variantJob->soundtrack_mimetype = $baseJob->soundtrack_mimetype;
        $variantJob->soundtrack_start_seconds = $baseJob->soundtrack_start_seconds;
        $variantJob->soundtrack_end_seconds = $baseJob->soundtrack_end_seconds;
        $variantJob->first_frame_path = $baseJob->first_frame_path;
        $variantJob->last_frame_path = $baseJob->last_frame_path;
        
        // Generate unique outfile for variant
        $originalOutfile = $baseJob->outfile ?? $baseJob->filename ?? 'video.mp4';
        $pathInfo = pathinfo($originalOutfile);
        
        // Ensure we have valid filename and extension
        $filename = $pathInfo['filename'] ?? 'video';
        $extension = $pathInfo['extension'] ?? 'mp4';
        
        $variantJob->outfile = sprintf(
            '%s_variant_%s_%s.%s',
            $filename,
            $modelFile->id,
            uniqid(),
            $extension
        );
        
        // Set initial status
        $variantJob->status = Videojob::STATUS_PENDING;
        $variantJob->progress = 0;
        $variantJob->estimated_time_left = 0;
        $variantJob->job_time = 0;
        
        $variantJob->save();
        
        return $variantJob;
    }

    /**
     * Process all variants in parallel (by dispatching to queue).
     *
     * @param Videojob $baseJob
     * @param int $previewFrames
     * @return void
     */
    public function processVariantsInParallel(Videojob $baseJob, int $previewFrames = 0): void
    {
        $variants = $baseJob->variants()->with('variantVideoJob')->get();
        
        foreach ($variants as $variant) {
            $variantJob = $variant->variantVideoJob;
            
            if ($variantJob->status !== Videojob::STATUS_PENDING) {
                Log::info('Skipping variant - not in pending status', [
                    'variant_id' => $variant->id,
                    'job_id' => $variantJob->id,
                    'status' => $variantJob->status,
                ]);
                continue;
            }
            
            // Mark as approved to queue it
            $variantJob->resetProgress(Videojob::STATUS_APPROVED);
            
            // Dispatch the job to queue for parallel processing
            ProcessVideoJob::dispatch($variantJob, $previewFrames);
            
            // Update variant status
            $variant->status = 'processing';
            $variant->save();
            
            Log::info('Dispatched variant for processing', [
                'variant_id' => $variant->id,
                'job_id' => $variantJob->id,
                'model_id' => $variant->model_id,
            ]);
        }
    }

    /**
     * Get all variants with their processing status.
     *
     * @param Videojob $baseJob
     * @return array
     */
    public function getVariantsStatus(Videojob $baseJob): array
    {
        return $baseJob->variants()
            ->with(['variantVideoJob', 'modelFile'])
            ->get()
            ->map(function ($variant) {
                $job = $variant->variantVideoJob;
                return [
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->variant_name,
                    'model_name' => $variant->modelFile->name ?? 'Unknown',
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'preview_url' => $job->preview_animation ?? $job->preview_img,
                    'finished_url' => $job->url,
                    'job_time' => $job->job_time,
                    'estimated_time_left' => $job->estimated_time_left,
                ];
            })
            ->toArray();
    }
}
