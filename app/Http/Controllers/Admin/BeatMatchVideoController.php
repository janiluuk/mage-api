<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBeatMatchMusicVideoJob;
use App\Models\Videojob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BeatMatchVideoController extends Controller
{
    public function index()
    {
        return view('admin.beat-match-video');
    }

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio_file' => 'required|file|mimes:mp3,wav,aac,m4a|max:51200',
            'video_files' => 'required|array|min:1',
            'video_files.*' => 'required|file|mimes:mp4,mov,webm|max:200000',
            'cut_intensity' => 'required|integer|in:1,2,3',
            'direction' => 'nullable|string|in:forward,backward,random',
            'speed_factor' => 'nullable|numeric|min:0.1|max:2.0',
            'start_time' => 'nullable|numeric|min:0',
            'end_time' => 'nullable|numeric|gt:start_time',
        ]);

        try {
            // Store audio file
            $audioPath = $request->file('audio_file')->store('temp/beat-match', 'local');
            $audioFullPath = Storage::disk('local')->path($audioPath);

            // Store video files
            $videoFiles = [];
            foreach ($request->file('video_files') as $videoFile) {
                $videoPath = $videoFile->store('temp/beat-match', 'local');
                $videoFiles[] = Storage::disk('local')->path($videoPath);
            }

            // Generate output filename
            $outputFilename = 'beat-match-' . time() . '-' . uniqid() . '.mp4';

            // Create Videojob
            $videoJob = new Videojob();
            $videoJob->filename = 'beat-match-' . time();
            $videoJob->original_filename = $request->file('audio_file')->getClientOriginalName();
            $videoJob->outfile = $outputFilename;
            $videoJob->generator = 'beat-match';
            $videoJob->mimetype = 'video/mp4';
            $videoJob->user_id = auth()->id() ?? auth('api')->id();
            $videoJob->status = Videojob::STATUS_PENDING;
            $videoJob->queued_at = null;
            $videoJob->generation_parameters = [
                'audio_file' => $audioFullPath,
                'video_files' => $videoFiles,
                'cut_intensity' => $validated['cut_intensity'],
                'direction' => $validated['direction'] ?? 'random',
                'speed_factor' => (float)($validated['speed_factor'] ?? 1.0),
                'start_time' => (float)($validated['start_time'] ?? 0.0),
                'end_time' => isset($validated['end_time']) ? (float)$validated['end_time'] : null,
            ];
            $videoJob->save();

            // Queue the job
            $videoJob->resetProgress(Videojob::STATUS_APPROVED);
            $videoJob->queued_at = Carbon::now();
            $videoJob->save();

            ProcessBeatMatchMusicVideoJob::dispatch($videoJob)->onQueue('default');

            Log::info('Beat match music video job queued', [
                'job_id' => $videoJob->id,
                'audio_file' => $audioFullPath,
                'video_count' => count($videoFiles),
                'cut_intensity' => $validated['cut_intensity'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Beat match music video job queued successfully',
                'job_id' => $videoJob->id,
                'status' => $videoJob->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating beat match music video job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create beat match music video job: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function status($id): JsonResponse
    {
        $videoJob = Videojob::findOrFail($id);

        return response()->json([
            'id' => $videoJob->id,
            'status' => $videoJob->status,
            'progress' => $videoJob->progress,
            'estimated_time_left' => $videoJob->estimated_time_left,
            'job_time' => $videoJob->job_time,
            'url' => $videoJob->url,
            'error' => $videoJob->status === Videojob::STATUS_ERROR ? 'Processing failed' : null,
        ]);
    }
}

