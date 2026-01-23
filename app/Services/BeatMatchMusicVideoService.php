<?php

namespace App\Services;

use App\Models\Videojob;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;

class BeatMatchMusicVideoService
{
    /**
     * Start the beat matching music video process
     *
     * @param Videojob $videoJob
     * @return void
     */
    public function startProcess(Videojob $videoJob): void
    {
        $generationParameters = $videoJob->generation_parameters ?? [];
        
        // Extract parameters from new structure
        // Support both old structure (for backward compatibility) and new structure
        $inputFiles = $generationParameters['input_files'] ?? [];
        $options = $generationParameters['options'] ?? [];
        
        // Get audio and video files from new structure or fallback to old
        $audioFile = $inputFiles['audio_file'] ?? $generationParameters['audio_file'] ?? null;
        $videoFiles = $inputFiles['video_files'] ?? $generationParameters['video_files'] ?? [];
        
        // Get options from new structure or fallback to old (for backward compatibility)
        $cutIntensity = $options['cut_intensity'] ?? $generationParameters['cut_intensity'] ?? 1;
        $direction = $options['direction'] ?? $generationParameters['direction'] ?? 'random';
        $speedFactor = $options['speed_factor'] ?? $generationParameters['speed_factor'] ?? 1.0;
        $startTime = $options['start_time'] ?? $generationParameters['start_time'] ?? 0.0;
        $endTime = $options['end_time'] ?? $generationParameters['end_time'] ?? null;

        if (!$audioFile || empty($videoFiles)) {
            throw new \Exception('Audio file and video files are required');
        }

        // Verify files exist
        if (!file_exists($audioFile)) {
            throw new \Exception("Audio file not found: {$audioFile}");
        }

        foreach ($videoFiles as $videoFile) {
            if (!file_exists($videoFile)) {
                throw new \Exception("Video file not found: {$videoFile}");
            }
        }

        // Create temporary directory for videos
        $tempVideoDir = storage_path('app/temp/videos_' . $videoJob->id);
        if (!is_dir($tempVideoDir)) {
            mkdir($tempVideoDir, 0755, true);
        }

        // Copy video files to temp directory
        foreach ($videoFiles as $videoFile) {
            $destPath = $tempVideoDir . '/' . basename($videoFile);
            if (!copy($videoFile, $destPath)) {
                throw new \Exception("Failed to copy video file: {$videoFile}");
            }
        }

        // Prepare output file path
        $outputFile = implode("/", [
            config('app.paths.processed'),
            $videoJob->outfile
        ]);

        // Ensure output directory exists
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Build command
        $scriptPath = base_path('scripts/beat_match_music_video.py');
        $command = [
            'python3',
            $scriptPath,
            $audioFile,
            $tempVideoDir,
            (string)$cutIntensity,
            '--output', $outputFile,
            '--direction', $direction,
            '--speed-factor', (string)$speedFactor,
            '--start-time', (string)$startTime,
        ];

        if ($endTime !== null) {
            $command[] = '--end-time';
            $command[] = (string)$endTime;
        }

        Log::info('Executing beat match music video command', [
            'job_id' => $videoJob->id,
            'command' => implode(' ', $command)
        ]);

        // Execute Python script
        $process = new Process($command);
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->setIdleTimeout(600);

        try {
            $process->mustRun(function ($type, $buffer) use ($videoJob) {
                if (Process::ERR === $type) {
                    Log::error("Beat match music video process error", [
                        'job_id' => $videoJob->id,
                        'error' => $buffer
                    ]);
                } else {
                    Log::info("Beat match music video process output", [
                        'job_id' => $videoJob->id,
                        'output' => $buffer
                    ]);
                }
            });
        } catch (ProcessFailedException $e) {
            // Clean up temp directory
            $this->cleanupTempDirectory($tempVideoDir);
            
            throw new \Exception('Beat match music video processing failed: ' . $e->getMessage());
        }

        // Clean up temp directory
        $this->cleanupTempDirectory($tempVideoDir);

        // Verify output file exists
        if (!file_exists($outputFile)) {
            throw new \Exception("Output file was not created: {$outputFile}");
        }

        // Update video job with results
        // Note: $startTime variable (line 36) contains audio segment start time from options
        // Use $jobStartTime for the job processing timestamp to avoid variable shadowing
        $jobStartTime = strtotime($videoJob->queued_at ?: 'now');
        $videoJob->status = Videojob::STATUS_FINISHED;
        $videoJob->url = config('app.url') . '/processed/' . basename($videoJob->outfile);
        $videoJob->job_time = time() - $jobStartTime;
        $videoJob->progress = 100;
        $videoJob->estimated_time_left = 0;
        $videoJob->save();

        Log::info('Beat match music video processing completed successfully', [
            'job_id' => $videoJob->id,
            'output_file' => $outputFile
        ]);
    }

    /**
     * Clean up temporary directory
     *
     * @param string $dir
     * @return void
     */
    private function cleanupTempDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Timeout in seconds
     */
    private const TIMEOUT_SECONDS = 7200;
}

