<?php

namespace App\Services;

use App\Events\VideoExportProgressUpdated;
use App\Models\VideoExportJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoExportService
{
    /**
     * Process a video export job
     */
    public function process(VideoExportJob $job): void
    {
        try {
            $job->update([
                'status' => VideoExportJob::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            // Download input files
            $inputPaths = $this->downloadInputFiles($job->input_files);

            // Build FFmpeg command
            $ffmpegCmd = $this->buildFFmpegCommand($job, $inputPaths);

            // Create output directory
            $outputDir = Storage::disk('public')->path('video-exports');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputFileName = $job->output_name ?? 'export_' . $job->id . '.mp4';
            $outputPath = $outputDir . '/' . uniqid() . '_' . $outputFileName;

            // Add output path to command
            $ffmpegCmd[] = '-y'; // Overwrite output file
            $ffmpegCmd[] = $outputPath;

            Log::info('Starting video export', [
                'job_id' => $job->id,
                'command' => implode(' ', $ffmpegCmd),
            ]);

            // Execute FFmpeg command
            $process = new Process($ffmpegCmd);
            $process->setTimeout(3600); // 1 hour timeout
            $process->setIdleTimeout(300); // 5 minute idle timeout

            $outputLog = [];
            $lastProgressUpdate = 0;

            $process->run(function ($type, $buffer) use ($job, &$outputLog, &$lastProgressUpdate) {
                $outputLog[] = $buffer;
                
                // Parse progress from FFmpeg output
                $progress = $this->parseProgress($buffer, $job);
                
                if ($progress !== null && (time() - $lastProgressUpdate) >= 1) {
                    // Refresh job from database to avoid stale updates
                    $job->refresh();
                    
                    $job->update([
                        'progress' => min(100, $progress['percent']),
                        'timemark' => $progress['timemark'] ?? null,
                        'output_log' => array_slice($outputLog, -100), // Keep last 100 lines
                    ]);
                    
                    // Broadcast progress update event for real-time updates
                    event(new VideoExportProgressUpdated($job));
                    
                    $lastProgressUpdate = time();
                }
            });

            if (!$process->isSuccessful() || !file_exists($outputPath)) {
                throw new ProcessFailedException($process);
            }

            // Move file to final location and get URL
            $relativePath = 'video-exports/' . basename($outputPath);
            $publicPath = Storage::disk('public')->putFileAs(
                'video-exports',
                $outputPath,
                basename($outputPath)
            );

            $outputUrl = Storage::disk('public')->url($publicPath);

            // Update job with success
            $job->refresh(); // Refresh to avoid stale state
            $job->update([
                'status' => VideoExportJob::STATUS_COMPLETED,
                'progress' => 100.0,
                'output_path' => $publicPath,
                'output_url' => $outputUrl,
                'output_log' => array_slice($outputLog, -100),
                'completed_at' => now(),
            ]);
            
            // Broadcast final completion event
            event(new VideoExportProgressUpdated($job));

            // Clean up downloaded input files
            $this->cleanupInputFiles($inputPaths);

            Log::info('Video export completed', [
                'job_id' => $job->id,
                'output_url' => $outputUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Video export failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $job->update([
                'status' => VideoExportJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'output_log' => $outputLog ?? [],
            ]);
            
            // Broadcast failure event
            event(new VideoExportProgressUpdated($job));

            // Clean up on error
            if (isset($inputPaths)) {
                $this->cleanupInputFiles($inputPaths);
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                @unlink($outputPath);
            }

            throw $e;
        }
    }

    /**
     * Download input video files
     */
    private function downloadInputFiles(array $inputFiles): array
    {
        $inputPaths = [];
        $tempDir = sys_get_temp_dir() . '/video_export_' . uniqid();

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($inputFiles as $index => $inputFile) {
            $url = $inputFile['url'] ?? null;
            if (!$url) {
                throw new \Exception("Input file {$index} missing URL");
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
            $tempPath = $tempDir . '/input' . $index . '.' . $extension;

            // Download file
            $ch = curl_init($url);
            $fp = fopen($tempPath, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($httpCode !== 200 || !file_exists($tempPath)) {
                throw new \Exception("Failed to download input file {$index}: HTTP {$httpCode}");
            }

            $inputPaths[] = $tempPath;
        }

        return $inputPaths;
    }

    /**
     * Build FFmpeg command from filter graph
     */
    private function buildFFmpegCommand(VideoExportJob $job, array $inputPaths): array
    {
        $cmd = [];

        // Validate input paths before adding to command
        foreach ($inputPaths as $inputPath) {
            // Check for path traversal attempts first (before realpath resolves them)
            if (strpos($inputPath, '..') !== false) {
                throw new \InvalidArgumentException('Invalid input file path: path traversal not allowed');
            }
            
            // Ensure path is within temporary directory
            $realPath = realpath($inputPath);
            $tempDir = sys_get_temp_dir();
            
            if (!$realPath || !str_starts_with($realPath, $tempDir)) {
                throw new \InvalidArgumentException('Invalid input file path: path must be within temporary directory');
            }
            
            // Verify file exists and is readable
            if (!is_file($realPath) || !is_readable($realPath)) {
                throw new \InvalidArgumentException('Invalid input file path: file does not exist or is not readable');
            }
        }

        // Add input files
        foreach ($inputPaths as $inputPath) {
            $cmd[] = '-i';
            $cmd[] = $inputPath;
        }

        // Build filter complex
        $filterComplex = $this->buildFilterComplex($job->filter_graph);
        if ($filterComplex) {
            // Validate filter complex doesn't contain shell injection attempts
            if (preg_match('/[;&|`$()<>{}\\\\\\r\\n]/', $filterComplex)) {
                throw new \InvalidArgumentException('Invalid filter graph: contains potentially dangerous characters');
            }
            
            $cmd[] = '-filter_complex';
            $cmd[] = $filterComplex;
        }

        // Map output streams
        foreach ($job->outputs as $output) {
            $cmd[] = '-map';
            $cmd[] = "[{$output}]";
        }

        // Add export options
        $options = $job->export_options ?? [];
        
        // Video codec and quality
        $cmd[] = '-c:v';
        $cmd[] = 'libx264';
        $cmd[] = '-preset';
        $cmd[] = 'medium';
        $cmd[] = '-crf';
        $cmd[] = '23';

        // Audio codec
        if (in_array('outa', $job->outputs)) {
            $cmd[] = '-c:a';
            $cmd[] = 'aac';
            $cmd[] = '-b:a';
            $cmd[] = '128k';
        }

        // Bitrate override
        if (isset($options['bitrate']) && $options['bitrate']) {
            $bitrate = (float) $options['bitrate'] * 1000000; // Convert Mbps to bps
            $cmd[] = '-b:v';
            $cmd[] = (string) $bitrate;
        }

        // FPS
        if (isset($options['fps']) && $options['fps']) {
            $cmd[] = '-r';
            $cmd[] = (string) $options['fps'];
        }

        // Format
        $cmd[] = '-f';
        $cmd[] = 'mp4';
        $cmd[] = '-movflags';
        $cmd[] = '+faststart'; // Enable fast start for web playback

        return $cmd;
    }

    /**
     * Build filter complex string from filter graph
     */
    private function buildFilterComplex(array $filterGraph): string
    {
        $filters = [];

        foreach ($filterGraph as $filter) {
            $filterStr = '';

            // Inputs
            if (isset($filter['inputs'])) {
                if (is_array($filter['inputs'])) {
                    $filterStr .= implode('', array_map(fn($inp) => "[{$inp}]", $filter['inputs']));
                } else {
                    $filterStr .= "[{$filter['inputs']}]";
                }
            }

            // Filter name
            $filterStr .= $filter['filter'] ?? '';

            // Options
            if (isset($filter['options'])) {
                if (is_string($filter['options'])) {
                    $filterStr .= '=' . $filter['options'];
                } elseif (is_array($filter['options'])) {
                    $opts = [];
                    foreach ($filter['options'] as $key => $value) {
                        $opts[] = "{$key}={$value}";
                    }
                    $filterStr .= '=' . implode(':', $opts);
                }
            }

            // Outputs
            if (isset($filter['outputs'])) {
                if (is_array($filter['outputs'])) {
                    $filterStr .= implode('', array_map(fn($out) => "[{$out}]", $filter['outputs']));
                } else {
                    $filterStr .= "[{$filter['outputs']}]";
                }
            }

            $filters[] = $filterStr;
        }

        return implode(';', $filters);
    }

    /**
     * Parse progress from FFmpeg output
     */
    private function parseProgress(string $buffer, VideoExportJob $job): ?array
    {
        // Parse time from FFmpeg output like "time=00:00:30.00"
        if (preg_match('/time=(\d{2}):(\d{2}):(\d{2}\.\d{2})/', $buffer, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (float) $matches[3];
            $totalSeconds = $hours * 3600 + $minutes * 60 + $seconds;

            // Estimate total duration from fragments (simplified)
            $totalDuration = 0;
            foreach ($job->fragments as $fragment) {
                $start = ($fragment['start'] ?? 0) * ($fragment['duration'] ?? 60);
                $end = ($fragment['end'] ?? 1) * ($fragment['duration'] ?? 60);
                $duration = ($end - $start) / ($fragment['playbackRate'] ?? 1);
                $totalDuration += $duration;
            }

            $percent = $totalDuration > 0 ? min(99, ($totalSeconds / $totalDuration) * 100) : 0; // Cap at 99% until complete

            return [
                'percent' => round($percent, 2),
                'timemark' => sprintf('%02d:%02d:%05.2f', $hours, $minutes, $seconds),
            ];
        }

        return null;
    }

    /**
     * Clean up downloaded input files
     */
    private function cleanupInputFiles(array $inputPaths): void
    {
        foreach ($inputPaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        // Remove temp directory if empty
        $tempDir = dirname($inputPaths[0] ?? '');
        if ($tempDir && file_exists($tempDir) && count(glob($tempDir . '/*')) === 0) {
            @rmdir($tempDir);
        }
    }

    /**
     * Cancel an export job
     */
    public function cancel(VideoExportJob $job): void
    {
        $job->update([
            'status' => VideoExportJob::STATUS_CANCELLED,
            'error' => 'Cancelled by user',
        ]);
        
        // Broadcast cancellation event
        event(new VideoExportProgressUpdated($job));
    }
}

