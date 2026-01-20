<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBeatMatchMusicVideoJob;
use App\Jobs\ProcessAudioTrackSplitJob;
use App\Models\UserFile;
use App\Models\Videojob;
use App\Services\CustomJobs\JobTypeRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling custom video/audio job processing
 */
class CustomJobController extends Controller
{
    private JobTypeRegistry $_jobTypeRegistry;

    /**
     * Constructor
     *
     * @param JobTypeRegistry $jobTypeRegistry Job type registry service
     */
    public function __construct(JobTypeRegistry $jobTypeRegistry)
    {
        $this->_jobTypeRegistry = $jobTypeRegistry;
    }

    /**
     * Process a custom video job
     * POST /api/v1/custom-jobs/process
     *
     * @param Request $request HTTP request object
     *
     * @return JsonResponse
     */
    public function process(Request $request): JsonResponse
    {
        // Parse options if it's a JSON string (common in multipart/form-data)
        $optionsInput = $request->input('options');
        if (is_string($optionsInput)) {
            $decoded = json_decode($optionsInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['options' => $decoded]);
            }
        }

        // Base validation
        $baseValidated = $request->validate(
            [
                'job_type' => 'required|string',
                'input_type' => 'required|string|in:files,project',
                'options' => 'required|array',
            ]
        );

        // Check if job type is supported
        if (!$this->_jobTypeRegistry->isSupported($baseValidated['job_type'])) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unsupported job type. Supported types: '
                        . implode(
                            ', ',
                            $this->_jobTypeRegistry->getSupportedTypes()
                        ),
                ],
                422
            );
        }

        // Get validator for this job type
        $validator = $this->_jobTypeRegistry->getValidator(
            $baseValidated['job_type']
        );

        // Validate job-specific options
        $optionsRules = $validator->getValidationRules(
            $baseValidated['options']
        );
        $optionsValidator = Validator::make(
            $baseValidated['options'],
            $optionsRules
        );

        if ($optionsValidator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Invalid job options',
                    'errors' => $optionsValidator->errors(),
                ],
                422
            );
        }

        // Validate input files based on job type validator
        // Check if job_id is provided in options before validating files
        $hasJobId = isset($baseValidated['options']['job_id']) && !empty($baseValidated['options']['job_id']);
        
        $inputRules = $validator->getInputFileValidationRules(
            $baseValidated['input_type'],
            $hasJobId
        );
        $inputValidated = $request->validate($inputRules);

        // Merge validated options (they're already validated)
        $validatedOptions = $optionsValidator->validated();

        try {
            $user = $request->user();
            $videoFiles = [];
            $audioFile = null;

            // Handle input based on input_type and job_type
            $jobType = $baseValidated['job_type'];
            $isAudioOnly = $jobType === 'audio-track-split';

            // Check if job_id is provided in options (for audio-track-split)
            $jobId = $validatedOptions['job_id'] ?? null;

            if ($jobId && $isAudioOnly) {
                // For audio-track-split with job_id, resolve file later
                $audioFile = null; // Will be resolved in service
            } elseif ($baseValidated['input_type'] === 'project') {
                if (!$isAudioOnly) {
                    // Get video files from project (for beat-match)
                    $projectFiles = UserFile::where(
                        'project_id',
                        $inputValidated['project_id']
                    )
                        ->where('user_id', $user->id)
                        ->whereIn(
                            'mime_type',
                            ['video/mp4', 'video/quicktime', 'video/webm']
                        )
                        ->get();

                    if ($projectFiles->isEmpty()) {
                        return response()->json(
                            [
                                'message' => 'No video files found in the '
                                    . 'specified project'
                            ],
                            422
                        );
                    }

                    foreach ($projectFiles as $file) {
                        $fileDisk = $file->disk ?? 'local';
                        if ($fileDisk === 'local') {
                            $filePath = storage_path('app/' . $file->path);
                        } else {
                            // For non-local disks, use path() method
                            /**
                             * Get filesystem adapter for non-local disk
                             *
                             * @var \Illuminate\Filesystem\FilesystemAdapter $adapter
                             */
                            $adapter = Storage::disk($fileDisk);
                            $filePath = $adapter->path($file->path);
                        }
                        if (file_exists($filePath)) {
                            $videoFiles[] = $filePath;
                        }
                    }
                }

                // Get audio file from project (for both job types)
                $audioFiles = UserFile::where(
                    'project_id',
                    $inputValidated['project_id']
                )
                    ->where('user_id', $user->id)
                    ->whereIn(
                        'mime_type',
                        [
                            'audio/mpeg',
                            'audio/wav',
                            'audio/aac',
                            'audio/x-m4a',
                            'audio/flac'
                        ]
                    )
                    ->first();

                if (!$audioFiles) {
                    return response()->json(
                        [
                            'message' => 'No audio file found in the '
                                . 'specified project. Please provide '
                                . 'audio_file separately or ensure project '
                                . 'contains an audio file.'
                        ],
                        422
                    );
                }

                $audioDisk = $audioFiles->disk ?? 'local';
                if ($audioDisk === 'local') {
                    $audioFile = storage_path('app/' . $audioFiles->path);
                } else {
                    // For non-local disks, use path() method
                    /**
                     * Get filesystem adapter for non-local disk
                     *
                     * @var \Illuminate\Filesystem\FilesystemAdapter $adapter
                     */
                    $adapter = Storage::disk($audioDisk);
                    $audioFile = $adapter->path($audioFiles->path);
                }

                if (!file_exists($audioFile)) {
                    return response()->json(
                        [
                            'message' => 'Audio file not found on disk'
                        ],
                        422
                    );
                }

            } else {
                // Handle file uploads
                if ($request->hasFile('audio_file')) {
                    $audioFilePath = $request->file('audio_file')
                        ->store('temp/custom-jobs', 'local');
                    $audioFile = storage_path('app/' . $audioFilePath);
                }

                if (!$isAudioOnly && $request->hasFile('video_files')) {
                    // Handle video files only for beat-match jobs
                    foreach ($request->file('video_files') as $videoFile) {
                        $videoPath = $videoFile
                            ->store('temp/custom-jobs', 'local');
                        $videoFiles[] = storage_path('app/' . $videoPath);
                    }
                }
            }

            // Validate that we have the required files for the job type
            if ($baseValidated['job_type'] === 'beat-match'
                && empty($videoFiles)
            ) {
                return response()->json(
                    [
                        'message' => 'At least one video file is required '
                            . 'for beat-match jobs'
                    ],
                    422
                );
            }

            if ($baseValidated['job_type'] === 'audio-track-split'
                && !$audioFile && !$jobId
            ) {
                return response()->json(
                    [
                        'message' => 'Audio file or job_id is required for '
                            . 'audio-track-split jobs'
                    ],
                    422
                );
            }

            // Generate output filename based on job type
            $fileExtension = $baseValidated['job_type'] === 'beat-match'
                ? '.mp4' : '.mp3';
            $outputFilename = 'custom-job-'
                . $baseValidated['job_type'] . '-'
                . time() . '-'
                . uniqid() . $fileExtension;

            // Determine original filename
            $originalFilename = 'unknown';
            if ($baseValidated['input_type'] === 'files'
                && $request->hasFile('audio_file')
            ) {
                $originalFilename = $request->file('audio_file')
                    ->getClientOriginalName();
            } elseif ($baseValidated['input_type'] === 'project'
                && isset($inputValidated['project_id'])
            ) {
                $originalFilename = 'project-'
                    . $inputValidated['project_id'];
            } elseif ($jobId) {
                $originalFilename = 'job-' . $jobId;
            }

            // Create Videojob
            $videoJob = new Videojob();
            $videoJob->filename = 'custom-job-'
                . $baseValidated['job_type'] . '-'
                . time();
            $videoJob->original_filename = $originalFilename;
            $videoJob->outfile = $outputFilename;
            $videoJob->generator = $baseValidated['job_type'];
            $mimeType = $baseValidated['job_type'] === 'beat-match'
                ? 'video/mp4' : 'audio/mpeg';
            $videoJob->mimetype = $mimeType;
            $videoJob->user_id = $user->id;
            $videoJob->status = Videojob::STATUS_PENDING;
            $videoJob->queued_at = null;

            // Store job configuration in generation_parameters
            // Separate input files from options for clarity
            $videoJob->generation_parameters = [
                'job_type' => $baseValidated['job_type'],
                'input_type' => $baseValidated['input_type'],
                'options' => $validatedOptions, // Job-specific options
                'input_files' => [
                    'audio_file' => $audioFile,
                    'video_files' => $videoFiles,
                    'job_id' => $jobId, // Store job_id if provided
                ],
                'project_id' => $inputValidated['project_id'] ?? null,
            ];
            $videoJob->save();

            // Queue the job
            $videoJob->resetProgress(Videojob::STATUS_APPROVED);
            $videoJob->queued_at = Carbon::now();
            $videoJob->save();
            $videoJob->refresh();

            // Dispatch appropriate job based on job_type
            $serviceClass = $validator->getServiceClass();
            if (class_exists($serviceClass)) {
                // Dispatch job based on job type
                if ($baseValidated['job_type'] === 'beat-match') {
                    ProcessBeatMatchMusicVideoJob::dispatch($videoJob)
                        ->onQueue('default');
                } elseif ($baseValidated['job_type'] === 'audio-track-split') {
                    ProcessAudioTrackSplitJob::dispatch($videoJob)
                        ->onQueue('default');
                } else {
                    // For other job types, we can extend this pattern
                    Log::warning(
                        'Job type service not yet implemented',
                        [
                            'job_type' => $baseValidated['job_type'],
                            'service_class' => $serviceClass
                        ]
                    );
                }
            }

            Log::info(
                'Custom job queued',
                [
                    'job_id' => $videoJob->getKey(),
                    'job_type' => $baseValidated['job_type'],
                    'input_type' => $baseValidated['input_type'],
                    'options' => $validatedOptions,
                    'user_id' => $user->id,
                ]
            );

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Custom job queued successfully',
                    'job_id' => $videoJob->getKey(),
                    'status' => $videoJob->status,
                ]
            );

        } catch (\Exception $e) {
            Log::error(
                'Error creating custom job',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Failed to create custom job: '
                        . $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get custom job status
     * GET /api/v1/custom-jobs/{id}/status
     *
     * @param Request $request HTTP request object
     * @param int     $id      Job ID
     *
     * @return JsonResponse
     */
    public function status(Request $request, $id): JsonResponse
    {
        $videoJob = Videojob::findOrFail($id);

        // Check authorization
        if ($videoJob->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $errorStatus = Videojob::STATUS_ERROR;
        $error = $videoJob->status === $errorStatus
            ? 'Processing failed' : null;

        return response()->json(
            [
                'id' => $videoJob->id,
                'status' => $videoJob->status,
                'progress' => $videoJob->progress,
                'estimated_time_left' => $videoJob->estimated_time_left,
                'job_time' => $videoJob->job_time,
                'url' => $videoJob->url,
                'error' => $error,
            ]
        );
    }
}
