<?php

namespace App\Services\CustomJobs;

interface JobValidatorInterface
{
    /**
     * Validate job options for this job type
     *
     * @param array $options
     * @return array Validation rules array for Laravel validation
     */
    public function getValidationRules(array $options): array;

    /**
     * Get validation rules for input files based on input type
     * 
     * This method allows flexible definition of input file requirements per job type.
     * Different job types may require different file types, counts, or combinations.
     *
     * @param string $inputType Either 'files' (direct file upload) or 'project' (files from project_id)
     * @return array Validation rules array for Laravel validation
     */
    public function getInputFileValidationRules(string $inputType): array;

    /**
     * Get description of this job type
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the service class that handles processing this job type
     *
     * @return string Service class name
     */
    public function getServiceClass(): string;
}

