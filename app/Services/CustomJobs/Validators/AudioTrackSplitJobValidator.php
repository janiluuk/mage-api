<?php

namespace App\Services\CustomJobs\Validators;

use App\Services\CustomJobs\JobValidatorInterface;

class AudioTrackSplitJobValidator implements JobValidatorInterface
{
    public function getValidationRules(array $options): array
    {
        return [
            'model' => 'nullable|string|max:255',
            'output_format' => 'nullable|string|in:mp3,wav,aac,m4a,flac',
            'vocal_split_mode' => 'nullable|boolean',
            'job_id' => 'nullable|integer|exists:video_jobs,id', // Optional job_id for input
        ];
    }

    public function getInputFileValidationRules(string $inputType): array
    {
        if ($inputType === 'files') {
            return [
                'audio_file' => 'required|file|mimes:mp3,wav,aac,m4a,flac|max:51200',
            ];
        } else {
            // For project input type, validate project_id
            // Also allow job_id in options for audio-track-split
            return [
                'project_id' => 'required_without:job_id|integer|exists:user_files,project_id',
            ];
        }
    }

    public function getDescription(): string
    {
        return 'Split audio file into multiple tracks with AI model processing';
    }

    public function getServiceClass(): string
    {
        return \App\Services\AudioTrackSplitService::class; // To be implemented
    }
}

