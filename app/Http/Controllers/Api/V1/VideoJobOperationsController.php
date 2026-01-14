<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Videojob;
use App\Models\UserFile;
use App\Services\FileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Controller for video job operations like soundtrack, extend, and trim
 */
class VideoJobOperationsController extends Controller
{
    public function __construct(
        private readonly FileManager $fileManager
    ) {
    }

    /**
     * Add soundtrack to a video job
     * POST /api/v1/video-jobs/add-soundtrack
     */
    public function addSoundtrack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_job_id' => 'required|integer|exists:video_jobs,id',
            'soundtrack' => 'required|file|mimes:mp3,aac,wav,ogg,flac|max:51200',
            'start_seconds' => 'nullable|numeric|min:0',
            'end_seconds' => 'nullable|numeric|gt:start_seconds',
            'output_name' => 'nullable|string|max:255',
        ]);

        $videoJob = Videojob::findOrFail($validated['video_job_id']);

        // Check authorization
        if ($videoJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if video job has finished media
        $finishedMedia = $videoJob->getFirstMedia('finished');
        if (!$finishedMedia) {
            return response()->json([
                'message' => 'Video job must be completed before adding soundtrack'
            ], 422);
        }

        // Store the soundtrack file temporarily
        $soundtrack = $request->file('soundtrack');
        $soundtrackPath = $soundtrack->store('soundtracks', 'public');
        $absoluteSoundtrackPath = Storage::disk('public')->path($soundtrackPath);

        try {
            // Get video file path
            $videoPath = $finishedMedia->getPath();
            
            // Create output filename
            $outputName = $validated['output_name'] ?? pathinfo($finishedMedia->file_name, PATHINFO_FILENAME) . '_with_audio';
            $outputPath = Storage::disk('public')->path('videos/' . uniqid() . '_' . $outputName . '.mp4');
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Build FFmpeg command
            $ffmpegCmd = $this->buildSoundtrackCommand(
                $videoPath,
                $absoluteSoundtrackPath,
                $outputPath,
                $validated['start_seconds'] ?? null,
                $validated['end_seconds'] ?? null
            );

            Log::info('Adding soundtrack to video job', [
                'video_job_id' => $videoJob->id,
                'command' => implode(' ', $ffmpegCmd)
            ]);

            // Execute FFmpeg command using Symfony Process
            $process = new Process($ffmpegCmd);
            $process->setTimeout(600); // 10 minute timeout
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('FFmpeg soundtrack addition failed', [
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput()
                ]);
                throw new ProcessFailedException($process);
            }

            // Save as new media on the video job
            $videoJob->addMedia($outputPath)
                ->toMediaCollection('finished');

            // Update video job metadata - keep the original soundtrack reference
            // Note: We don't update soundtrack_path/url since the file was temporary
            // The soundtrack is now embedded in the video file
            $videoJob->soundtrack_start_seconds = $validated['start_seconds'] ?? null;
            $videoJob->soundtrack_end_seconds = $validated['end_seconds'] ?? null;
            $videoJob->save();

            // Clean up temporary soundtrack file after successful processing
            Storage::disk('public')->delete($soundtrackPath);

            return response()->json([
                'message' => 'Soundtrack added successfully',
                'video_job_id' => $videoJob->id,
                'video_url' => $videoJob->finished_url,
            ]);

        } catch (\Exception $e) {
            // Clean up on error
            Storage::disk('public')->delete($soundtrackPath);
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error adding soundtrack', [
                'error' => $e->getMessage(),
                'video_job_id' => $videoJob->id
            ]);

            return response()->json([
                'message' => 'Failed to add soundtrack: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend a video job (create continuation)
     * POST /api/v1/video-jobs/extend
     */
    public function extend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_job_id' => 'required|integer|exists:video_jobs,id',
            'length' => 'nullable|numeric|between:1,20',
            'prompt' => 'nullable|string|max:2000',
            'negative_prompt' => 'nullable|string|max:2000',
            'seed' => 'nullable|integer',
            'denoising' => 'nullable|numeric|between:0,1',
        ]);

        $baseJob = Videojob::findOrFail($validated['video_job_id']);

        // Check authorization
        if ($baseJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only deforum jobs can be extended
        if ($baseJob->generator !== 'deforum') {
            return response()->json([
                'message' => 'Only deforum jobs can be extended'
            ], 422);
        }

        // Check if base job is completed
        if ($baseJob->status !== Videojob::STATUS_FINISHED) {
            return response()->json([
                'message' => 'Base video job must be completed before extension'
            ], 422);
        }

        try {
            // Create new video job as extension
            $newJob = new Videojob();
            
            // Inherit properties from base job
            $persistedParameters = [];
            if (!empty($baseJob->generation_parameters)) {
                $persistedParameters = json_decode((string) $baseJob->generation_parameters, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Failed to decode generation_parameters', [
                        'job_id' => $baseJob->id,
                        'error' => json_last_error_msg()
                    ]);
                    $persistedParameters = [];
                }
            }
            
            $newJob->user_id = $request->user()->id;
            $newJob->generator = 'deforum';
            $newJob->model_id = $persistedParameters['model_id'] ?? $baseJob->model_id;
            $newJob->prompt = $validated['prompt'] ?? $persistedParameters['prompts']['positive'] ?? $baseJob->prompt;
            $newJob->negative_prompt = $validated['negative_prompt'] ?? $persistedParameters['prompts']['negative'] ?? $baseJob->negative_prompt;
            $newJob->length = $validated['length'] ?? $persistedParameters['length'] ?? $baseJob->length;
            $newJob->seed = $validated['seed'] ?? $persistedParameters['seed'] ?? $baseJob->seed;
            $newJob->denoising = $validated['denoising'] ?? $persistedParameters['denoising'] ?? $baseJob->denoising;
            $newJob->fps = $persistedParameters['fps'] ?? $baseJob->fps ?? 24;
            $newJob->frame_count = $persistedParameters['frame_count'] ?? $baseJob->frame_count;
            $newJob->width = $baseJob->width;
            $newJob->height = $baseJob->height;
            $newJob->status = Videojob::STATUS_PENDING;
            
            // Store reference to base job
            $extensionMetadata = [
                'extended_from_job_id' => $baseJob->id,
                'is_extension' => true,
            ];
            $newJob->generation_parameters = json_encode($extensionMetadata);
            
            $newJob->save();

            Log::info('Created video job extension', [
                'new_job_id' => $newJob->id,
                'base_job_id' => $baseJob->id,
            ]);

            return response()->json([
                'message' => 'Video job extension created successfully',
                'video_job_id' => $newJob->id,
                'base_job_id' => $baseJob->id,
                'status' => $newJob->status,
                'extended_from' => $baseJob->id,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating video job extension', [
                'error' => $e->getMessage(),
                'base_job_id' => $baseJob->id
            ]);

            return response()->json([
                'message' => 'Failed to create extension: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trim/clip a video job
     * POST /api/v1/video-jobs/trim
     */
    public function trim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_job_id' => 'required|integer|exists:video_jobs,id',
            'start_seconds' => 'required|numeric|min:0',
            'end_seconds' => 'required|numeric|gt:start_seconds',
            'output_name' => 'nullable|string|max:255',
        ]);

        $videoJob = Videojob::findOrFail($validated['video_job_id']);

        // Check authorization
        if ($videoJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if video job has finished media
        $finishedMedia = $videoJob->getFirstMedia('finished');
        if (!$finishedMedia) {
            return response()->json([
                'message' => 'Video job must be completed before trimming'
            ], 422);
        }

        try {
            $videoPath = $finishedMedia->getPath();
            $startSeconds = (float) $validated['start_seconds'];
            $endSeconds = (float) $validated['end_seconds'];
            $duration = $endSeconds - $startSeconds;

            // Validate trim range
            if ($duration <= 0) {
                return response()->json([
                    'message' => 'Invalid trim range: end_seconds must be greater than start_seconds'
                ], 422);
            }

            // Create output filename
            $outputName = $validated['output_name'] ?? pathinfo($finishedMedia->file_name, PATHINFO_FILENAME) . '_trimmed';
            $outputPath = Storage::disk('public')->path('videos/' . uniqid() . '_' . $outputName . '.mp4');
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Build FFmpeg trim command
            $ffmpegCmd = [
                'ffmpeg',
                '-i', $videoPath,
                '-ss', (string) $startSeconds,
                '-t', (string) $duration,
                '-c:v', 'libx264',
                '-c:a', 'aac',
                '-strict', 'experimental',
                $outputPath
            ];

            Log::info('Trimming video job', [
                'video_job_id' => $videoJob->id,
                'start' => $startSeconds,
                'end' => $endSeconds,
                'duration' => $duration,
                'command' => implode(' ', $ffmpegCmd)
            ]);

            // Execute FFmpeg command using Symfony Process
            $process = new Process($ffmpegCmd);
            $process->setTimeout(600); // 10 minute timeout
            $process->run();

            if (!$process->isSuccessful() || !file_exists($outputPath)) {
                Log::error('FFmpeg trim failed', [
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput()
                ]);
                throw new ProcessFailedException($process);
            }

            // Create new video job for trimmed version
            $trimmedJob = new Videojob();
            $trimmedJob->user_id = $request->user()->id;
            $trimmedJob->original_filename = $outputName . '.mp4';
            $trimmedJob->filename = basename($outputPath);
            $trimmedJob->status = Videojob::STATUS_FINISHED;
            $trimmedJob->generator = $videoJob->generator;
            $trimmedJob->model_id = $videoJob->model_id;
            $trimmedJob->prompt = $videoJob->prompt;
            $trimmedJob->negative_prompt = $videoJob->negative_prompt;
            $trimmedJob->seed = $videoJob->seed;
            $trimmedJob->width = $videoJob->width;
            $trimmedJob->height = $videoJob->height;
            
            // Store metadata about trimming
            $trimMetadata = [
                'trimmed_from_job_id' => $videoJob->id,
                'is_trimmed' => true,
                'trim_start' => $startSeconds,
                'trim_end' => $endSeconds,
                'trim_duration' => $duration,
            ];
            $trimmedJob->generation_parameters = json_encode($trimMetadata);
            
            $trimmedJob->save();

            // Add media to the new job
            $trimmedJob->addMedia($outputPath)
                ->toMediaCollection('finished');

            Log::info('Video trimmed successfully', [
                'original_job_id' => $videoJob->id,
                'trimmed_job_id' => $trimmedJob->id,
            ]);

            return response()->json([
                'message' => 'Video trimmed successfully',
                'video_job_id' => $trimmedJob->id,
                'original_job_id' => $videoJob->id,
                'video_url' => $trimmedJob->finished_url,
                'trim_info' => [
                    'start_seconds' => $startSeconds,
                    'end_seconds' => $endSeconds,
                    'duration' => $duration,
                ],
            ], 201);

        } catch (\Exception $e) {
            // Clean up on error
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error trimming video', [
                'error' => $e->getMessage(),
                'video_job_id' => $videoJob->id
            ]);

            return response()->json([
                'message' => 'Failed to trim video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build FFmpeg command for adding soundtrack
     */
    private function buildSoundtrackCommand(
        string $videoPath,
        string $audioPath,
        string $outputPath,
        ?float $startSeconds = null,
        ?float $endSeconds = null
    ): array {
        $cmd = ['ffmpeg', '-i', $videoPath];

        // If audio timing is specified, trim the audio before mixing
        if ($startSeconds !== null && $endSeconds !== null) {
            $duration = $endSeconds - $startSeconds;
            $cmd = array_merge($cmd, [
                '-ss', (string) $startSeconds,
                '-t', (string) $duration,
                '-i', $audioPath,
            ]);
        } elseif ($startSeconds !== null) {
            $cmd = array_merge($cmd, [
                '-ss', (string) $startSeconds,
                '-i', $audioPath,
            ]);
        } else {
            // No trimming, just add the audio
            $cmd = array_merge($cmd, ['-i', $audioPath]);
        }

        // Mix video and audio, using shortest stream
        $cmd = array_merge($cmd, [
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-strict', 'experimental',
            '-map', '0:v:0',
            '-map', '1:a:0',
            '-shortest',
            $outputPath
        ]);

        return $cmd;
    }
}
