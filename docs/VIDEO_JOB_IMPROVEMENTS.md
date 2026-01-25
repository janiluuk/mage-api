# Video Job Processing Improvements

This document describes the new features added to improve video job processing, enabling users to work more efficiently with video variants, job extensions, post-processing effects, and real-time progress updates.

## Features

### 1. Job Variants System

Create multiple variants of a video job using different AI models simultaneously. This allows users to:
- Generate the same video with 3 different models in parallel
- Preview and compare results
- Accept the best variant

#### API Endpoints

**Create Variants**
```http
POST /api/v1/video-jobs/{id}/variants
Authorization: Bearer {token}
Content-Type: application/json

{
  "model_ids": [1, 2, 3],
  "preview_frames": 10,
  "auto_process": true
}
```

**Get Variants Status**
```http
GET /api/v1/video-jobs/{id}/variants
Authorization: Bearer {token}
```

**Process Variants**
```http
POST /api/v1/video-jobs/{id}/variants/process
Authorization: Bearer {token}
Content-Type: application/json

{
  "preview_frames": 10
}
```

#### Example Usage

```php
// Create 3 variants with different models
$response = Http::withToken($token)->post("/api/v1/video-jobs/{$jobId}/variants", [
    'model_ids' => [1, 2, 3],
    'preview_frames' => 10,
    'auto_process' => true
]);

// Check variant status
$status = Http::withToken($token)->get("/api/v1/video-jobs/{$jobId}/variants");

foreach ($status['variants'] as $variant) {
    echo "Variant {$variant['variant_name']}: {$variant['status']} ({$variant['progress']}%)\n";
}
```

### 2. Job Extension with Parameter Reuse

Extend existing jobs using the same parameters, with optional overrides. Perfect for:
- Creating continuations of videos
- Experimenting with parameter variations
- Batch processing with consistent settings

#### API Endpoint

```http
POST /api/v1/video-jobs/{id}/extend-with-params
Authorization: Bearer {token}
Content-Type: application/json

{
  "override_params": {
    "prompt": "New prompt for extension",
    "seed": 99999,
    "model_id": 5
  }
}
```

#### Example Usage

```php
// Extend with same parameters
$response = Http::withToken($token)->post("/api/v1/video-jobs/{$jobId}/extend-with-params");

// Extend with overridden prompt
$response = Http::withToken($token)->post("/api/v1/video-jobs/{$jobId}/extend-with-params", [
    'override_params' => [
        'prompt' => 'Continuation of the original scene',
        'seed' => rand(1, 999999)
    ]
]);
```

### 3. Post-Processing Effects

Apply FFmpeg effects to finished videos. Available effects include:

- **fade_in** - Fade in from black at the start
- **fade_out** - Fade out to black at the end
- **brightness** - Adjust brightness (-1.0 to 1.0)
- **contrast** - Adjust contrast (0.0 to 2.0)
- **saturation** - Adjust color saturation (0.0 to 3.0)
- **sharpen** - Sharpen the video
- **blur** - Apply blur effect
- **denoise** - Remove noise from video
- **scale** - Resize video
- **crop** - Crop video to specific dimensions
- **rotate** - Rotate video

#### API Endpoints

**Get Available Effects**
```http
GET /api/v1/video-jobs/post-process/effects
Authorization: Bearer {token}
```

**Apply Post-Processing**
```http
POST /api/v1/video-jobs/{id}/post-process
Authorization: Bearer {token}
Content-Type: application/json

{
  "effects": [
    {
      "name": "fade_in",
      "params": {
        "duration": 1
      }
    },
    {
      "name": "brightness",
      "params": {
        "value": 0.2
      }
    }
  ]
}
```

#### Example Usage

```php
// Get available effects
$effects = Http::withToken($token)->get('/api/v1/video-jobs/post-process/effects');

// Apply fade in and brightness adjustment
$response = Http::withToken($token)->post("/api/v1/video-jobs/{$jobId}/post-process", [
    'effects' => [
        [
            'name' => 'fade_in',
            'params' => ['duration' => 2]
        ],
        [
            'name' => 'brightness',
            'params' => ['value' => 0.15]
        ],
        [
            'name' => 'sharpen',
            'params' => []
        ]
    ]
]);
```

### 4. Live Progress Updates via WebSocket

Real-time progress updates broadcast to users while videos are encoding. Users can see:
- Current frame being processed
- Progress percentage
- Estimated time remaining
- Live frame previews (if available)

#### WebSocket Channel

Subscribe to the private channel for your video job:

```javascript
// Using Laravel Echo with Pusher
Echo.private(`video-job.${jobId}`)
    .listen('.progress.updated', (e) => {
        console.log(`Progress: ${e.progress}%`);
        console.log(`Frame: ${e.current_frame}/${e.total_frames}`);
        console.log(`ETA: ${e.estimated_time_left} seconds`);
        
        // Update UI
        updateProgressBar(e.progress);
        updateFrameDisplay(e.frame_url);
    });
```

#### Broadcast Data Structure

```json
{
  "id": 123,
  "status": "processing",
  "progress": 45.5,
  "current_frame": 455,
  "total_frames": 1000,
  "frame_url": "http://example.com/frames/frame-455.png",
  "estimated_time_left": 120,
  "job_time": 180,
  "preview_url": "http://example.com/preview.gif",
  "finished_url": null
}
```

### 5. Enhanced Load Balancing

Improved parallel job execution with configurable limits:

#### Configuration (.env)

```bash
# Enable async processing with real-time progress
VIDEO_PROCESSING_ASYNC=true

# Maximum concurrent jobs globally
VIDEO_PROCESSING_MAX_CONCURRENT=3

# Maximum concurrent jobs per model (for load balancing)
VIDEO_PROCESSING_MAX_PER_MODEL=1

# Enable parallel processing of job variants
VIDEO_PROCESSING_PARALLEL_VARIANTS=true

# Enable live progress broadcasting
VIDEO_PROCESSING_BROADCAST_PROGRESS=true

# Queue priorities (higher = more priority)
VIDEO_PROCESSING_PREVIEW_PRIORITY=10
VIDEO_PROCESSING_FULL_PRIORITY=5
VIDEO_PROCESSING_VARIANT_PRIORITY=8
VIDEO_PROCESSING_POST_PRIORITY=3
```

## Database Schema

### video_job_variants table

```sql
CREATE TABLE video_job_variants (
  id BIGINT UNSIGNED PRIMARY KEY,
  base_video_job_id BIGINT UNSIGNED NOT NULL,
  variant_video_job_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED,
  variant_name VARCHAR(255),
  description TEXT,
  variant_order INT DEFAULT 0,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (base_video_job_id) REFERENCES video_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_video_job_id) REFERENCES video_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (model_id) REFERENCES model_files(id) ON DELETE SET NULL,
  UNIQUE (base_video_job_id, variant_video_job_id)
);
```

## Testing

Run the test suite:

```bash
# Run all video job tests
php artisan test --filter VideoJob

# Run specific test suites
php artisan test tests/Feature/VideoJobVariantsTest.php
php artisan test tests/Feature/VideoJobPostProcessingTest.php
php artisan test tests/Feature/VideoJobLiveProgressTest.php
php artisan test tests/Feature/VideoJobExtensionWithParamsTest.php
```

## Performance Considerations

1. **Parallel Processing**: Variants are processed in parallel using Laravel's queue system. Configure `VIDEO_PROCESSING_MAX_CONCURRENT` based on your server resources.

2. **Load Balancing**: The system automatically distributes jobs across available instances using the LoadBalancerService.

3. **Real-time Updates**: WebSocket broadcasting adds minimal overhead. Throttle updates using `VIDEO_PROCESSING_PROGRESS_THRESHOLD`.

4. **Post-Processing**: FFmpeg operations are CPU-intensive. Consider running them on separate queue workers.

## Migration

Run migrations to create the new tables:

```bash
php artisan migrate
```

## Services

### VideoJobVariantService

Manages creation and processing of job variants.

```php
use App\Services\VideoJobs\VideoJobVariantService;

$service = new VideoJobVariantService();

// Create variants
$variants = $service->createVariants($baseJob, [$model1, $model2, $model3], $previewFrames);

// Process variants in parallel
$service->processVariantsInParallel($baseJob, $previewFrames);

// Get status
$status = $service->getVariantsStatus($baseJob);
```

### VideoPostProcessor

Applies FFmpeg effects to finished videos.

```php
use App\Services\VideoJobs\VideoPostProcessor;

$processor = new VideoPostProcessor();

// Apply effects
$success = $processor->applyEffects($videoJob, [
    'fade_in' => ['duration' => 1],
    'brightness' => ['value' => 0.2],
    'sharpen' => []
]);

// Get available effects
$effects = $processor->getAvailableEffects();
```

## Best Practices

1. **Use Preview Frames**: Test with preview frames (e.g., 10) before generating full videos.

2. **Limit Variants**: Start with 2-3 variants to balance quality vs. processing time.

3. **Monitor Progress**: Use WebSocket updates to provide real-time feedback to users.

4. **Queue Configuration**: Set up dedicated queue workers for video processing to avoid blocking other tasks.

5. **Post-Processing**: Apply effects after user approval to save processing time on rejected videos.

## Troubleshooting

### Variants not processing in parallel

- Check `VIDEO_PROCESSING_PARALLEL_VARIANTS` is `true`
- Verify queue workers are running: `php artisan queue:work`
- Increase `VIDEO_PROCESSING_MAX_CONCURRENT`

### WebSocket updates not received

- Verify `BROADCAST_DRIVER` is set to `redis` or `pusher`
- Check broadcasting configuration in `config/broadcasting.php`
- Ensure user is authenticated and authorized for the channel

### Post-processing fails

- Verify FFmpeg is installed: `ffmpeg -version`
- Check file permissions on video files
- Review logs for specific FFmpeg errors

## License

This feature is part of the Mage API project.
