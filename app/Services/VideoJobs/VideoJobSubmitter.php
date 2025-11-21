<?php

namespace App\Services\VideoJobs;

use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Videojob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VideoJobSubmitter
{
    use GeneratesSeeds;

    public function __construct(private QueueNameResolver $queueNameResolver)
    {
    }

    public function submitVid2Vid(Videojob $videoJob, array $payload): Videojob
    {
        $seed = $this->normalizeSeed((int) ($payload['seed'] ?? -1));
        $frameCount = $payload['frameCount'] ?? 1;
        $controlnet = $payload['controlnet'] ?? [];

        if (! empty($controlnet)) {
            $videoJob->controlnet = json_encode($controlnet);
            Log::info('Got controlnet params: ' . json_encode($controlnet), ['controlnet' => json_decode($videoJob->controlnet)]);
        }

        $videoJob->model_id = $payload['modelId'];
        $videoJob->prompt = trim((string) $payload['prompt']);
        $videoJob->negative_prompt = trim((string) ($payload['negative_prompt'] ?? ''));
        $videoJob->cfg_scale = $payload['cfgScale'];
        $videoJob->seed = $seed;
        $videoJob->status = 'processing';
        $videoJob->progress = 5;
        $videoJob->job_time = 3;
        $videoJob->estimated_time_left = ($frameCount * 6) + 6;
        $videoJob->denoising = $payload['denoising'];
        $videoJob->queued_at = Carbon::now();
        $videoJob->save();

        $queueName = $frameCount > 1
            ? $this->queueNameResolver->resolve('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->queueNameResolver->resolve('HIGH_PRIORITY_QUEUE', 'high');

        Log::info("Dispatching job with framecount {$frameCount} to queue {$queueName}");
        ProcessVideoJob::dispatch($videoJob, $frameCount)->onQueue($queueName);

        return $videoJob;
    }

    public function submitDeforum(Videojob $videoJob, array $payload): Videojob
    {
        $frameCount = $payload['frameCount'] ?? 1;
        $videoJob->model_id = $payload['modelId'];
        $videoJob->prompt = trim((string) $payload['prompt']);
        $videoJob->negative_prompt = trim((string) ($payload['negative_prompt'] ?? ''));
        $videoJob->status = 'processing';
        $videoJob->progress = 5;
        $seed = $this->normalizeSeed((int) ($payload['seed'] ?? -1));

        $videoJob->fps = 24;
        $videoJob->generator = 'deforum';
        $videoJob->seed = $seed;
        $videoJob->length = $payload['length'] ?? 4;
        $videoJob->frame_count = round($videoJob->length * $videoJob->fps);
        $videoJob->job_time = 3;
        $videoJob->estimated_time_left = ($videoJob->frame_count * 6) + 6;
        $videoJob->denoising = $payload['denoising'] ?? null;
        $videoJob->queued_at = Carbon::now();
        $videoJob->save();

        $queueName = $frameCount > 1
            ? $this->queueNameResolver->resolve('MEDIUM_PRIORITY_QUEUE', 'medium')
            : $this->queueNameResolver->resolve('HIGH_PRIORITY_QUEUE', 'high');

        Log::info("Dispatching job with framecount {$frameCount} to queue {$queueName}");
        ProcessDeforumJob::dispatch($videoJob, $frameCount)->onQueue($queueName);

        return $videoJob;
    }
}
