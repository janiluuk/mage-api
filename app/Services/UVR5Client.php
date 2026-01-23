<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UVR5Client
{
    private ?string $dockerImage;
    private ?string $baseUrl;
    private bool $useDocker;
    private ?string $pythonPath;

    public function __construct()
    {
        // Configuration: prefer Docker for isolation, fallback to local Python
        $this->dockerImage = config('services.uvr5.docker_image', 'upseem/uvr5-cli-no-ui:latest');
        $this->baseUrl = config('services.uvr5.base_url');
        $this->useDocker = config('services.uvr5.use_docker', true);
        $this->pythonPath = config('services.uvr5.python_path', 'python3');
    }

    /**
     * Split audio file into multiple tracks using UVR5
     *
     * @param string $inputFile Path to input audio file
     * @param array $options Options for UVR5 processing
     *   - model: string - Model name (e.g., 'MDX-Net', 'VR-Arch', 'Demucs')
     *   - output_format: string - Output format (e.g., 'wav', 'mp3', 'flac')
     *   - vocal_split_mode: bool - Enable vocal splitting (main vocals vs background vocals)
     *   - output_dir: string - Directory to save output files
     * @return array Array of output file paths
     * @throws \Exception
     */
    public function splitAudio(string $inputFile, array $options = []): array
    {
        if (!file_exists($inputFile)) {
            throw new \Exception("Input file not found: {$inputFile}");
        }

        $model = $options['model'] ?? 'MDX-Net-InstVoc_HQ_3';
        $outputFormat = $options['output_format'] ?? 'wav';
        $vocalSplitMode = $options['vocal_split_mode'] ?? false;
        $outputDir = $options['output_dir'] ?? dirname($inputFile) . '/uvr5_output_' . time();

        // Create output directory if it doesn't exist
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::info('Starting UVR5 audio separation', [
            'input_file' => $inputFile,
            'model' => $model,
            'output_format' => $outputFormat,
            'output_dir' => $outputDir,
        ]);

        if ($this->useDocker) {
            return $this->splitUsingDocker($inputFile, $model, $outputFormat, $vocalSplitMode, $outputDir);
        } else {
            return $this->splitUsingPython($inputFile, $model, $outputFormat, $vocalSplitMode, $outputDir);
        }
    }

    /**
     * Split audio using Docker container
     */
    private function splitUsingDocker(
        string $inputFile,
        string $model,
        string $outputFormat,
        bool $vocalSplitMode,
        string $outputDir
    ): array {
        // Ensure input file and output dir exist and are accessible
        $inputPath = realpath($inputFile);
        $outputPath = realpath($outputDir) ?: $outputDir;

        if (!$inputPath) {
            throw new \Exception("Input file path resolution failed: {$inputFile}");
        }

        // Build Docker command
        // Mount input file and output directory into container
        $inputDir = dirname($inputPath);
        $inputFilename = basename($inputPath);
        $outputDirName = basename($outputPath);

        $command = [
            'docker', 'run', '--rm',
            '-v', "{$inputDir}:/input",
            '-v', "{$outputPath}:/output",
            $this->dockerImage,
            'audio-separator',
            'separate',
            "/input/{$inputFilename}",
            '--model-name', $model,
            '--output-format', $outputFormat,
            '--output-dir', '/output',
        ];

        if ($vocalSplitMode) {
            $command[] = '--vocal-split-mode';
        }

        Log::info('Executing UVR5 Docker command', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        $process->setIdleTimeout(300);

        try {
            $process->mustRun(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    Log::debug('UVR5 Docker process stderr', ['output' => $buffer]);
                } else {
                    Log::debug('UVR5 Docker process stdout', ['output' => $buffer]);
                }
            });
        } catch (ProcessFailedException $e) {
            Log::error('UVR5 Docker process failed', [
                'error' => $e->getMessage(),
                'command' => implode(' ', $command),
            ]);
            throw new \Exception('UVR5 audio separation failed: ' . $e->getMessage());
        }

        return $this->collectOutputFiles($outputDir, $outputFormat);
    }

    /**
     * Split audio using local Python installation
     */
    private function splitUsingPython(
        string $inputFile,
        string $model,
        string $outputFormat,
        bool $vocalSplitMode,
        string $outputDir
    ): array {
        // Use audio-separator Python package if available
        $command = [
            $this->pythonPath,
            '-m', 'audio_separator',
            'separate',
            $inputFile,
            '--model-name', $model,
            '--output-format', $outputFormat,
            '--output-dir', $outputDir,
        ];

        if ($vocalSplitMode) {
            $command[] = '--vocal-split-mode';
        }

        Log::info('Executing UVR5 Python command', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        $process->setIdleTimeout(300);

        try {
            $process->mustRun(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    Log::debug('UVR5 Python process stderr', ['output' => $buffer]);
                } else {
                    Log::debug('UVR5 Python process stdout', ['output' => $buffer]);
                }
            });
        } catch (ProcessFailedException $e) {
            Log::error('UVR5 Python process failed', [
                'error' => $e->getMessage(),
                'command' => implode(' ', $command),
            ]);
            throw new \Exception('UVR5 audio separation failed: ' . $e->getMessage());
        }

        return $this->collectOutputFiles($outputDir, $outputFormat);
    }

    /**
     * Collect output files from output directory
     */
    private function collectOutputFiles(string $outputDir, string $outputFormat): array
    {
        $outputFiles = [];
        $files = glob($outputDir . '/*.' . $outputFormat);

        foreach ($files as $file) {
            if (is_file($file)) {
                $outputFiles[] = $file;
            }
        }

        // Also check for common stem naming patterns
        $commonStems = ['vocals', 'instrumental', 'lead_vocals', 'backing_vocals', 'other'];
        foreach ($commonStems as $stem) {
            $stemFile = $outputDir . '/' . $stem . '.' . $outputFormat;
            if (file_exists($stemFile) && !in_array($stemFile, $outputFiles)) {
                $outputFiles[] = $stemFile;
            }
        }

        if (empty($outputFiles)) {
            Log::warning('No output files found after UVR5 processing', ['output_dir' => $outputDir]);
            throw new \Exception('No output files were generated by UVR5');
        }

        Log::info('UVR5 processing completed', [
            'output_dir' => $outputDir,
            'output_files_count' => count($outputFiles),
        ]);

        return $outputFiles;
    }

    /**
     * Test connection to UVR5 service
     */
    public function testConnection(): bool
    {
        if ($this->useDocker) {
            // Test Docker availability and image
            $process = new Process(['docker', 'images', '--format', '{{.Repository}}:{{.Tag}}']);
            $process->run();
            
            if ($process->getExitCode() !== 0) {
                return false;
            }
            
            $output = $process->getOutput();
            return str_contains($output, explode(':', $this->dockerImage)[0]);
        }

        // Test Python availability
        $process = new Process([$this->pythonPath, '--version']);
        $process->run();
        return $process->getExitCode() === 0;
    }
}

