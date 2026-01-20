<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VideoExportJob;
use App\Services\VideoExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VideoExportController extends Controller
{
    public function __construct(
        private readonly VideoExportService $exportService
    ) {
    }

    /**
     * Create a new video export job
     * POST /api/v1/video-export
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fragments' => 'required|array|min:1',
            'fragments.*.videoId' => 'nullable|string',
            'fragments.*.videoUrl' => 'required|url',
            'fragments.*.start' => 'required|numeric|min:0|max:1',
            'fragments.*.end' => 'required|numeric|min:0|max:1|gt:fragments.*.start',
            'fragments.*.playbackRate' => 'nullable|numeric|min:0.25|max:4',
            'fragments.*.volume' => 'nullable|numeric|min:0|max:1',
            'fragments.*.hasAudio' => 'nullable|boolean',
            'inputFiles' => 'required|array|min:1',
            'inputFiles.*.url' => 'required|url',
            'inputFiles.*.index' => 'required|integer',
            'inputFiles.*.id' => 'nullable|string',
            'filterGraph' => 'required|array',
            'outputs' => 'required|array',
            'exportOptions' => 'nullable|array',
            'exportOptions.fps' => 'nullable|numeric|min:1|max:120',
            'exportOptions.bitrate' => 'nullable|numeric|min:0.1',
            'exportOptions.width' => 'nullable|integer|min:1|max:7680',
            'exportOptions.height' => 'nullable|integer|min:1|max:4320',
            'exportOptions.customResolution' => 'nullable|boolean',
            'exportOptions.interpolate' => 'nullable|boolean',
            'outputName' => 'nullable|string|max:255',
        ]);

        try {
            // Create export job
            $job = VideoExportJob::create([
                'user_id' => $request->user()->id,
                'status' => VideoExportJob::STATUS_PENDING,
                'fragments' => $validated['fragments'],
                'input_files' => $validated['inputFiles'],
                'filter_graph' => $validated['filterGraph'],
                'outputs' => $validated['outputs'],
                'export_options' => $validated['exportOptions'] ?? [],
                'output_name' => $validated['outputName'] ?? 'exported-video.mp4',
            ]);

            // Process job asynchronously
            // TODO: Use Laravel queue system for better job management
            // For now, start processing in background using exec
            $service = app(VideoExportService::class);
            $jobId = $job->id;
            
            // Process job in background
            // TODO: Use Laravel queue system (Queue::push()) for production
            // For now, use exec to run in background on Linux/Unix systems
            if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $artisanPath = base_path('artisan');
                $command = sprintf(
                    'cd %s && nohup php %s video:export:process %d >> storage/logs/video-export.log 2>&1 &',
                    escapeshellarg(base_path()),
                    escapeshellarg($artisanPath),
                    $jobId
                );
                exec($command);
            } else {
                // Fallback: Process synchronously (blocks the request)
                // In production, this should use Laravel queues instead
                // Using dispatch()->afterResponse() would require a queue worker
                try {
                    $service->process($job);
                } catch (\Exception $e) {
                    Log::error('Video export processing failed', [
                        'job_id' => $job->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'data' => [
                    'id' => (string) $job->id,
                    'type' => 'video-export-job',
                    'attributes' => [
                        'status' => $job->status,
                        'progress' => (float) $job->progress,
                        'created_at' => $job->created_at->toIso8601String(),
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create video export job', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Failed to create export job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get export job status
     * GET /api/v1/video-export/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $job = VideoExportJob::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $job->id,
                'type' => 'video-export-job',
                'attributes' => [
                    'status' => $job->status,
                    'progress' => (float) $job->progress,
                    'timemark' => $job->timemark,
                    'error' => $job->error,
                    'output' => $job->output_log ?? [],
                    'fileUrl' => $job->output_url,
                    'created_at' => $job->created_at->toIso8601String(),
                    'updated_at' => $job->updated_at->toIso8601String(),
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Cancel export job
     * DELETE /api/v1/video-export/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $job = VideoExportJob::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($job->status === VideoExportJob::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Cannot cancel completed job',
            ], 422);
        }

        if (in_array($job->status, [VideoExportJob::STATUS_FAILED, VideoExportJob::STATUS_CANCELLED])) {
            return response()->json([
                'message' => 'Job is already ' . $job->status,
            ], 422);
        }

        $this->exportService->cancel($job);

            return response()->json([
                'message' => 'Export job cancelled',
            ]);
    }

    /**
     * Stream export job progress via Server-Sent Events (SSE)
     * GET /api/v1/video-export/{id}/stream
     */
    public function stream(Request $request, $id): StreamedResponse
    {
        $job = VideoExportJob::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->stream(function () use ($job, $request) {
            // Set headers for SSE
            echo "data: " . json_encode([
                'id' => $job->id,
                'status' => $job->status,
                'progress' => (float) $job->progress,
                'timemark' => $job->timemark,
                'error' => $job->error,
                'output_url' => $job->output_url,
            ]) . "\n\n";
            flush();

            // Poll for updates
            $lastProgress = $job->progress;
            $maxPollTime = 3600; // 1 hour max
            $startTime = time();
            $pollInterval = 1; // 1 second

            while (true) {
                // Check timeout
                if (time() - $startTime > $maxPollTime) {
                    echo "data: " . json_encode([
                        'id' => $job->id,
                        'status' => 'timeout',
                        'message' => 'Stream timeout',
                    ]) . "\n\n";
                    flush();
                    break;
                }

                // Refresh job from database
                $job->refresh();

                // Send update if status changed or progress changed significantly
                if ($job->status !== VideoExportJob::STATUS_PROCESSING && 
                    $job->status !== VideoExportJob::STATUS_PENDING) {
                    // Job is complete, failed, or cancelled
                    echo "data: " . json_encode([
                        'id' => $job->id,
                        'status' => $job->status,
                        'progress' => (float) $job->progress,
                        'timemark' => $job->timemark,
                        'error' => $job->error,
                        'output_url' => $job->output_url,
                    ]) . "\n\n";
                    flush();
                    break;
                }

                // Check if progress changed significantly (0.5% threshold)
                if (abs((float) $job->progress - $lastProgress) >= 0.5) {
                    echo "data: " . json_encode([
                        'id' => $job->id,
                        'status' => $job->status,
                        'progress' => (float) $job->progress,
                        'timemark' => $job->timemark,
                        'output_log' => array_slice($job->output_log ?? [], -5), // Last 5 lines
                    ]) . "\n\n";
                    flush();
                    $lastProgress = (float) $job->progress;
                }

                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }

                sleep($pollInterval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
            'Connection' => 'keep-alive',
        ]);
    }
}
