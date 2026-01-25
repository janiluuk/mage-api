# Job System Refactoring Documentation

## Overview

The job system has been refactored to provide a streamlined, extensible architecture for processing different types of video/audio jobs. The new system uses a processor pattern that allows easy addition of new job types without duplicating code.

## Architecture

### Key Components

1. **JobType Enum** (`app/Jobs/JobType.php`)
   - Defines all available job types
   - Maps job types to their processors
   - Determines job type from Videojob model

2. **JobProcessorInterface** (`app/Services/JobProcessors/JobProcessorInterface.php`)
   - Contract for all job processors
   - Defines `process()`, `getTimeout()`, `getStaleThreshold()`, and `supports()` methods

3. **AbstractJobProcessor** (`app/Services/JobProcessors/AbstractJobProcessor.php`)
   - Base class with common functionality
   - Handles locking, logging, and error handling
   - Reduces code duplication across processors

4. **Concrete Processors**
   - `StableDiffusionJobProcessor` - Handles Vid2Vid jobs
   - `DeforumJobProcessor` - Handles Deforum animation jobs
   - `AudioJobProcessor` - Handles audio track splitting
   - `BeatMatchJobProcessor` - Handles beat matching
   - `ComfyUIJobProcessor` - Handles ComfyUI workflow execution

5. **UnifiedJobHandler** (`app/Jobs/UnifiedJobHandler.php`)
   - Orchestrates job processing
   - Handles concurrency control
   - Routes to appropriate processor

6. **ProcessUnifiedJob** (`app/Jobs/ProcessUnifiedJob.php`)
   - New unified job class for all job types
   - Replaces individual job classes (recommended for new code)

## Job Types

### VID2VID
- **Description**: Video-to-video processing using Stable Diffusion models
- **Processor**: `StableDiffusionJobProcessor`
- **Detection**: Default when `generator` is not 'deforum'
- **Timeout**: 7.5 hours
- **Stale Threshold**: 15 minutes

### DEFORUM
- **Description**: Deforum animation generation
- **Processor**: `DeforumJobProcessor`
- **Detection**: `generator === 'deforum'`
- **Timeout**: 7.5 hours
- **Stale Threshold**: 15 minutes

### AUDIO_TRACK_SPLIT
- **Description**: UVR5-based audio track separation
- **Processor**: `AudioJobProcessor`
- **Detection**: `generation_parameters.jobType === 'audio-track-split'`
- **Timeout**: 2 hours
- **Stale Threshold**: 30 minutes

### BEAT_MATCH
- **Description**: Music-video beat synchronization
- **Processor**: `BeatMatchJobProcessor`
- **Detection**: `generation_parameters.jobType === 'beat-match'`
- **Timeout**: 2 hours
- **Stale Threshold**: 30 minutes

### COMFYUI_WORKFLOW
- **Description**: ComfyUI workflow execution
- **Processor**: `ComfyUIJobProcessor`
- **Detection**: `generation_parameters.jobType === 'comfyui-workflow'`
- **Timeout**: 7.5 hours
- **Stale Threshold**: 15 minutes

## Usage

### Dispatching Jobs

#### Using the New Unified System (Recommended)

```php
use App\Jobs\ProcessUnifiedJob;
use App\Services\VideoJobs\VideoJobSubmitter;

// Method 1: Using ProcessUnifiedJob directly
$videoJob = Videojob::create([...]);
ProcessUnifiedJob::dispatch($videoJob, $previewFrames, $extendFromJobId)->onQueue($queueName);

// Method 2: Using VideoJobSubmitter (recommended)
$submitter = app(VideoJobSubmitter::class);
$submitter->submit($videoJob, $previewFrames, $extendFromJobId);
```

#### Using Legacy Methods (Backwards Compatible)

```php
// Old method - still works but not recommended for new code
$submitter->submitVid2Vid($videoJob, $payload);
$submitter->submitDeforum($videoJob, $payload);
```

### Adding a New Job Type

1. **Add to JobType enum**:
```php
// app/Jobs/JobType.php
case NEW_TYPE = 'new-type';
```

2. **Create a processor**:
```php
// app/Services/JobProcessors/NewTypeJobProcessor.php
namespace App\Services\JobProcessors;

class NewTypeJobProcessor extends AbstractJobProcessor
{
    protected int $timeout = 3600; // 1 hour
    protected int $staleThreshold = 15;
    
    public function __construct(
        private YourService $yourService
    ) {
    }
    
    public function process(Videojob $videoJob, int $previewFrames = 0, ?int $extendFromJobId = null): void
    {
        $startTime = time();
        
        try {
            $this->logJobStart($videoJob, 'NewType');
            $this->markStaleJobsAsErrors();
            
            if ($this->isJobLocked($videoJob->id)) {
                // Handle already processing
                return;
            }
            
            $this->lockJob($videoJob->id);
            $this->initializeJob($videoJob);
            
            // Your processing logic here
            $this->yourService->process($videoJob);
            
            $this->unlockJob($videoJob->id);
            $this->logJobCompletion($videoJob, 'NewType', time() - $startTime);
            
        } catch (\Exception $e) {
            $this->unlockJob($videoJob->id);
            $this->logJobError($videoJob, 'NewType', $e);
            throw $e;
        }
    }
    
    public function supports(Videojob $videoJob): bool
    {
        $params = $videoJob->generation_parameters ? json_decode($videoJob->generation_parameters, true) : [];
        return isset($params['jobType']) && $params['jobType'] === 'new-type';
    }
}
```

3. **Map in JobType enum**:
```php
public function getProcessorClass(): string
{
    return match($this) {
        // ... existing mappings
        self::NEW_TYPE => \App\Services\JobProcessors\NewTypeJobProcessor::class,
    };
}
```

4. **Update fromVideoJob() if needed**:
```php
public static function fromVideoJob(\App\Models\Videojob $videoJob): self
{
    $params = $videoJob->generation_parameters ? json_decode($videoJob->generation_parameters, true) : [];
    
    if (isset($params['jobType']) && $params['jobType'] === 'new-type') {
        return self::NEW_TYPE;
    }
    
    // ... existing logic
}
```

5. **Dispatch the job**:
```php
$videoJob = Videojob::create([
    'generation_parameters' => json_encode(['jobType' => 'new-type', ...])
]);

$submitter->submit($videoJob);
```

## Testing

### Unit Tests
```php
// Test job type detection
$videoJob = Videojob::factory()->make([
    'generation_parameters' => json_encode(['jobType' => 'new-type'])
]);
$jobType = JobType::fromVideoJob($videoJob);
$this->assertSame(JobType::NEW_TYPE, $jobType);

// Test processor creation
$processor = app(JobProcessorFactory::class)->getProcessor($videoJob);
$this->assertInstanceOf(NewTypeJobProcessor::class, $processor);
```

### Integration Tests
```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

$submitter = app(VideoJobSubmitter::class);
$submitter->submit($videoJob);

Queue::assertPushed(ProcessUnifiedJob::class);
```

## Migration Guide

### For Existing Code

The refactored job classes (`ProcessVideoJob`, `ProcessDeforumJob`, etc.) maintain **full backwards compatibility**. No changes are required to existing code.

However, for new features, we recommend using `ProcessUnifiedJob`:

**Before:**
```php
ProcessVideoJob::dispatch($videoJob, $frameCount)->onQueue($queueName);
```

**After:**
```php
ProcessUnifiedJob::dispatch($videoJob, $frameCount)->onQueue($queueName);
// Or even better:
app(VideoJobSubmitter::class)->submit($videoJob, $frameCount);
```

### Benefits of the New System

1. **Single Responsibility**: Each processor handles one job type
2. **Easy Extension**: Add new job types by creating a processor
3. **Code Reuse**: Common logic in AbstractJobProcessor
4. **Type Safety**: JobType enum prevents typos
5. **Testability**: Processors can be tested independently
6. **Consistency**: All jobs follow the same pattern

## Common Patterns

### Job Locking
All processors inherit job locking from `AbstractJobProcessor`:
```php
if ($this->isJobLocked($videoJob->id)) {
    // Already processing
    return;
}
$this->lockJob($videoJob->id);
try {
    // Process
} finally {
    $this->unlockJob($videoJob->id);
}
```

### Error Handling
```php
try {
    // Process
} catch (\Exception $e) {
    $this->unlockJob($videoJob->id);
    $this->logJobError($videoJob, 'ProcessorName', $e);
    throw $e;
}
```

### Stale Job Detection
```php
$this->markStaleJobsAsErrors(); // Marks jobs stuck in processing
```

## Configuration

Job timeouts and thresholds can be customized in processors:

```php
class CustomJobProcessor extends AbstractJobProcessor
{
    protected int $timeout = 1800; // 30 minutes
    protected int $staleThreshold = 5; // 5 minutes
}
```

## Troubleshooting

### Job Stuck in Processing
- Check if job is locked: `Cache::has("video_job_processing_{$jobId}")`
- Manually release lock: `Cache::forget("video_job_processing_{$jobId}")`
- Jobs automatically marked as error after stale threshold

### Wrong Processor Selected
- Verify `JobType::fromVideoJob()` logic
- Check `generation_parameters` JSON structure
- Ensure processor `supports()` method is correct

### Tests Failing
- Ensure `.env` file exists for tests
- Mock external services (FFMpeg, ComfyUI, etc.)
- Use `Queue::fake()` for job dispatch tests
