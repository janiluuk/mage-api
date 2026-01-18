<?php

namespace App\Services;

use App\Models\Videojob;
use App\Models\UserFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioTrackSplitService
{
    private UVR5Client $uvr5Client;

    /**
     * Timeout in seconds
     */
    private const TIMEOUT_SECONDS = 7200;

    public function __construct(UVR5Client $uvr5Client = null)
    {
        $this->uvr5Client = $uvr5Client ?? new UVR5Client();
    }

    /**
     * Start the audio track splitting process
     *
     * @param Videojob $videoJob
     * @return void
     */
    public function startProcess(Videojob $videoJob): void
    {
        $generationParameters = $videoJob->generation_parameters ?? [];
        
        // Extract parameters from structure
        $inputFiles = $generationParameters['input_files'] ?? [];
        $options = $generationParameters['options'] ?? [];
        
        // Get input audio file
        $audioFile = $inputFiles['audio_file'] ?? $generationParameters['audio_file'] ?? null;
        $jobId = $generationParameters['job_id'] ?? $inputFiles['job_id'] ?? null;

        // If job_id provided, resolve the audio file from that job
        if ($jobId && !$audioFile) {
            $audioFile = $this->resolveAudioFileFromJob($jobId, $videoJob->user_id);
        }

        if (!$audioFile || !file_exists($audioFile)) {
            throw new \Exception('Audio file not found: ' . ($audioFile ?? 'null'));
        }

        // Get UVR5 options
        $model = $options['model'] ?? 'MDX-Net-InstVoc_HQ_3';
        $outputFormat = $options['output_format'] ?? 'wav';
        $vocalSplitMode = $options['vocal_split_mode'] ?? false;

        // Prepare output directory
        $outputDir = storage_path('app/temp/audio-split_' . $videoJob->id);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::info('Starting audio track split process', [
            'job_id' => $videoJob->id,
            'audio_file' => $audioFile,
            'model' => $model,
            'output_format' => $outputFormat,
        ]);

        try {
            // Call UVR5 to split audio
            $outputFiles = $this->uvr5Client->splitAudio($audioFile, [
                'model' => $model,
                'output_format' => $outputFormat,
                'vocal_split_mode' => $vocalSplitMode,
                'output_dir' => $outputDir,
            ]);

            // Move output files to processed directory and create UserFile entries
            $processedDirConfig = config('app.paths.processed');
            // Remove leading /storage/app if present to get relative path
            $processedDirRelative = str_replace('/storage/app/', '', $processedDirConfig);
            $processedDirRelative = str_replace('/storage/app', '', $processedDirRelative);
            if (empty($processedDirRelative) || $processedDirRelative === '/') {
                $processedDirRelative = 'processed';
            }
            $processedPath = storage_path('app/' . $processedDirRelative);
            if (!is_dir($processedPath)) {
                mkdir($processedPath, 0755, true);
            }

            $userFiles = [];
            foreach ($outputFiles as $outputFile) {
                $filename = 'audio-split-' . $videoJob->id . '-' . time() . '-' . basename($outputFile);
                $destination = $processedPath . '/' . $filename;

                if (!copy($outputFile, $destination)) {
                    throw new \Exception("Failed to copy output file: {$outputFile}");
                }

                // Determine mime type based on format
                $mimeType = $this->getMimeTypeForFormat($outputFormat);

                // Create UserFile entry for each output track
                // Path should be relative to disk root (storage/app for 'local' disk)
                $userFile = new UserFile();
                $userFile->user_id = $videoJob->user_id;
                $userFile->original_name = basename($outputFile);
                $userFile->disk = 'local';
                $userFile->path = $processedDirRelative . '/' . $filename;
                $userFile->size = filesize($destination);
                $userFile->mime_type = $mimeType;
                $userFile->type = 'audio';
                $userFile->save();

                $userFiles[] = [
                    'id' => $userFile->id,
                    'path' => $destination,
                    'url' => config('app.url') . '/processed/' . $filename,
                    'filename' => $filename,
                ];

                Log::info('Created output track file', [
                    'job_id' => $videoJob->id,
                    'user_file_id' => $userFile->id,
                    'filename' => $filename,
                ]);
            }

            // Clean up temp directory
            $this->cleanupTempDirectory($outputDir);

            // Update video job with results
            $startTime = strtotime($videoJob->queued_at ?: 'now');
            $videoJob->status = Videojob::STATUS_FINISHED;
            
            // Store output files info in generation_parameters
            $generationParameters['output_files'] = $userFiles;
            $videoJob->generation_parameters = $generationParameters;
            
            // If we have a main output file (e.g., vocals), set it as the primary output
            if (!empty($userFiles)) {
                $mainOutput = $userFiles[0];
                $videoJob->url = $mainOutput['url'];
                $videoJob->outfile = $mainOutput['filename'];
                $videoJob->mimetype = $this->getMimeTypeForFormat($outputFormat);
            }
            
            $videoJob->job_time = time() - $startTime;
            $videoJob->progress = 100;
            $videoJob->estimated_time_left = 0;
            $videoJob->save();

            Log::info('Audio track split processing completed successfully', [
                'job_id' => $videoJob->id,
                'output_files_count' => count($userFiles),
            ]);

        } catch (\Exception $e) {
            // Clean up temp directory on error
            $this->cleanupTempDirectory($outputDir);
            
            Log::error('Audio track split processing failed', [
                'job_id' => $videoJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Resolve audio file from a job_id
     */
    private function resolveAudioFileFromJob(int $jobId, int $userId): string
    {
        $sourceJob = Videojob::where('id', $jobId)
            ->where('user_id', $userId)
            ->first();

        if (!$sourceJob) {
            throw new \Exception("Source job not found or access denied: {$jobId}");
        }

        // Try to get the output file from the source job
        if ($sourceJob->outfile && $sourceJob->status === Videojob::STATUS_FINISHED) {
            $outputPath = storage_path('app' . config('app.paths.processed')) . '/' . basename($sourceJob->outfile);
            if (file_exists($outputPath)) {
                return $outputPath;
            }
        }

        // Try to get from generation_parameters input_files
        $generationParameters = $sourceJob->generation_parameters ?? [];
        $inputFiles = $generationParameters['input_files'] ?? [];
        if (!empty($inputFiles['audio_file']) && file_exists($inputFiles['audio_file'])) {
            return $inputFiles['audio_file'];
        }

        // Try UserFile entries
        $userFiles = UserFile::where('user_id', $userId)
            ->whereIn('mime_type', ['audio/mpeg', 'audio/wav', 'audio/aac', 'audio/x-m4a', 'audio/flac'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($userFiles) {
            $filePath = Storage::disk($userFiles->disk ?? 'local')->path($userFiles->path);
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        throw new \Exception("Could not resolve audio file from job: {$jobId}");
    }

    /**
     * Get MIME type for audio format
     */
    private function getMimeTypeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'm4a' => 'audio/x-m4a',
            default => 'audio/mpeg',
        };
    }

    /**
     * Clean up temporary directory
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
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }
}

