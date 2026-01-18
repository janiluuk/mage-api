<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Videojob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for story generation API
 */
class StoryController extends Controller
{
    /**
     * Start story generation
     * POST /api/v1/story/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'config' => 'required|array',
            'extendFrom' => 'nullable|integer|exists:batches,id',
            'frame_count' => 'nullable|integer|min:1|max:1000',
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            // Create or extend batch
            if (!empty($validated['extendFrom'])) {
                $sourceBatch = Batch::where('user_id', $user->id)
                    ->findOrFail($validated['extendFrom']);
                
                $batch = Batch::create([
                    'user_id' => $user->id,
                    'name' => $validated['name'] ?? 'Extended Story',
                    'description' => $validated['description'] ?? null,
                    'settings' => array_merge($sourceBatch->settings ?? [], $validated['config']),
                    'status' => Batch::STATUS_PENDING,
                ]);

                Log::info('Extended story batch created', [
                    'batch_id' => $batch->id,
                    'source_batch_id' => $sourceBatch->id,
                    'user_id' => $user->id
                ]);
            } else {
                $batch = Batch::create([
                    'user_id' => $user->id,
                    'name' => $validated['name'] ?? 'New Story',
                    'description' => $validated['description'] ?? null,
                    'settings' => $validated['config'],
                    'status' => Batch::STATUS_PENDING,
                ]);

                Log::info('Story batch created', [
                    'batch_id' => $batch->id,
                    'user_id' => $user->id
                ]);
            }

            // Create video jobs for story generation
            $frameCount = $validated['frame_count'] ?? 10;
            $jobs = [];

            for ($i = 0; $i < $frameCount; $i++) {
                $videoJob = Videojob::create([
                    'user_id' => $user->id,
                    'filename' => sprintf('story_%d_frame_%d', $batch->id, $i),
                    'original_filename' => sprintf('frame_%d.mp4', $i),
                    'prompt' => $validated['config']['prompt'] ?? '',
                    'generator' => 'deforum',
                    'status' => Videojob::STATUS_PENDING,
                    'generation_parameters' => array_merge($validated['config'], [
                        'frame_index' => $i,
                        'frame_count' => $frameCount,
                        'batch_id' => $batch->id,
                    ]),
                    'frame_count' => 1,
                ]);

                $batch->videoJobs()->attach($videoJob->id, [
                    'order' => $i + 1,
                    'status' => 'pending',
                ]);

                $jobs[] = $videoJob;
            }

            $batch->total_jobs = count($jobs);
            $batch->save();

            DB::commit();

            return response()->json([
                'message' => 'Story generation started',
                'batch' => $batch->load('videoJobs'),
                'batchId' => $batch->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error starting story generation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to start story generation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch status
     * GET /api/v1/story/batch/{batchId}
     */
    public function getBatchStatus(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->with(['videoJobs' => function ($query) {
                $query->select([
                    'video_jobs.id',
                    'video_jobs.filename',
                    'video_jobs.status',
                    'video_jobs.progress',
                    'video_jobs.preview_img',
                    'video_jobs.thumbnail',
                    'video_jobs.url',
                    'video_jobs.generation_parameters',
                ]);
            }])
            ->findOrFail($batchId);

        // Update progress
        if (method_exists($batch, 'updateProgress')) {
            $batch->updateProgress();
        }

        $response = [
            'id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'progress' => $batch->progress ?? 0,
            'total_jobs' => $batch->total_jobs ?? 0,
            'completed_jobs' => $batch->completed_jobs ?? 0,
            'failed_jobs' => $batch->failed_jobs ?? 0,
            'created_at' => $batch->created_at,
            'updated_at' => $batch->updated_at,
            'jobs' => $batch->videoJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'filename' => $job->filename,
                    'status' => $job->status,
                    'progress' => $job->progress ?? 0,
                    'preview_img' => $job->preview_img,
                    'thumbnail' => $job->thumbnail,
                    'url' => $job->url,
                    'frame_index' => $job->generation_parameters['frame_index'] ?? null,
                    'order' => $job->pivot->order ?? null,
                    'description' => $job->pivot->description ?? '',
                ];
            })->sortBy(function ($job) {
                return $job['order'] ?? $job['frame_index'] ?? 0;
            })->values(),
        ];

        return response()->json($response);
    }

    /**
     * Pause batch processing
     * POST /api/v1/story/batch/{batchId}/pause
     */
    public function pauseBatch(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->findOrFail($batchId);

        if ($batch->status !== Batch::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Batch is not processing'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $batch->status = 'paused';
            $batch->save();

            // Update pivot statuses to paused for pending jobs
            $batch->videoJobs()->wherePivot('status', 'pending')
                ->orWherePivot('status', 'processing')
                ->updateExistingPivot(
                    $batch->videoJobs()->pluck('video_jobs.id')->toArray(),
                    ['status' => 'paused']
                );

            DB::commit();

            Log::info('Story batch paused', ['batch_id' => $batchId]);

            return response()->json([
                'message' => 'Batch paused successfully',
                'batch' => $batch->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error pausing batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to pause batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume batch processing
     * POST /api/v1/story/batch/{batchId}/resume
     */
    public function resumeBatch(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->findOrFail($batchId);

        if ($batch->status !== 'paused') {
            return response()->json([
                'message' => 'Batch is not paused'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $batch->status = Batch::STATUS_PROCESSING;
            $batch->save();

            // Update pivot statuses back to pending for paused jobs
            $batch->videoJobs()->wherePivot('status', 'paused')
                ->updateExistingPivot(
                    $batch->videoJobs()->pluck('video_jobs.id')->toArray(),
                    ['status' => 'pending']
                );

            DB::commit();

            Log::info('Story batch resumed', ['batch_id' => $batchId]);

            return response()->json([
                'message' => 'Batch resumed successfully',
                'batch' => $batch->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error resuming batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to resume batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel batch
     * DELETE /api/v1/story/batch/{batchId}
     */
    public function cancelBatch(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::where('user_id', $request->user()->id)
            ->findOrFail($batchId);

        if (in_array($batch->status, [Batch::STATUS_COMPLETED, Batch::STATUS_CANCELLED])) {
            return response()->json([
                'message' => 'Batch is already completed or cancelled'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $batch->status = Batch::STATUS_CANCELLED;
            $batch->completed_at = now();
            $batch->save();

            // Cancel all video jobs
            $batch->videoJobs()->each(function ($job) {
                if (!in_array($job->status, [Videojob::STATUS_FINISHED, Videojob::STATUS_ERROR])) {
                    $job->status = Videojob::STATUS_CANCELLED;
                    $job->save();
                }
            });

            DB::commit();

            Log::info('Story batch cancelled', ['batch_id' => $batchId]);

            return response()->json([
                'message' => 'Batch cancelled successfully',
                'batch' => $batch->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error cancelling batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to cancel batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Persist frame from generation
     * POST /api/v1/story/batch/{batchId}/frames
     */
    public function persistFrame(Request $request, int $batchId): JsonResponse
    {
        $validated = $request->validate([
            'video_job_id' => 'required|integer|exists:video_jobs,id',
            'frame_data' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $batch = Batch::where('user_id', $request->user()->id)
            ->findOrFail($batchId);

        $videoJob = Videojob::where('id', $validated['video_job_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Verify job belongs to batch
        if (!$batch->videoJobs()->where('video_job_id', $videoJob->id)->exists()) {
            return response()->json([
                'message' => 'Video job does not belong to this batch'
            ], 422);
        }

        try {
            // Update video job with frame data
            if (!empty($validated['frame_data'])) {
                $videoJob->generation_parameters = array_merge(
                    $videoJob->generation_parameters ?? [],
                    ['frame_data' => $validated['frame_data']]
                );
            }

            if (!empty($validated['metadata'])) {
                $videoJob->generation_parameters = array_merge(
                    $videoJob->generation_parameters ?? [],
                    ['metadata' => $validated['metadata']]
                );
            }

            $videoJob->save();

            Log::info('Frame persisted', [
                'batch_id' => $batchId,
                'video_job_id' => $videoJob->id
            ]);

            return response()->json([
                'message' => 'Frame persisted successfully',
                'video_job' => $videoJob,
            ]);

        } catch (\Exception $e) {
            Log::error('Error persisting frame', [
                'batch_id' => $batchId,
                'video_job_id' => $validated['video_job_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to persist frame: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create share link for story
     * POST /api/v1/story/share
     */
    public function createShareLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'required|integer|exists:batches,id',
            'expires_at' => 'nullable|date|after:now',
            'public' => 'nullable|boolean',
        ]);

        $batch = Batch::where('user_id', $request->user()->id)
            ->findOrFail($validated['batch_id']);

        try {
            // Generate unique share token
            $shareToken = bin2hex(random_bytes(32));

            // Store share link in batch settings
            $settings = $batch->settings ?? [];
            $settings['share'] = [
                'token' => $shareToken,
                'created_at' => now()->toIso8601String(),
                'expires_at' => $validated['expires_at'] ?? null,
                'public' => $validated['public'] ?? false,
                'url' => url("/story/share/{$shareToken}"),
            ];

            $batch->settings = $settings;
            $batch->save();

            Log::info('Share link created', [
                'batch_id' => $batch->id,
                'token' => $shareToken
            ]);

            return response()->json([
                'message' => 'Share link created successfully',
                'share' => $settings['share'],
                'batch' => $batch,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating share link', [
                'batch_id' => $validated['batch_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create share link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all stories for the authenticated user
     * GET /api/v1/story
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Batch::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%' . $request->input('search') . '%');
        }

        $stories = $query->paginate($request->input('per_page', 15));

        return response()->json($stories);
    }

    /**
     * Get a single story with jobs for editing
     * GET /api/v1/story/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $story = Batch::where('user_id', $user->id)
            ->with(['videoJobs' => function ($query) {
                $query->orderBy('batch_video_job.order');
            }])
            ->findOrFail($id);

        // Update progress
        if (method_exists($story, 'updateProgress')) {
            $story->updateProgress();
        }

        $response = [
            'id' => $story->id,
            'name' => $story->name,
            'description' => $story->description,
            'status' => $story->status,
            'progress' => $story->progress ?? 0,
            'total_jobs' => $story->total_jobs ?? 0,
            'completed_jobs' => $story->completed_jobs ?? 0,
            'failed_jobs' => $story->failed_jobs ?? 0,
            'created_at' => $story->created_at,
            'updated_at' => $story->updated_at,
            'jobs' => $story->videoJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'filename' => $job->filename,
                    'original_filename' => $job->original_filename,
                    'status' => $job->status,
                    'progress' => $job->progress ?? 0,
                    'preview_img' => $job->preview_img,
                    'thumbnail' => $job->thumbnail,
                    'url' => $job->url,
                    'prompt' => $job->prompt,
                    'order' => $job->pivot->order,
                    'description' => $job->pivot->description ?? '',
                    'pivot_status' => $job->pivot->status,
                ];
            })->values(),
        ];

        return response()->json($response);
    }

    /**
     * Update story metadata
     * PUT /api/v1/story/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
        ]);

        $story = Batch::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $story->update($validated);

        return response()->json([
            'message' => 'Story updated successfully',
            'story' => $story,
        ]);
    }

    /**
     * Update job order in story
     * PUT /api/v1/story/{id}/jobs/order
     */
    public function updateJobOrder(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'job_orders' => 'required|array',
            'job_orders.*.job_id' => 'required|integer|exists:video_jobs,id',
            'job_orders.*.order' => 'required|integer|min:1',
            'job_orders.*.description' => 'nullable|string',
        ]);

        $story = Batch::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Verify all jobs belong to the user and story
            $jobIds = collect($validated['job_orders'])->pluck('job_id')->toArray();
            $userJobIds = Videojob::where('user_id', $request->user()->id)
                ->whereIn('id', $jobIds)
                ->pluck('id')
                ->toArray();

            if (count($userJobIds) !== count($jobIds)) {
                return response()->json([
                    'message' => 'One or more jobs do not belong to you'
                ], 422);
            }

            // Update order for each job
            foreach ($validated['job_orders'] as $item) {
                $updateData = ['order' => $item['order']];
                
                if (isset($item['description'])) {
                    $updateData['description'] = $item['description'];
                }

                $story->videoJobs()->updateExistingPivot($item['job_id'], $updateData);
            }

            DB::commit();

            Log::info('Story job order updated', [
                'story_id' => $id,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'message' => 'Job order updated successfully',
                'story' => $story->fresh()->load('videoJobs'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating story job order', [
                'story_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update job order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign jobs to story
     * POST /api/v1/story/{id}/jobs
     */
    public function assignJobs(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'job_ids' => 'required|array',
            'job_ids.*' => 'integer|exists:video_jobs,id',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string',
        ]);

        $story = Batch::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $userId = $request->user()->id;
            $jobIds = $validated['job_ids'];

            // Verify all jobs belong to the user
            $jobs = Videojob::whereIn('id', $jobIds)
                ->where('user_id', $userId)
                ->get();

            if ($jobs->count() !== count($jobIds)) {
                return response()->json([
                    'message' => 'One or more jobs not found or not owned by user'
                ], 404);
            }

            // Get current max order
            $maxOrder = $story->videoJobs()->max('batch_video_job.order') ?? 0;

            // Attach jobs with order and description
            foreach ($jobIds as $index => $jobId) {
                if (!$story->videoJobs()->where('video_job_id', $jobId)->exists()) {
                    $attachData = [
                        'order' => $maxOrder + $index + 1,
                        'status' => 'pending',
                    ];

                    if (isset($validated['descriptions'][$jobId])) {
                        $attachData['description'] = $validated['descriptions'][$jobId];
                    }

                    $story->videoJobs()->attach($jobId, $attachData);
                }
            }

            $story->total_jobs = $story->videoJobs()->count();
            $story->save();

            DB::commit();

            Log::info('Jobs assigned to story', [
                'story_id' => $id,
                'job_count' => count($jobIds),
                'user_id' => $userId
            ]);

            return response()->json([
                'message' => 'Jobs assigned to story successfully',
                'story' => $story->fresh()->load('videoJobs'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning jobs to story', [
                'story_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to assign jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove jobs from story
     * DELETE /api/v1/story/{id}/jobs
     */
    public function removeJobs(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'job_ids' => 'required|array',
            'job_ids.*' => 'integer',
        ]);

        $story = Batch::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $story->videoJobs()->detach($validated['job_ids']);
            $story->total_jobs = $story->videoJobs()->count();
            $story->save();

            Log::info('Jobs removed from story', [
                'story_id' => $id,
                'job_count' => count($validated['job_ids'])
            ]);

            return response()->json([
                'message' => 'Jobs removed from story successfully',
                'story' => $story->fresh()->load('videoJobs'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing jobs from story', [
                'story_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to remove jobs: ' . $e->getMessage()
            ], 500);
        }
    }
}

