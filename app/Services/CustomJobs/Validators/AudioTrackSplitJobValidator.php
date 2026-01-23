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

    public function getInputFileValidationRules(string $inputType, bool $hasJobId = false): array
    {
        if ($inputType === 'files') {
            // If job_id is provided, audio_file is optional (will use existing job output)
            if ($hasJobId) {
                return [
                    'audio_file' => 'nullable|file|mimes:mp3,wav,aac,m4a,flac|max:51200',
                ];
            }
            return [
                'audio_file' => 'required|file|mimes:mp3,wav,aac,m4a,flac|max:51200',
            ];
        } else {
            // For project input type, project_id is always required
            // Note: We don't validate exists:user_files,project_id here because
            // the controller will validate project ownership and file existence
            return [
                'project_id' => 'required|integer',
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

