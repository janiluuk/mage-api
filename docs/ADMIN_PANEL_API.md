# Admin Panel API Documentation

## Overview
The admin panel uses RESTful API endpoints to manage generator instances and monitor system status.

## Authentication
All endpoints require authentication and administrator privileges via `AuthorizationChecker` and `IsAdministratorChecker` middleware.

## Endpoints

### Instance Management

#### GET `/api/administration/generator-instances`
Get all generator instances (basic CRUD).

**Response:**
```json
[
  {
    "id": 1,
    "name": "ComfyUI Instance 1",
    "url": "http://localhost:8188",
    "type": "comfyui",
    "enabled": true,
    "queue_size": 2,
    "processing_count": 1,
    "created_at": "2026-01-20T10:00:00.000000Z",
    "updated_at": "2026-01-20T10:00:00.000000Z"
  }
]
```

#### POST `/api/administration/generator-instances`
Create a new instance.

**Request Body:**
```json
{
  "name": "New Instance",
  "url": "http://localhost:8189",
  "type": "comfyui",
  "enabled": true
}
```

#### GET `/api/administration/generator-instances/{id}`
Get a specific instance.

#### PUT/PATCH `/api/administration/generator-instances/{id}`
Update an instance.

#### DELETE `/api/administration/generator-instances/{id}`
Delete an instance.

#### PATCH `/api/administration/generator-instances/{id}/toggle`
Toggle enabled/disabled status.

---

### Comprehensive Status & Monitoring

#### GET `/api/administration/instances/status`
**Get comprehensive status with all metrics and FFMpeg status.**

**Response:**
```json
{
  "instances": [
    {
      "id": 1,
      "name": "ComfyUI Instance 1",
      "url": "http://localhost:8188",
      "type": "comfyui",
      "enabled": true,
      "queue_size": 2,
      "processing_count": 1,
      "total_load": 3,
      "current_model": "stable-diffusion-xl",
      "gpu_utilization": 75,
      "cpu_utilization": 45,
      "memory_utilization": 60,
      "health_status": "online",
      "last_health_check_at": "2026-01-20T10:00:00.000000Z",
      "last_queue_check_at": "2026-01-20T10:00:00.000000Z",
      "current_job": {
        "id": 123,
        "video_job_id": 456,
        "video_job": {
          "id": 456,
          "prompt": "A beautiful landscape",
          "status": "processing",
          "progress": 65
        },
        "started_at": "2026-01-20T09:55:00.000000Z"
      },
      "recent_jobs": [
        {
          "id": 122,
          "video_job_id": 455,
          "processing_time_seconds": 180,
          "completed_at": "2026-01-20T09:50:00.000000Z"
        }
      ]
    }
  ],
  "ffmpeg": {
    "active_encoding_count": 2,
    "pending_encoding_count": 1,
    "total_queue_size": 3,
    "active_jobs": [
      {
        "id": 789,
        "filename": "video.mp4",
        "started_at": "2026-01-20T09:58:00.000000Z",
        "status": "encoding",
        "progress": 40
      }
    ]
  },
  "summary": {
    "total_instances": 4,
    "enabled_instances": 3,
    "online_instances": 3,
    "total_queue_size": 5,
    "total_processing": 2
  }
}
```

#### GET `/api/administration/instances/{id}/metrics-history`
Get historical metrics for an instance (last 24 hours).

**Response:**
```json
{
  "instance": {
    "id": 1,
    "name": "ComfyUI Instance 1"
  },
  "history": [
    {
      "recorded_at": "2026-01-20T10:00:00.000000Z",
      "gpu_utilization": 75,
      "cpu_utilization": 45,
      "memory_utilization": 60,
      "queue_size": 2,
      "processing_count": 1,
      "health_status": "online",
      "current_model": "stable-diffusion-xl"
    }
  ]
}
```

#### GET `/api/administration/instances/{id}/job-history`
Get job processing history for an instance.

**Response:**
```json
{
  "instance": {
    "id": 1,
    "name": "ComfyUI Instance 1"
  },
  "jobs": [
    {
      "id": 122,
      "video_job_id": 455,
      "processing_time_seconds": 180,
      "assigned_at": "2026-01-20T09:45:00.000000Z",
      "started_at": "2026-01-20T09:47:00.000000Z",
      "completed_at": "2026-01-20T09:50:00.000000Z",
      "video_job": {
        "id": 455,
        "prompt": "A beautiful landscape",
        "generator": "comfyui"
      }
    }
  ]
}
```

## Admin Panel Usage

The admin panel (`/administration/instances`) uses these endpoints:

1. **Initial Load**: Calls `/api/administration/instances/status` to get all data with metrics
2. **CRUD Operations**: Uses `/api/administration/generator-instances` endpoints
3. **Auto-refresh**: Re-fetches status every 30 seconds

## Data Fields Explained

### Instance Metrics
- `queue_size`: Number of jobs waiting to be processed
- `processing_count`: Number of jobs currently being processed
- `total_load`: queue_size + processing_count (for load balancing)
- `gpu_utilization`: GPU usage percentage (0-100)
- `cpu_utilization`: CPU usage percentage (0-100)
- `memory_utilization`: Memory usage percentage (0-100)
- `current_model`: Currently loaded model name
- `health_status`: `online`, `offline`, or `degraded`
- `last_health_check_at`: Timestamp of last metrics collection

### FFMpeg Status
- `active_encoding_count`: Currently encoding videos
- `pending_encoding_count`: Videos waiting to be encoded
- `total_queue_size`: Total encoding queue
- `active_jobs`: Array of currently encoding jobs with progress

## Testing

Test files:
- `tests/Feature/InstanceStatusApiTest.php` - API endpoint tests
- `tests/Feature/InstanceLoadBalancingTest.php` - Load balancing logic tests

