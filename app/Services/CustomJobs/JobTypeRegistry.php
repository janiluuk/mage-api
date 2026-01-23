<?php

namespace App\Services\CustomJobs;

use App\Services\CustomJobs\Validators\BeatMatchJobValidator;
use App\Services\CustomJobs\Validators\AudioTrackSplitJobValidator;

class JobTypeRegistry
{
    private array $validators = [];

    public function __construct()
    {
        // Register validators for each job type
        $this->validators = [
            'beat-match' => BeatMatchJobValidator::class,
            'audio-track-split' => AudioTrackSplitJobValidator::class,
            // Add more job types here as they are created
        ];
    }

    /**
     * Get validator for a job type
     *
     * @param string $jobType
     * @return JobValidatorInterface|null
     */
    public function getValidator(string $jobType): ?JobValidatorInterface
    {
        if (!isset($this->validators[$jobType])) {
            return null;
        }

        $validatorClass = $this->validators[$jobType];
        return new $validatorClass();
    }

    /**
     * Check if job type is supported
     *
     * @param string $jobType
     * @return bool
     */
    public function isSupported(string $jobType): bool
    {
        return isset($this->validators[$jobType]);
    }

    /**
     * Get list of supported job types
     *
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->validators);
    }
}

