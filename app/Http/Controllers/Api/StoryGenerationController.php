<?php

namespace App\Http\Controllers\Api;

use App\Models\StoryBatch;
use App\Models\StoryFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoryGenerationController extends ApiController
{
    /**
     * Start a new story generation batch and persist the request config.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'story' => 'required|array',
            'story.scenes' => 'required|array|min:1',
        ]);

        $totalFrames = collect($validated['story']['scenes'])
            ->sum(fn ($scene) => count($scene['frames'] ?? []));

        $batch = StoryBatch::create([
            'user_id' => auth('api')->id(),
            'status' => 'pending',
            'total_frames' => $totalFrames,
            'completed_frames' => 0,
            'progress' => 0,
            'config_json' => json_encode($request->all()),
        ]);

        return response()->json([
            'batchId' => (string) $batch->id,
            'totalFrames' => $totalFrames,
            'estimatedDuration' => null,
        ]);
    }

    /**
     * Fetch status and progress metrics for a batch owned by the current user.
     */
    public function status(string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        return response()->json([
            'status' => $batch->status,
            'progress' => $batch->progress,
            'completedFrames' => $batch->completed_frames,
            'totalFrames' => $batch->total_frames,
        ]);
    }

    /**
     * Retrieve the persisted story/config payload to resume editing later.
     */
    public function getConfig(string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        $config = $batch->config_json ? json_decode($batch->config_json, true) : null;

        return response()->json([
            'batchId' => (string) $batch->id,
            'config' => $config,
            'updatedAt' => $batch->updated_at?->toISOString(),
        ]);
    }

    /**
     * Update the persisted story/config payload for later continuation.
     */
    public function updateConfig(Request $request, string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        $validated = $request->validate([
            'story' => 'required|array',
            'story.scenes' => 'required|array|min:1',
            'config' => 'nullable|array',
        ]);

        // Use request->all() to preserve all fields including story.name and config.*
        // that aren't explicitly validated but should be saved
        $batch->config_json = json_encode($request->all());
        $batch->save();

        return response()->json([
            'batchId' => (string) $batch->id,
            'updatedAt' => $batch->updated_at?->toISOString(),
        ]);
    }

    /**
     * Pause a running batch owned by the current user.
     */
    public function pause(string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        $batch->status = 'paused';
        $batch->save();

        return response()->json(['status' => $batch->status]);
    }

    /**
     * Resume a paused batch owned by the current user.
     */
    public function resume(string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        $batch->status = 'processing';
        $batch->save();

        return response()->json(['status' => $batch->status]);
    }

    /**
     * Cancel a batch owned by the current user.
     */
    public function cancel(string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->first();

        $batch->status = 'cancelled';
        $batch->save();

        return response()->json(['status' => $batch->status]);
    }

    /**
     * Persist a generated frame and update batch progress.
     */
    public function persistFrame(Request $request, string $batchId): JsonResponse
    {
        $batch = StoryBatch::where('id', $batchId)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        $validated = $request->validate([
            'frameId' => 'required|integer',
            'prompt' => 'nullable|string',
            'image' => 'nullable|string',
            'imageUrl' => 'nullable|string',
        ]);

        $imageUrl = $validated['imageUrl'] ?? null;

        if (!$imageUrl && !empty($validated['image'])) {
            $imageData = $validated['image'];
            if (str_starts_with($imageData, 'data:')) {
                $parts = explode(',', $imageData, 2);
                $imageData = $parts[1] ?? '';
            }

            $binary = base64_decode($imageData, true);
            if ($binary !== false) {
                $path = "story-frames/{$batchId}/" . Str::uuid() . '.png';
                Storage::disk('public')->put($path, $binary);
                $imageUrl = Storage::disk('public')->url($path);
            }
        }

        $frame = StoryFrame::create([
            'story_batch_id' => $batch->id,
            'frame_id' => $validated['frameId'],
            'prompt' => $validated['prompt'] ?? '',
            'image_url' => $imageUrl,
        ]);

        $batch->completed_frames = min($batch->total_frames, $batch->completed_frames + 1);
        $batch->progress = $batch->total_frames > 0
            ? (int) floor(($batch->completed_frames / $batch->total_frames) * 100)
            : 0;
        $batch->status = $batch->completed_frames >= $batch->total_frames ? 'complete' : $batch->status;
        $batch->save();

        return response()->json([
            'frameId' => $frame->frame_id,
            'imageUrl' => $frame->image_url,
            'thumbnailUrl' => $frame->image_url,
        ]);
    }

    /**
     * Create a share token for a batch and return a share URL.
     */
    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'story' => 'required|array',
            'config' => 'nullable|array',
            'batchId' => 'nullable|string',
        ]);

        $token = Str::random(24);
        $batchId = $validated['batchId'] ?? null;

        if ($batchId) {
            $batch = StoryBatch::where('id', $batchId)
                ->where('user_id', auth('api')->id())
                ->first();

            if ($batch) {
                $batch->share_token = $token;
                $batch->save();
            }
        }

        return response()->json([
            'shareUrl' => url("/story/share/{$token}"),
        ]);
    }
}

