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
    
    /**
     * Default FFmpeg filter chain for audio processing.
     */
    private const DEFAULT_FILTER_CHAIN = 'acompressor=threshold=-20dB:ratio=2:attack=5:release=50,highpass=f=120,aecho=0.8:0.9:1000:0.3,alimiter=limit=0.95';
    
    /**
     * Allowed output format values for FFmpeg.
     */
    private const ALLOWED_FORMATS = ['adts', 'mp4', 'wav', 'mp3', 'aac', 'flac', 'ogg'];

    public function __construct()
    {
        $this->ffmpegPath = config('services.ffmpeg.binaries.ffmpeg', '/usr/bin/ffmpeg');
        $this->ffprobePath = config('services.ffmpeg.binaries.ffprobe', '/usr/bin/ffprobe');
    }

    /**
     * Process audio buffer and return processed audio data.
     *
     * @param string $audioData Raw audio data (WAV format expected from ComfyUI)
     * @return string Processed audio data in configured format (default: AAC/ADTS)
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
            // Output: AAC format, configurable channels and bitrate
            $filterChain = $this->buildAudioFilterChain();
            $outputParams = $this->getOutputParameters();
            
            $command = sprintf(
                '%s -y -i %s -af %s -ac %s -b:a %s -f %s %s',
                escapeshellarg($this->ffmpegPath),
                escapeshellarg($tempInput),
                escapeshellarg($filterChain),
                escapeshellarg($outputParams['channels']),
                escapeshellarg($outputParams['bitrate']),
                escapeshellarg($outputParams['format']),
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

    /**
     * Build the audio filter chain from configuration.
     *
     * @return string The FFmpeg audio filter chain
     */
    private function buildAudioFilterChain(): string
    {
        $config = config('services.ffmpeg.audio_processing');
        
        if (!is_array($config) || empty($config)) {
            // Return default filter chain if configuration is missing
            return self::DEFAULT_FILTER_CHAIN;
        }
        
        $filters = [];
        
        // Compressor filter
        if (isset($config['compressor']) && is_array($config['compressor'])) {
            $comp = $config['compressor'];
            $filters[] = sprintf(
                'acompressor=threshold=%s:ratio=%s:attack=%s:release=%s',
                $this->sanitizeFilterValue($comp['threshold'] ?? '-20dB', '-20dB'),
                $this->sanitizeFilterValue($comp['ratio'] ?? '2', '2'),
                $this->sanitizeFilterValue($comp['attack'] ?? '5', '5'),
                $this->sanitizeFilterValue($comp['release'] ?? '50', '50')
            );
        }
        
        // High-pass filter
        if (isset($config['highpass']) && is_array($config['highpass'])) {
            $filters[] = sprintf(
                'highpass=f=%s',
                $this->sanitizeFilterValue($config['highpass']['frequency'] ?? '120', '120')
            );
        }
        
        // Echo filter
        if (isset($config['echo']) && is_array($config['echo'])) {
            $echo = $config['echo'];
            $filters[] = sprintf(
                'aecho=%s:%s:%s:%s',
                $this->sanitizeFilterValue($echo['in_gain'] ?? '0.8', '0.8'),
                $this->sanitizeFilterValue($echo['out_gain'] ?? '0.9', '0.9'),
                $this->sanitizeFilterValue($echo['delay'] ?? '1000', '1000'),
                $this->sanitizeFilterValue($echo['decay'] ?? '0.3', '0.3')
            );
        }
        
        // Limiter filter
        if (isset($config['limiter']) && is_array($config['limiter'])) {
            $filters[] = sprintf(
                'alimiter=limit=%s',
                $this->sanitizeFilterValue($config['limiter']['limit'] ?? '0.95', '0.95')
            );
        }
        
        // If no filters were configured, return default filter chain
        if (empty($filters)) {
            return self::DEFAULT_FILTER_CHAIN;
        }
        
        return implode(',', $filters);
    }

    /**
     * Get output parameters from configuration.
     *
     * @return array Output parameters (channels, bitrate, format)
     */
    private function getOutputParameters(): array
    {
        $config = config('services.ffmpeg.audio_processing.output', []);
        
        if (!is_array($config)) {
            $config = [];
        }
        
        $format = $config['format'] ?? 'adts';
        // Validate format against whitelist
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $format = 'adts';
        }
        
        return [
            'channels' => $this->sanitizeFilterValue($config['channels'] ?? '2', '2'),
            'bitrate' => $this->sanitizeFilterValue($config['bitrate'] ?? '128k', '128k'),
            'format' => $format, // Already validated against whitelist, no need to sanitize
        ];
    }

    /**
     * Sanitize filter values to prevent command injection.
     * Only allows specific safe patterns for FFmpeg parameters.
     *
     * @param mixed $value The value to sanitize
     * @param string $default The default value to return if sanitization fails
     * @return string The sanitized value
     */
    private function sanitizeFilterValue($value, string $default = '0'): string
    {
        // Convert to string
        $value = (string)$value;
        
        // Allow only safe patterns:
        // - Negative numbers with optional dB suffix (e.g., -20dB)
        // - Positive numbers with optional decimal and optional k suffix (e.g., 2, 0.8, 128k)
        // Hyphen is only allowed at the start for negative numbers
        if (preg_match('/^-?[0-9]+(?:\.[0-9]+)?(?:dB|k)?$/', $value)) {
            return $value;
        }
        
        return $default;
    }
}
