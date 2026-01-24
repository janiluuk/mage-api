# Admin Panel UI Enhancement Requirements

## Overview
The backend API for load balancing, instance monitoring, and FFMpeg tracking is **fully implemented**. 
The remaining work is **frontend UI enhancements** in the **mage-app** repository.

## Completed Backend Features ✅

### 1. Load Balancing & Instance Management
- LoadBalancerService with queue-based instance selection
- Instance job tracking via `instance_jobs` table
- Automatic queue count updates
- Health-aware instance selection (prefers online over degraded instances)

### 2. Instance Monitoring & Metrics
- InstanceMetricsService for collecting metrics from ComfyUI and SD Forge
- Tracks: GPU/CPU/Memory utilization, current model, health status
- Historical metrics storage (24-hour rolling window)
- Scheduled metrics collection (runs every minute)

### 3. FFMpeg Encoding Tracking
- Encoding status tracking in `video_jobs` table
- Status updates: 'encoding', 'completed', 'failed'
- Timestamps: `encoding_started_at`, `encoding_completed_at`
- Integration in AsyncVideoProcessor

### 4. API Endpoints
All endpoints are documented in `docs/ADMIN_PANEL_API.md`:
- `GET /api/administration/instances/status` - Comprehensive status with all metrics
- `GET /api/administration/instances/{id}/metrics-history` - Historical metrics
- `GET /api/administration/instances/{id}/job-history` - Job processing history
- Standard CRUD endpoints for instance management

### 5. Comprehensive Testing
- 16 unit tests for LoadBalancerService
- 10 unit tests for InstanceMetricsService
- 8 feature tests for metrics collection
- Existing feature tests for load balancing and status API

## Required Frontend Work (mage-app) ⚠️

The admin panel UI needs to be enhanced to display the new metrics data:

### 1. Metrics Display
- **GPU Utilization**: Display percentage with visual indicator (e.g., progress bar)
- **CPU Utilization**: Display percentage with visual indicator
- **Memory Utilization**: Display percentage with visual indicator
- **Current Model**: Display model name currently loaded
- **Health Status**: Visual indicator (green=online, yellow=degraded, red=offline)

### 2. FFMpeg Worker Status Section
- **Active Encoding Count**: Number of videos currently encoding
- **Pending Encoding Count**: Number of videos waiting to encode
- **Active Jobs List**: List of currently encoding jobs with progress

### 3. Real-time Updates
- Auto-refresh status every 30 seconds (already implemented in API)
- Optional: WebSocket/Pusher integration for real-time updates

### 4. Charts & Visualization
- **Historical Metrics Charts**: Line charts for GPU/CPU/Memory over time
  - Use the `/api/administration/instances/{id}/metrics-history` endpoint
  - Display last 24 hours of data
- **Queue Size Trends**: Show queue size over time
- **Processing Time Stats**: Average job processing time per instance

### 5. Job History View
- Display recent jobs processed by each instance
- Show processing time and completion status
- Link to job details

## API Usage Examples

### Get Comprehensive Status
```javascript
fetch('/api/administration/instances/status')
  .then(response => response.json())
  .then(data => {
    // data.instances - array of instances with metrics
    // data.ffmpeg - FFMpeg worker status
    // data.summary - aggregate statistics
  });
```

### Get Historical Metrics
```javascript
fetch('/api/administration/instances/1/metrics-history')
  .then(response => response.json())
  .then(data => {
    // data.history - array of metrics over time (24 hours)
    // Use this for charts
  });
```

### Get Job History
```javascript
fetch('/api/administration/instances/1/job-history')
  .then(response => response.json())
  .then(data => {
    // data.jobs - array of recent jobs with processing times
  });
```

## UI Component Suggestions

### Recommended Libraries
- **Chart.js** or **Recharts**: For metrics visualization
- **PrimeVue ProgressBar**: For utilization percentages
- **PrimeVue Tag**: For health status indicators
- **PrimeVue DataTable**: For job history display

### Layout Suggestion
```
┌─────────────────────────────────────────────────────┐
│ Instance Overview                                   │
├─────────────────────────────────────────────────────┤
│ Instance 1: ComfyUI                [●Online]        │
│ ┌─────────────────────────────────────────────────┐ │
│ │ GPU: 75% [██████████░░░░░░░░░░]                 │ │
│ │ CPU: 45% [█████████░░░░░░░░░░░░]                │ │
│ │ MEM: 60% [████████████░░░░░░░░░]                │ │
│ │ Model: stable-diffusion-xl                      │ │
│ │ Queue: 2 | Processing: 1                        │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ [View History] [View Jobs] [Edit] [Disable]        │
├─────────────────────────────────────────────────────┤
│ FFMpeg Workers                                      │
│ Active: 2 | Pending: 1 | Total Queue: 3            │
│ • video_123.mp4 (40% complete)                      │
│ • video_456.mp4 (15% complete)                      │
└─────────────────────────────────────────────────────┘
```

## Reference Documentation
- **API Endpoints**: `docs/ADMIN_PANEL_API.md`
- **Services**: 
  - `app/Services/LoadBalancerService.php`
  - `app/Services/InstanceMetricsService.php`
- **Models**: 
  - `app/Models/GeneratorInstance.php`
  - `app/Models/InstanceJob.php`
  - `app/Models/Videojob.php`
