<?php

namespace App\Services\Audio;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Audio\Aac;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AudioProcessor
{
    private string $ffmpegPath;
    private string $ffprobePath;

    public function __construct()
    {
        $this->ffmpegPath = config('services.ffmpeg.binaries.ffmpeg', '/usr/bin/ffmpeg');
        $this->ffprobePath = config('services.ffmpeg.binaries.ffprobe', '/usr/bin/ffprobe');
    }

    /**
     * Process audio buffer and return processed audio data.
     *
     * @param string $audioData Raw audio data (WAV format expected from ComfyUI)
     * @return string Processed AAC audio data
     * @throws \Exception
     */
    public function processAudio(string $audioData): string
    {
        // Save audio data to temporary file
        $tempInput = tempnam(sys_get_temp_dir(), 'audio_input_') . '.wav';
        $tempOutput = tempnam(sys_get_temp_dir(), 'audio_output_') . '.aac';
        
        try {
            file_put_contents($tempInput, $audioData);

            // Build FFmpeg command for audio processing
            // Apply filters: compressor, highpass, echo, limiter
            // Output: AAC format, 2 channels, 128k bitrate
            $command = sprintf(
                '%s -y -i %s -af "acompressor=threshold=-20dB:ratio=2:attack=5:release=50,highpass=f=120,aecho=0.8:0.9:1000:0.3,alimiter=limit=0.95" -ac 2 -b:a 128k -f adts %s',
                escapeshellarg($this->ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minute timeout
            $process->mustRun();

            // Read processed audio
            if (!file_exists($tempOutput) || filesize($tempOutput) === 0) {
                throw new \Exception('FFmpeg produced empty output file');
            }

            $processedAudio = file_get_contents($tempOutput);

            // Cleanup
            @unlink($tempInput);
            @unlink($tempOutput);

            return $processedAudio;
        } catch (\Exception $e) {
            // Cleanup on error
            @unlink($tempInput);
            @unlink($tempOutput);
            
            Log::error('Audio processing failed', [
                'error' => $e->getMessage(),
                'command' => $command ?? 'N/A',
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception("Audio processing failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Stream processed audio directly to response.
     * This creates a temporary file, processes it, and streams it.
     *
     * @param string $audioData Raw audio data
     * @param resource $outputStream Output stream (e.g., php://output)
     * @return void
     * @throws \Exception
     */
    public function processAndStream(string $audioData, $outputStream): void
    {
        $processedAudio = $this->processAudio($audioData);
        fwrite($outputStream, $processedAudio);
    }
}
