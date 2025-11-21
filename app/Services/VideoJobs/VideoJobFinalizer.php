<?php

namespace App\Services\VideoJobs;

use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use App\Models\Videojob;

class VideoJobFinalizer
{
    use GeneratesSeeds;

    public function __construct(private QueueNameResolver $queueNameResolver)
    {
    }

    public function finalize(Videojob $videoJob): Videojob
    {
        $videoJob->resetProgress('approved');
        $videoJob->refresh();
        ProcessVideoJob::dispatch($videoJob, 0)->onQueue($this->queueNameResolver->resolve('LOW_PRIORITY_QUEUE', 'low'));

        return $videoJob;
    }

    public function finalizeDeforum(Videojob $videoJob, array $payload): Videojob
    {
        $videoJob->resetProgress('approved');
        $videoJob->fps = 24;
        $videoJob->seed = $this->normalizeSeed((int) ($payload['seed'] ?? -1));
        $videoJob->model_id = $payload['modelId'] ?? $videoJob->model_id;
        $videoJob->prompt = trim((string) ($payload['prompt'] ?? $videoJob->prompt));
        $videoJob->negative_prompt = trim((string) ($payload['negative_prompt'] ?? $videoJob->negative_prompt));
        $videoJob->length = $payload['length'] ?? $videoJob->length;
        $videoJob->frame_count = round($videoJob->length * $videoJob->fps);
        $videoJob->save();

        $videoJob->refresh();
        ProcessDeforumJob::dispatch($videoJob, 0)->onQueue($this->queueNameResolver->resolve('LOW_PRIORITY_QUEUE', 'low'));

        return $videoJob;
    }

    public function cancel(Videojob $videoJob): Videojob
    {
        $videoJob->resetProgress('cancelled');

        return $videoJob;
    }
}
