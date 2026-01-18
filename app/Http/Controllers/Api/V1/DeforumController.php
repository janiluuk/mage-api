<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Deforum Live Control API
 */
class DeforumController extends Controller
{
    /**
     * Send live update to Deforum instance
     * POST /api/v1/deforum/live
     */
    public function sendLiveUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|integer|exists:batches,id',
            'video_job_id' => 'nullable|integer|exists:video_jobs,id',
            'control_params' => 'required|array',
            'action' => 'nullable|string|in:update,pause,resume,stop',
        ]);

        $user = $request->user();

        try {
            // Verify ownership if batch_id or video_job_id provided
            if (!empty($validated['batch_id'])) {
                $batch = \App\Models\Batch::where('user_id', $user->id)
                    ->findOrFail($validated['batch_id']);
            }

            if (!empty($validated['video_job_id'])) {
                $videoJob = \App\Models\Videojob::where('user_id', $user->id)
                    ->findOrFail($validated['video_job_id']);
            }

            // TODO: Forward control update to ComfyUI/Deforum service
            // This would typically involve:
            // 1. Getting the active Deforum instance
            // 2. Sending control parameters via HTTP/WebSocket
            // 3. Updating job status if needed

            Log::info('Deforum live update sent', [
                'user_id' => $user->id,
                'action' => $validated['action'] ?? 'update',
                'params' => $validated['control_params'],
            ]);

            return response()->json([
                'message' => 'Live update sent successfully',
                'action' => $validated['action'] ?? 'update',
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending Deforum live update', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send live update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live status of Deforum session
     * GET /api/v1/deforum/live/status
     */
    public function getLiveStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // TODO: Get status from active Deforum/ComfyUI instance
            // This would typically involve:
            // 1. Checking for active Deforum sessions for this user
            // 2. Querying ComfyUI instance status
            // 3. Returning current state

            $activeSession = [
                'active' => false,
                'batch_id' => null,
                'video_job_id' => null,
                'status' => 'idle',
                'started_at' => null,
                'progress' => 0,
            ];

            // Check for active batch processing
            $activeBatch = \App\Models\Batch::where('user_id', $user->id)
                ->where('status', \App\Models\Batch::STATUS_PROCESSING)
                ->whereHas('videoJobs', function ($query) {
                    $query->where('generator', 'deforum')
                        ->whereIn('status', [
                            \App\Models\Videojob::STATUS_PROCESSING,
                            \App\Models\Videojob::STATUS_PREPROCESSING,
                        ]);
                })
                ->with(['videoJobs' => function ($query) {
                    $query->where('generator', 'deforum')
                        ->whereIn('status', [
                            \App\Models\Videojob::STATUS_PROCESSING,
                            \App\Models\Videojob::STATUS_PREPROCESSING,
                        ])
                        ->orderBy('id', 'desc')
                        ->limit(1);
                }])
                ->first();

            if ($activeBatch && $activeBatch->videoJobs->isNotEmpty()) {
                $activeJob = $activeBatch->videoJobs->first();
                $activeSession = [
                    'active' => true,
                    'batch_id' => $activeBatch->id,
                    'video_job_id' => $activeJob->id,
                    'status' => $activeJob->status,
                    'started_at' => $activeBatch->started_at?->toIso8601String(),
                    'progress' => $activeJob->progress ?? 0,
                ];
            }

            return response()->json([
                'session' => $activeSession,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting Deforum live status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get live status: ' . $e->getMessage()
            ], 500);
        }
    }
}

