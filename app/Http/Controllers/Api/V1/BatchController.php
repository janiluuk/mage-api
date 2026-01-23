<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Videojob;
use App\Jobs\ProcessDeforumJob;
use App\Jobs\ProcessVideoJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for batch processing video jobs
 */
class BatchController extends Controller
{
    /**
     * List all batches for the authenticated user
     * GET /api/v1/batches
     */
    public function index(Request $request): JsonResponse
    {
        $query = Batch::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $batches = $query->paginate($request->input('per_page', 15));

        return response()->json($batches);
    }

    /**
     * Create a new batch
     * POST /api/v1/batches
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $batch = Batch::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'settings' => $validated['settings'] ?? [],
            'status' => Batch::STATUS_PENDING,
        ]);

        Log::info('Batch created', ['batch_id' => $batch->id, 'user_id' => $request->user()->id]);

        return response()->json([
            'message' => 'Batch created successfully',
            'batch' => $batch,
        ], 201);
    }

    /**
     * Get a specific batch
     * GET /api/v1/batches/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->with(['videoJobs'])
            ->findOrFail($id);

        return response()->json($batch);
    }

    /**
     * Update a batch
     * PUT /api/v1/batches/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)->findOrFail($id);

        // Don't allow updating if processing
        if ($batch->status === Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Cannot update batch while processing'
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'settings' => 'sometimes|nullable|array',
        ]);

        $batch->update($validated);

        return response()->json([
            'message' => 'Batch updated successfully',
            'batch' => $batch,
        ]);
    }

    /**
     * Delete a batch
     * DELETE /api/v1/batches/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)->findOrFail($id);

        // Don't allow deleting if processing
        if ($batch->status === Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Cannot delete batch while processing'
            ], 422);
        }

        $batch->delete();

        return response()->json([
            'message' => 'Batch deleted successfully',
        ]);
    }

    /**
     * Add video jobs to a batch
     * POST /api/v1/batches/{id}/jobs
     */
    public function addJobs(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)->findOrFail($id);

        if ($batch->status === Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Cannot add jobs to batch while processing'
            ], 422);
        }

        $validated = $request->validate([
            'video_job_ids' => 'required|array',
            'video_job_ids.*' => 'integer|exists:video_jobs,id',
        ]);

        $userId = $request->user()->id;
        $videoJobIds = $validated['video_job_ids'];

        // Verify all jobs belong to the user
        $jobs = Videojob::whereIn('id', $videoJobIds)
            ->where('user_id', $userId)
            ->get();

        if ($jobs->count() !== count($videoJobIds)) {
            return response()->json([
                'message' => 'One or more video jobs not found or not owned by user'
            ], 404);
        }

        // Get current max order
        $maxOrder = $batch->videoJobs()->max('batch_video_job.order') ?? 0;

        // Attach jobs with order
        foreach ($videoJobIds as $index => $jobId) {
            if (!$batch->videoJobs()->where('video_job_id', $jobId)->exists()) {
                $batch->videoJobs()->attach($jobId, [
                    'order' => $maxOrder + $index + 1,
                    'status' => 'pending',
                ]);
            }
        }

        $batch->total_jobs = $batch->videoJobs()->count();
        $batch->save();

        return response()->json([
            'message' => 'Video jobs added to batch',
            'batch' => $batch->load('videoJobs'),
        ]);
    }

    /**
     * Remove video jobs from a batch
     * DELETE /api/v1/batches/{id}/jobs
     */
    public function removeJobs(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)->findOrFail($id);

        if ($batch->status === Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Cannot remove jobs from batch while processing'
            ], 422);
        }

        $validated = $request->validate([
            'video_job_ids' => 'required|array',
            'video_job_ids.*' => 'integer',
        ]);

        $batch->videoJobs()->detach($validated['video_job_ids']);
        $batch->total_jobs = $batch->videoJobs()->count();
        $batch->save();

        return response()->json([
            'message' => 'Video jobs removed from batch',
            'batch' => $batch->load('videoJobs'),
        ]);
    }

    /**
     * Start batch processing
     * POST /api/v1/batches/{id}/process
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)->findOrFail($id);

        if ($batch->status === Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Batch is already processing'
            ], 422);
        }

        if ($batch->videoJobs()->count() === 0) {
            return response()->json([
                'message' => 'Batch has no video jobs'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $batch->status = Batch::STATUS_PROCESSING;
            $batch->started_at = now();
            $batch->save();

            // Queue all jobs in the batch
            $jobs = $batch->videoJobs()->get();
            
            foreach ($jobs as $job) {
                // Update pivot status to processing
                $batch->videoJobs()->updateExistingPivot($job->id, [
                    'status' => 'processing',
                    'started_at' => now(),
                ]);

                // Dispatch appropriate job based on generator type
                if ($job->generator === 'deforum') {
                    ProcessDeforumJob::dispatch($job, $job->frame_count ?? 1)->onQueue('medium');
                } else {
                    ProcessVideoJob::dispatch($job)->onQueue('medium');
                }
            }

            DB::commit();

            Log::info('Batch processing started', [
                'batch_id' => $batch->id,
                'job_count' => $jobs->count()
            ]);

            return response()->json([
                'message' => 'Batch processing started',
                'batch' => $batch->fresh()->load('videoJobs'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error starting batch processing', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to start batch processing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch status and progress
     * GET /api/v1/batches/{id}/status
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->with(['videoJobs' => function ($query) {
                $query->select([
                    'video_jobs.id',
                    'video_jobs.filename',
                    'video_jobs.status',
                    'video_jobs.progress',
                    'video_jobs.estimated_time_left',
                ]);
            }])
            ->findOrFail($id);

        // Update progress
        $batch->updateProgress();

        $response = [
            'id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'progress' => $batch->progress,
            'total_jobs' => $batch->total_jobs,
            'completed_jobs' => $batch->completed_jobs,
            'failed_jobs' => $batch->failed_jobs,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'jobs' => $batch->videoJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'filename' => $job->filename,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'estimated_time_left' => $job->estimated_time_left,
                    'pivot_status' => $job->pivot->status,
                    'pivot_started_at' => $job->pivot->started_at,
                    'pivot_completed_at' => $job->pivot->completed_at,
                ];
            }),
        ];

        return response()->json($response);
    }
}
