# Quick Reference - Video Job Processing

## API Quick Reference

### Create Job Variants
```bash
curl -X POST "https://api.example.com/api/v1/video-jobs/123/variants" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model_ids": [1, 2, 3],
    "preview_frames": 10,
    "auto_process": true
  }'
```

### Get Variant Status
```bash
curl "https://api.example.com/api/v1/video-jobs/123/variants" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Extend Job
```bash
curl -X POST "https://api.example.com/api/v1/video-jobs/123/extend-with-params" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "override_params": {
      "prompt": "New prompt",
      "seed": 99999
    }
  }'
```

### Apply Post-Processing
```bash
curl -X POST "https://api.example.com/api/v1/video-jobs/123/post-process" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "effects": [
      {"name": "fade_in", "params": {"duration": 1}},
      {"name": "brightness", "params": {"value": 0.2}}
    ]
  }'
```

## WebSocket Client Examples

### JavaScript (Laravel Echo)
```javascript
import Echo from 'laravel-echo';

const echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    encrypted: true
});

// Subscribe to video job progress
echo.private(`video-job.${jobId}`)
    .listen('.progress.updated', (data) => {
        updateProgress(data.progress);
        updateFrame(data.current_frame, data.total_frames);
        updateETA(data.estimated_time_left);
    });
```

### Vue.js Component
```vue
<template>
  <div class="video-progress">
    <progress :value="progress" max="100"></progress>
    <p>Frame {{ currentFrame }} / {{ totalFrames }}</p>
    <p>ETA: {{ estimatedTime }}s</p>
  </div>
</template>

<script>
export default {
  data() {
    return {
      progress: 0,
      currentFrame: 0,
      totalFrames: 0,
      estimatedTime: 0
    }
  },
  mounted() {
    Echo.private(`video-job.${this.jobId}`)
      .listen('.progress.updated', (e) => {
        this.progress = e.progress;
        this.currentFrame = e.current_frame;
        this.totalFrames = e.total_frames;
        this.estimatedTime = e.estimated_time_left;
      });
  }
}
</script>
```

### React Hook
```jsx
import { useEffect, useState } from 'react';
import Echo from 'laravel-echo';

function useVideoProgress(jobId) {
  const [progress, setProgress] = useState(0);
  const [frame, setFrame] = useState({ current: 0, total: 0 });
  const [eta, setEta] = useState(0);

  useEffect(() => {
    const channel = Echo.private(`video-job.${jobId}`);
    
    channel.listen('.progress.updated', (data) => {
      setProgress(data.progress);
      setFrame({ current: data.current_frame, total: data.total_frames });
      setEta(data.estimated_time_left);
    });

    return () => channel.stopListening('.progress.updated');
  }, [jobId]);

  return { progress, frame, eta };
}
```

## PHP Service Usage

### VideoJobVariantService
```php
use App\Services\VideoJobs\VideoJobVariantService;

$service = app(VideoJobVariantService::class);

// Create variants
$variants = $service->createVariants(
    $baseJob,
    [1, 2, 3], // model IDs
    10 // preview frames
);

// Process in parallel
$service->processVariantsInParallel($baseJob, 10);

// Get status
$status = $service->getVariantsStatus($baseJob);
```

### VideoPostProcessor
```php
use App\Services\VideoJobs\VideoPostProcessor;

$processor = app(VideoPostProcessor::class);

// Get available effects
$effects = $processor->getAvailableEffects();

// Apply effects
$success = $processor->applyEffects($videoJob, [
    'fade_in' => ['duration' => 1],
    'brightness' => ['value' => 0.2],
    'sharpen' => []
]);
```

## Configuration Presets

### Development (Low Resource)
```env
VIDEO_PROCESSING_ASYNC=false
VIDEO_PROCESSING_MAX_CONCURRENT=1
VIDEO_PROCESSING_MAX_PER_MODEL=1
VIDEO_PROCESSING_PARALLEL_VARIANTS=false
VIDEO_PROCESSING_BROADCAST_PROGRESS=false
```

### Production (Balanced)
```env
VIDEO_PROCESSING_ASYNC=true
VIDEO_PROCESSING_MAX_CONCURRENT=3
VIDEO_PROCESSING_MAX_PER_MODEL=1
VIDEO_PROCESSING_PARALLEL_VARIANTS=true
VIDEO_PROCESSING_BROADCAST_PROGRESS=true
```

### Production (High Performance)
```env
VIDEO_PROCESSING_ASYNC=true
VIDEO_PROCESSING_MAX_CONCURRENT=10
VIDEO_PROCESSING_MAX_PER_MODEL=3
VIDEO_PROCESSING_PARALLEL_VARIANTS=true
VIDEO_PROCESSING_BROADCAST_PROGRESS=true
```

## Common Workflows

### Workflow 1: Create and Compare Variants
```php
// 1. Create base job
$job = Videojob::create([...]);

// 2. Create variants with 3 models
POST /api/v1/video-jobs/{$job->id}/variants
{
  "model_ids": [1, 2, 3],
  "preview_frames": 10,
  "auto_process": true
}

// 3. Monitor progress (WebSocket)
Echo.private('video-job.{id}').listen('.progress.updated', ...);

// 4. Review and accept best variant
// 5. Generate full video for selected variant
POST /api/v1/video-jobs/{$variantId}/variants/process
{
  "preview_frames": 0
}
```

### Workflow 2: Extend Video Series
```php
// 1. Create first video
$job1 = Videojob::create([...]);

// 2. After completion, extend with same parameters
POST /api/v1/video-jobs/{$job1->id}/extend-with-params

// 3. Extend again with modified prompt
POST /api/v1/video-jobs/{$job1->id}/extend-with-params
{
  "override_params": {
    "prompt": "Continuation scene"
  }
}
```

### Workflow 3: Post-Process Approved Video
```php
// 1. User approves video
$job->update(['status' => 'approved']);

// 2. Apply post-processing
POST /api/v1/video-jobs/{$job->id}/post-process
{
  "effects": [
    {"name": "fade_in", "params": {"duration": 1}},
    {"name": "fade_out", "params": {"start_time": 28, "duration": 2}},
    {"name": "brightness", "params": {"value": 0.1}},
    {"name": "sharpen", "params": {}}
  ]
}
```

## Testing Commands

```bash
# Run all video job tests
php artisan test --filter VideoJob

# Run specific test file
php artisan test tests/Feature/VideoJobVariantsTest.php

# Run with coverage
php artisan test --coverage

# Run single test method
php artisan test --filter test_can_create_variants_with_different_models
```

## Queue Commands

```bash
# Start queue worker
php artisan queue:work

# With priority queues
php artisan queue:work --queue=high,medium,low

# Monitor queue status
php artisan queue:monitor

# Clear failed jobs
php artisan queue:flush

# Retry failed jobs
php artisan queue:retry all
```

## Debugging

### Enable Debug Logging
```php
// In .env
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log
```

### Monitor Queue Jobs
```php
// Check pending jobs
DB::table('jobs')->count();

// Check failed jobs
DB::table('failed_jobs')->get();
```

### WebSocket Connection Test
```javascript
Echo.connector.pusher.connection.bind('connected', function() {
    console.log('WebSocket connected');
});

Echo.connector.pusher.connection.bind('error', function(err) {
    console.error('WebSocket error:', err);
});
```

## Performance Tuning

### Optimize Queue Workers
```bash
# Run multiple workers
php artisan queue:work --queue=high &
php artisan queue:work --queue=medium &
php artisan queue:work --queue=low &

# With supervisor
[program:laravel-worker]
command=php /path/to/artisan queue:work --queue=high,medium,low --sleep=3 --tries=3
```

### Database Indexes
```sql
-- Already included in migrations
CREATE INDEX idx_variants_base_job ON video_job_variants(base_video_job_id);
CREATE INDEX idx_variants_status ON video_job_variants(status);
```

### Caching
```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Clear all caches
php artisan optimize:clear
```

## Security Checklist

- ✅ All endpoints require authentication
- ✅ Authorization checks for job ownership
- ✅ Input validation on all requests
- ✅ File path sanitization
- ✅ FFmpeg command escaping
- ✅ Private WebSocket channels
- ✅ Rate limiting on API endpoints

## Monitoring

### Key Metrics to Track
- Average job processing time
- Variant creation rate
- Post-processing success rate
- WebSocket connection count
- Queue depth and latency

### Recommended Tools
- Laravel Telescope (development)
- Laravel Horizon (queue monitoring)
- Sentry (error tracking)
- New Relic / Datadog (APM)

## Support

For issues or questions:
1. Check documentation: `docs/VIDEO_JOB_IMPROVEMENTS.md`
2. Review test examples in `tests/Feature/VideoJob*.php`
3. Check logs: `storage/logs/laravel.log`
4. Open GitHub issue with details
