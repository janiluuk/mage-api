<?php

namespace App\Http\Controllers;

use App\Models\Videojob;
use App\Services\VideoJobs\VideoJobFinalizer;
use App\Services\VideoJobs\VideoJobGuard;
use App\Services\VideoJobs\VideoJobSubmitter;
use App\Services\VideoJobs\VideoJobUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideojobController extends Controller
{
    public function __construct(
        private VideoJobUploader $uploader,
        private VideoJobSubmitter $submitter,
        private VideoJobFinalizer $finalizer,
        private VideoJobGuard $guard,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'attachment' => 'required|mimes:webm,mp4,mov,ogg,qt,gif,jpg,jpeg,png,webp|max:200000',
            'type' => 'required|in:vid2vid,deforum',
        ]);

        $videoJob = $this->uploader->upload(
            $validated['type'],
            $request->file('attachment'),
            auth('api')->id(),
        );

        return response()->json([
            'url' => $videoJob->original_url,
            'status' => $videoJob->status,
            'id' => $videoJob->id,
        ]);
    }

    public function submitDeforum(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $payload = $request->validate([
            'modelId' => 'required|integer',
            'prompt' => 'required|string',
            'frameCount' => 'numeric|between:1,20',
            'preset' => 'required|string',
            'length' => 'numeric|between:1,20',
        ]);

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->guard->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob = $this->submitter->submitDeforum($videoJob, $payload);

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

    public function submit(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $payload = $request->validate([
            'modelId' => 'required|integer',
            'cfgScale' => 'required|integer|between:2,10',
            'prompt' => 'required|string',
            'frameCount' => 'numeric|between:1,20',
            'denoising' => 'required|numeric|between:0.1,1.0',
        ]);

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->guard->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob = $this->submitter->submitVid2Vid($videoJob, array_merge($payload, [
            'seed' => $request->input('seed', -1),
            'negative_prompt' => $request->input('negative_prompt', ''),
            'controlnet' => $request->input('controlnet', []),
        ]));

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

    public function finalizeDeforum(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $payload = $request->validate([
            'modelId' => 'integer',
            'prompt' => 'string',
            'preset' => 'string',
            'length' => 'numeric|between:1,20',
        ]);

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->guard->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob = $this->finalizer->finalizeDeforum($videoJob, array_merge($payload, [
            'seed' => $request->input('seed', -1),
            'negative_prompt' => $request->input('negative_prompt', $videoJob->negative_prompt),
        ]));

        return response()->json([
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'job_time' => $videoJob->job_time,
            'retries' => $videoJob->retries,
            'queued_at' => $videoJob->queued_at,
            'estimated_time_left' => $videoJob->estimated_time_left,
        ]);
    }

    public function finalize(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->guard->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob = $this->finalizer->finalize($videoJob);

        return response()->json([
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'job_time' => $videoJob->job_time,
            'retries' => $videoJob->retries,
            'queued_at' => $videoJob->queued_at,
            'estimated_time_left' => $videoJob->estimated_time_left,
        ]);
    }

    public function cancelJob(Request $request): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $videoJob = Videojob::findOrFail($request->input('videoId'));

        if ($response = $this->guard->assertOwner($videoJob)) {
            return $response;
        }

        $videoJob = $this->finalizer->cancel($videoJob);

        return response()->json([
            'status' => $videoJob->status,
            'progress' => 0,
            'job_time' => 0,
            'estimated_time_left' => 0,
        ]);
    }

    public function status(int $id): JsonResponse
    {
        $videoJob = Videojob::findOrFail($id);

        return response()->json([
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'job_time' => $videoJob->job_time,
            'queued_at' => $videoJob->queued_at,
            'queue' => $videoJob->status === 'approved' ? $videoJob->getQueueInfo() : [],
        ]);
    }

    public function getVideoJobs(): JsonResponse
    {
        if ($response = $this->guard->requireAuthenticated()) {
            return $response;
        }

        $userId = auth('api')->id();
        $videoJobs = Videojob::where('user_id', $userId)->get();

        return response()->json($videoJobs);
    }
}
