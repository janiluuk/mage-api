<?php

namespace App\Http\Controllers;

use App\Services\Audio\AudioGenerationService;
use App\Services\Audio\AudioQueueManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AudioController extends Controller
{
    private AudioGenerationService $audioGenerationService;
    private AudioQueueManager $queueManager;

    public function __construct(
        AudioGenerationService $audioGenerationService,
        AudioQueueManager $queueManager
    ) {
        $this->audioGenerationService = $audioGenerationService;
        $this->queueManager = $queueManager;
    }

    /**
     * Stream audio generation endpoint.
     * GET /api/stream?text=...
     */
    public function stream(Request $request): Response
    {
        $text = $request->input('text', '');

        // Validate input
        if (!is_string($text)) {
            return response()->json(['error' => 'Text parameter must be a string'], 400);
        }

        if (strlen($text) > 1000) {
            return response()->json(['error' => 'Text parameter exceeds maximum length of 1000 characters'], 400);
        }

        $job = $this->queueManager->enqueue(['text' => $text]);

        try {
            $this->queueManager->markProcessing($job['id']);

            $comfyHost = config('services.comfy.host', '127.0.0.1:8188');

            // Generate audio
            $processedAudio = $this->audioGenerationService->generateAudio($text, $comfyHost);
            $this->queueManager->markComplete($job['id']);

            // Stream the audio response
            return response($processedAudio, 200, [
                'Content-Type' => 'audio/aac',
                'X-Job-Id' => $job['id'],
            ]);
        } catch (\Exception $e) {
            $this->queueManager->markFailed($job['id'], $e);
            Log::error('Audio generation error', [
                'job_id' => $job['id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error generating audio',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get queue status.
     * GET /api/status
     */
    public function status(): JsonResponse
    {
        return response()->json($this->queueManager->getStatus());
    }

    /**
     * Get queue details.
     * GET /api/audio-queue
     */
    public function queue(): JsonResponse
    {
        return response()->json($this->queueManager->getQueue());
    }

    /**
     * Get configuration.
     * GET /api/config
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'stableUrl' => config('services.stable.url', ''),
        ]);
    }
}
