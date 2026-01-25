<?php

namespace App\Services\VideoJobs;

use App\Models\Videojob;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Service for post-processing approved videos with ffmpeg effects.
 */
class VideoPostProcessor
{
    /**
     * Available post-processing effects
     */
    public const EFFECT_FADE_IN = 'fade_in';
    public const EFFECT_FADE_OUT = 'fade_out';
    public const EFFECT_BRIGHTNESS = 'brightness';
    public const EFFECT_CONTRAST = 'contrast';
    public const EFFECT_SATURATION = 'saturation';
    public const EFFECT_SHARPEN = 'sharpen';
    public const EFFECT_BLUR = 'blur';
    public const EFFECT_DENOISE = 'denoise';
    public const EFFECT_SCALE = 'scale';
    public const EFFECT_CROP = 'crop';
    public const EFFECT_ROTATE = 'rotate';

    /**
     * Apply post-processing effects to an approved video.
     *
     * @param Videojob $videoJob
     * @param array $effects Array of effects with parameters, e.g., ['fade_in' => ['duration' => 1], 'brightness' => ['value' => 0.2]]
     * @return bool
     */
    public function applyEffects(Videojob $videoJob, array $effects): bool
    {
        if ($videoJob->status !== Videojob::STATUS_FINISHED) {
            Log::warning('Cannot post-process video - invalid status', [
                'job_id' => $videoJob->id,
                'status' => $videoJob->status,
            ]);
            return false;
        }

        $inputPath = $videoJob->getFinishedVideoPath();
        
        if (!file_exists($inputPath)) {
            Log::error('Input video file not found for post-processing', [
                'job_id' => $videoJob->id,
                'path' => $inputPath,
            ]);
            return false;
        }

        try {
            // Update job status
            $videoJob->update([
                'status' => Videojob::STATUS_POST_PROCESSING,
                'progress' => 0,
            ]);

            // Build ffmpeg command with all effects
            $command = $this->buildPostProcessingCommand($inputPath, $effects);
            
            // Get output path
            $outputPath = $this->getPostProcessedOutputPath($inputPath);

            Log::info('Starting video post-processing', [
                'job_id' => $videoJob->id,
                'effects' => array_keys($effects),
                'command' => $command,
            ]);

            // Execute the command
            $fullCommand = sprintf('%s %s', $command, escapeshellarg($outputPath));
            $process = Process::fromShellCommandline($fullCommand);
            $process->setTimeout(3600); // 1 hour timeout
            
            $startTime = time();
            $process->mustRun();
            
            // Replace original with post-processed version
            if (file_exists($outputPath)) {
                rename($outputPath, $inputPath);
                
                // Update job
                $videoJob->update([
                    'status' => Videojob::STATUS_FINISHED,
                    'progress' => 100,
                    'job_time' => time() - $startTime,
                ]);

                Log::info('Video post-processing completed successfully', [
                    'job_id' => $videoJob->id,
                    'duration' => time() - $startTime,
                ]);

                return true;
            } else {
                throw new \Exception('Post-processed output file not created');
            }

        } catch (ProcessFailedException $exception) {
            Log::error('Video post-processing failed', [
                'job_id' => $videoJob->id,
                'error' => $exception->getMessage(),
                'output' => $exception->getProcess()->getOutput(),
            ]);

            $videoJob->update(['status' => Videojob::STATUS_ERROR]);
            return false;

        } catch (\Exception $exception) {
            Log::error('Video post-processing error', [
                'job_id' => $videoJob->id,
                'error' => $exception->getMessage(),
            ]);

            $videoJob->update(['status' => Videojob::STATUS_ERROR]);
            return false;
        }
    }

    /**
     * Build ffmpeg command with all effects applied.
     *
     * @param string $inputPath
     * @param array $effects
     * @return string
     */
    private function buildPostProcessingCommand(string $inputPath, array $effects): string
    {
        $filters = [];
        
        foreach ($effects as $effectName => $params) {
            $filter = $this->buildEffectFilter($effectName, $params);
            if ($filter) {
                $filters[] = $filter;
            }
        }

        $filterComplex = implode(',', $filters);
        
        $commandParts = [
            'ffmpeg',
            '-y',
            '-i', escapeshellarg($inputPath),
        ];

        if (!empty($filterComplex)) {
            $commandParts[] = '-vf';
            $commandParts[] = escapeshellarg($filterComplex);
        }

        // Keep audio and set output format
        $commandParts[] = '-c:a copy';
        $commandParts[] = '-c:v libx264';
        $commandParts[] = '-preset fast';
        $commandParts[] = '-crf 23';

        return implode(' ', $commandParts);
    }

    /**
     * Build individual effect filter string.
     *
     * @param string $effectName
     * @param array $params
     * @return string|null
     */
    private function buildEffectFilter(string $effectName, array $params): ?string
    {
        return match ($effectName) {
            self::EFFECT_FADE_IN => sprintf(
                'fade=t=in:st=0:d=%s',
                $params['duration'] ?? 1
            ),
            self::EFFECT_FADE_OUT => sprintf(
                'fade=t=out:st=%s:d=%s',
                $params['start_time'] ?? 0,
                $params['duration'] ?? 1
            ),
            self::EFFECT_BRIGHTNESS => sprintf(
                'eq=brightness=%s',
                $params['value'] ?? 0
            ),
            self::EFFECT_CONTRAST => sprintf(
                'eq=contrast=%s',
                $params['value'] ?? 1
            ),
            self::EFFECT_SATURATION => sprintf(
                'eq=saturation=%s',
                $params['value'] ?? 1
            ),
            self::EFFECT_SHARPEN => 'unsharp=5:5:1.0:5:5:0.0',
            self::EFFECT_BLUR => sprintf(
                'boxblur=%s',
                $params['amount'] ?? '5:1'
            ),
            self::EFFECT_DENOISE => sprintf(
                'hqdn3d=%s',
                $params['strength'] ?? '4:3:6:4.5'
            ),
            self::EFFECT_SCALE => sprintf(
                'scale=%s:%s',
                $params['width'] ?? -1,
                $params['height'] ?? -1
            ),
            self::EFFECT_CROP => sprintf(
                'crop=%s:%s:%s:%s',
                $params['width'] ?? 'iw',
                $params['height'] ?? 'ih',
                $params['x'] ?? 0,
                $params['y'] ?? 0
            ),
            self::EFFECT_ROTATE => sprintf(
                'rotate=%s*PI/180',
                $params['degrees'] ?? 0
            ),
            default => null,
        };
    }

    /**
     * Get output path for post-processed video.
     *
     * @param string $inputPath
     * @return string
     */
    private function getPostProcessedOutputPath(string $inputPath): string
    {
        $pathInfo = pathinfo($inputPath);
        return sprintf(
            '%s/%s_postprocessed.%s',
            $pathInfo['dirname'],
            $pathInfo['filename'],
            $pathInfo['extension']
        );
    }

    /**
     * Get list of valid effect names.
     *
     * @return array
     */
    public function getValidEffectNames(): array
    {
        return [
            self::EFFECT_FADE_IN,
            self::EFFECT_FADE_OUT,
            self::EFFECT_BRIGHTNESS,
            self::EFFECT_CONTRAST,
            self::EFFECT_SATURATION,
            self::EFFECT_SHARPEN,
            self::EFFECT_BLUR,
            self::EFFECT_DENOISE,
            self::EFFECT_SCALE,
            self::EFFECT_CROP,
            self::EFFECT_ROTATE,
        ];
    }

    /**
     * Get available effects list.
     *
     * @return array
     */
    public function getAvailableEffects(): array
    {
        return [
            self::EFFECT_FADE_IN => [
                'name' => 'Fade In',
                'description' => 'Fade in from black at the start',
                'parameters' => ['duration' => 'Duration in seconds'],
            ],
            self::EFFECT_FADE_OUT => [
                'name' => 'Fade Out',
                'description' => 'Fade out to black at the end',
                'parameters' => [
                    'start_time' => 'When to start fade (seconds from start)',
                    'duration' => 'Duration in seconds',
                ],
            ],
            self::EFFECT_BRIGHTNESS => [
                'name' => 'Brightness',
                'description' => 'Adjust brightness',
                'parameters' => ['value' => 'Brightness value (-1.0 to 1.0)'],
            ],
            self::EFFECT_CONTRAST => [
                'name' => 'Contrast',
                'description' => 'Adjust contrast',
                'parameters' => ['value' => 'Contrast value (0.0 to 2.0)'],
            ],
            self::EFFECT_SATURATION => [
                'name' => 'Saturation',
                'description' => 'Adjust color saturation',
                'parameters' => ['value' => 'Saturation value (0.0 to 3.0)'],
            ],
            self::EFFECT_SHARPEN => [
                'name' => 'Sharpen',
                'description' => 'Sharpen the video',
                'parameters' => [],
            ],
            self::EFFECT_BLUR => [
                'name' => 'Blur',
                'description' => 'Apply blur effect',
                'parameters' => ['amount' => 'Blur amount (e.g., "5:1")'],
            ],
            self::EFFECT_DENOISE => [
                'name' => 'Denoise',
                'description' => 'Remove noise from video',
                'parameters' => ['strength' => 'Denoise strength (e.g., "4:3:6:4.5")'],
            ],
            self::EFFECT_SCALE => [
                'name' => 'Scale',
                'description' => 'Resize video',
                'parameters' => [
                    'width' => 'Target width (-1 for auto)',
                    'height' => 'Target height (-1 for auto)',
                ],
            ],
            self::EFFECT_CROP => [
                'name' => 'Crop',
                'description' => 'Crop video to specific dimensions',
                'parameters' => [
                    'width' => 'Crop width',
                    'height' => 'Crop height',
                    'x' => 'X offset',
                    'y' => 'Y offset',
                ],
            ],
            self::EFFECT_ROTATE => [
                'name' => 'Rotate',
                'description' => 'Rotate video',
                'parameters' => ['degrees' => 'Rotation angle in degrees'],
            ],
        ];
    }
}
