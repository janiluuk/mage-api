<?php

namespace App\Services\CustomJobs\Validators;

use App\Services\CustomJobs\JobValidatorInterface;

class BeatMatchJobValidator implements JobValidatorInterface
{
    public function getValidationRules(array $options): array
    {
        return [
            'cut_intensity' => 'nullable|integer|in:1,2,3',
            'direction' => 'nullable|string|in:forward,backward,random',
            'speed_factor' => 'nullable|numeric|min:0.1|max:2.0',
            'start_time' => 'nullable|numeric|min:0',
            'end_time' => 'nullable|numeric|gt:start_time',
        ];
    }

    public function getInputFileValidationRules(string $inputType, bool $hasJobId = false): array
    {
        if ($inputType === 'files') {
            return [
                'audio_file' => 'required|file|mimes:mp3,wav,aac,m4a|max:51200',
                'video_files' => 'required|array|min:1',
                'video_files.*' => 'required|file|mimes:mp4,mov,webm|max:200000',
            ];
        } else {
            // For project input type, validate project_id
            return [
                'project_id' => 'required|integer|exists:user_files,project_id',
            ];
        }
    }

    public function getDescription(): string
    {
        return 'Create a music video with cuts synchronized to bass beats in audio';
    }

    public function getServiceClass(): string
    {
        return \App\Services\BeatMatchMusicVideoService::class;
    }
}

