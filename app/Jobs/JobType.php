<?php

namespace App\Jobs;

/**
 * Enum for different job processing types
 */
enum JobType: string
{
    case VID2VID = 'vid2vid';
    case DEFORUM = 'deforum';
    case AUDIO_TRACK_SPLIT = 'audio-track-split';
    case BEAT_MATCH = 'beat-match';
    case COMFYUI_WORKFLOW = 'comfyui-workflow';
    
    /**
     * Get the processor class for this job type
     */
    public function getProcessorClass(): string
    {
        return match($this) {
            self::VID2VID => \App\Services\JobProcessors\StableDiffusionJobProcessor::class,
            self::DEFORUM => \App\Services\JobProcessors\DeforumJobProcessor::class,
            self::AUDIO_TRACK_SPLIT => \App\Services\JobProcessors\AudioJobProcessor::class,
            self::BEAT_MATCH => \App\Services\JobProcessors\BeatMatchJobProcessor::class,
            self::COMFYUI_WORKFLOW => \App\Services\JobProcessors\ComfyUIJobProcessor::class,
        };
    }
    
    /**
     * Determine job type from Videojob model
     */
    public static function fromVideoJob(\App\Models\Videojob $videoJob): self
    {
        // Check generation_parameters for custom job types
        $params = $videoJob->generation_parameters ? json_decode($videoJob->generation_parameters, true) : [];
        
        if (isset($params['jobType'])) {
            if ($params['jobType'] === 'audio-track-split') {
                return self::AUDIO_TRACK_SPLIT;
            }
            if ($params['jobType'] === 'beat-match') {
                return self::BEAT_MATCH;
            }
            if ($params['jobType'] === 'comfyui-workflow') {
                return self::COMFYUI_WORKFLOW;
            }
        }
        
        // Fall back to generator field
        if ($videoJob->generator === 'deforum') {
            return self::DEFORUM;
        }
        
        // Default to vid2vid for backwards compatibility
        return self::VID2VID;
    }
}
