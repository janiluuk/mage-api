# API Endpoints Documentation

## Video Job Operations

### Add Soundtrack to Video Job
Add an audio track to a completed video job.

**Endpoint:** `POST /api/v1/video-jobs/add-soundtrack`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `video_job_id` (required, integer) - ID of the video job
- `soundtrack` (required, file) - Audio file (mp3, aac, wav, ogg, flac, max 50MB)
- `start_seconds` (optional, numeric) - Start time for audio in seconds
- `end_seconds` (optional, numeric) - End time for audio in seconds (must be > start_seconds)
- `output_name` (optional, string) - Name for the output file

**Response (200):**
```json
{
  "message": "Soundtrack added successfully",
  "video_job_id": 123,
  "video_url": "https://example.com/videos/output.mp4"
}
```

**Error Responses:**
- `403` - Unauthorized (not the video job owner)
- `422` - Video job not completed or validation error
- `500` - Processing error

---

### Extend Video Job
Create a continuation/extension of a completed Deforum video job.

**Endpoint:** `POST /api/v1/video-jobs/extend`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `video_job_id` (required, integer) - ID of the base video job to extend
- `length` (optional, numeric) - Video length in seconds (1-20)
- `prompt` (optional, string) - Generation prompt (max 2000 chars)
- `negative_prompt` (optional, string) - Negative prompt (max 2000 chars)
- `seed` (optional, integer) - Random seed
- `denoising` (optional, numeric) - Denoising strength (0-1)

**Response (201):**
```json
{
  "message": "Video job extension created successfully",
  "video_job_id": 456,
  "base_job_id": 123,
  "status": "pending",
  "extended_from": 123
}
```

**Error Responses:**
- `403` - Unauthorized (not the video job owner)
- `422` - Only Deforum jobs can be extended, or base job not completed

---

### Trim Video Job
Trim/clip a portion of a completed video job.

**Endpoint:** `POST /api/v1/video-jobs/trim`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `video_job_id` (required, integer) - ID of the video job to trim
- `start_seconds` (required, numeric) - Start time in seconds (>= 0)
- `end_seconds` (required, numeric) - End time in seconds (> start_seconds)
- `output_name` (optional, string) - Name for the trimmed output

**Response (201):**
```json
{
  "message": "Video trimmed successfully",
  "video_job_id": 789,
  "original_job_id": 123,
  "video_url": "https://example.com/videos/trimmed.mp4",
  "trim_info": {
    "start_seconds": 5.0,
    "end_seconds": 15.0,
    "duration": 10.0
  }
}
```

**Error Responses:**
- `403` - Unauthorized
- `422` - Video job not completed or invalid trim range

---

## Batch Processing

### List Batches
Get all batches for the authenticated user.

**Endpoint:** `GET /api/v1/batches`

**Authentication:** Required (Bearer Token)

**Query Parameters:**
- `status` (optional) - Filter by status: pending, processing, completed, failed, cancelled
- `per_page` (optional, default: 15) - Results per page

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "My Batch",
      "description": "Batch description",
      "status": "processing",
      "total_jobs": 10,
      "completed_jobs": 5,
      "failed_jobs": 0,
      "progress": 50,
      "started_at": "2026-01-13T12:00:00Z",
      "completed_at": null,
      "created_at": "2026-01-13T11:00:00Z"
    }
  ],
  "meta": { ... },
  "links": { ... }
}
```

---

### Create Batch
Create a new batch for processing multiple video jobs.

**Endpoint:** `POST /api/v1/batches`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `name` (required, string, max 255) - Batch name
- `description` (optional, string) - Batch description
- `settings` (optional, object) - Batch-wide settings

**Response (201):**
```json
{
  "message": "Batch created successfully",
  "batch": {
    "id": 1,
    "name": "My Batch",
    "status": "pending",
    ...
  }
}
```

---

### Get Batch Details
Get details of a specific batch including all associated video jobs.

**Endpoint:** `GET /api/v1/batches/{id}`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "id": 1,
  "name": "My Batch",
  "status": "processing",
  "video_jobs": [
    {
      "id": 123,
      "filename": "video1.mp4",
      "status": "finished",
      "progress": 100
    }
  ],
  ...
}
```

---

### Update Batch
Update batch details (cannot update while processing).

**Endpoint:** `PUT /api/v1/batches/{id}`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `name` (optional, string)
- `description` (optional, string)
- `settings` (optional, object)

**Response (200):**
```json
{
  "message": "Batch updated successfully",
  "batch": { ... }
}
```

---

### Delete Batch
Delete a batch (cannot delete while processing).

**Endpoint:** `DELETE /api/v1/batches/{id}`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "message": "Batch deleted successfully"
}
```

---

### Add Jobs to Batch
Add video jobs to a batch.

**Endpoint:** `POST /api/v1/batches/{id}/jobs`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `video_job_ids` (required, array of integers) - IDs of video jobs to add

**Response (200):**
```json
{
  "message": "Video jobs added to batch",
  "batch": { ... }
}
```

---

### Remove Jobs from Batch
Remove video jobs from a batch.

**Endpoint:** `DELETE /api/v1/batches/{id}/jobs`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `video_job_ids` (required, array of integers) - IDs of video jobs to remove

**Response (200):**
```json
{
  "message": "Video jobs removed from batch",
  "batch": { ... }
}
```

---

### Start Batch Processing
Start processing all jobs in a batch.

**Endpoint:** `POST /api/v1/batches/{id}/process`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "message": "Batch processing started",
  "batch": { ... }
}
```

**Error Responses:**
- `422` - Batch already processing or has no jobs

---

### Get Batch Status
Get real-time status and progress of a batch.

**Endpoint:** `GET /api/v1/batches/{id}/status`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "id": 1,
  "name": "My Batch",
  "status": "processing",
  "progress": 50,
  "total_jobs": 10,
  "completed_jobs": 5,
  "failed_jobs": 0,
  "started_at": "2026-01-13T12:00:00Z",
  "completed_at": null,
  "jobs": [
    {
      "id": 123,
      "filename": "video1.mp4",
      "status": "finished",
      "progress": 100,
      "estimated_time_left": 0,
      "pivot_status": "completed",
      "pivot_started_at": "2026-01-13T12:00:00Z",
      "pivot_completed_at": "2026-01-13T12:05:00Z"
    }
  ]
}
```

---

## Preset Management

### List Presets
Get all presets accessible to the authenticated user (own + public).

**Endpoint:** `GET /api/v1/presets`

**Authentication:** Required (Bearer Token)

**Query Parameters:**
- `category` (optional) - Filter by category
- `type` (optional) - Filter by type: video, image, animation
- `favorites_only` (optional, boolean) - Show only favorites
- `own_only` (optional, boolean) - Show only own presets
- `per_page` (optional, default: 15) - Results per page

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "My Preset",
      "description": "Preset description",
      "category": "vid2vid",
      "type": "video",
      "settings": { ... },
      "is_public": false,
      "is_favorite": true,
      "usage_count": 5,
      "last_used_at": "2026-01-13T12:00:00Z"
    }
  ],
  ...
}
```

---

### Create Preset
Create a new preset.

**Endpoint:** `POST /api/v1/presets`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `name` (required, string, max 255) - Preset name
- `description` (optional, string) - Preset description
- `category` (optional, string, max 50) - Preset category
- `type` (required, string) - Type: video, image, or animation
- `settings` (required, object) - Preset settings/parameters
- `is_public` (optional, boolean) - Make preset public
- `is_favorite` (optional, boolean) - Mark as favorite

**Response (201):**
```json
{
  "message": "Preset created successfully",
  "preset": { ... }
}
```

---

### Get Preset Details
Get details of a specific preset.

**Endpoint:** `GET /api/v1/presets/{id}`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "id": 1,
  "name": "My Preset",
  "settings": { ... },
  ...
}
```

---

### Update Preset
Update a preset (only own presets can be updated).

**Endpoint:** `PUT /api/v1/presets/{id}`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `name` (optional, string)
- `description` (optional, string)
- `category` (optional, string)
- `type` (optional, string)
- `settings` (optional, object)
- `is_public` (optional, boolean)
- `is_favorite` (optional, boolean)

**Response (200):**
```json
{
  "message": "Preset updated successfully",
  "preset": { ... }
}
```

---

### Delete Preset
Delete a preset (only own presets can be deleted).

**Endpoint:** `DELETE /api/v1/presets/{id}`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "message": "Preset deleted successfully"
}
```

---

### Mark Preset as Used
Increment usage count for a preset.

**Endpoint:** `POST /api/v1/presets/{id}/use`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "message": "Preset marked as used",
  "preset": { ... }
}
```

---

### Toggle Favorite
Toggle favorite status for a preset.

**Endpoint:** `POST /api/v1/presets/{id}/favorite`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "message": "Preset marked as favorite",
  "preset": { ... }
}
```

---

### Duplicate Preset
Create a copy of a preset (own or public).

**Endpoint:** `POST /api/v1/presets/{id}/duplicate`

**Authentication:** Required (Bearer Token)

**Request Parameters:**
- `name` (optional, string) - Name for the duplicated preset (defaults to "{original name} (Copy)")

**Response (201):**
```json
{
  "message": "Preset duplicated successfully",
  "preset": { ... }
}
```

---

### Get Preset Categories
Get a list of all available preset categories.

**Endpoint:** `GET /api/v1/presets/categories`

**Authentication:** Required (Bearer Token)

**Response (200):**
```json
{
  "categories": ["vid2vid", "deforum", "general"]
}
```

---

## Rate Limiting

All API endpoints are rate-limited to prevent abuse:

- **Authenticated users:** 60 requests per minute per user
- **Unauthenticated users:** 60 requests per minute per IP address

Rate limit information is included in response headers:
- `X-RateLimit-Limit` - Maximum requests allowed
- `X-RateLimit-Remaining` - Requests remaining in current window

When rate limit is exceeded, you'll receive a `429 Too Many Requests` response:
```json
{
  "message": "Too many requests. Please try again later.",
  "retry_after": 42
}
```

---

## Authentication

All endpoints require JWT authentication unless otherwise specified.

Include the JWT token in the Authorization header:
```
Authorization: Bearer {your_token}
```

To obtain a token, use the login endpoint:
```
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password"
}
```

---

## Error Handling

All endpoints follow consistent error response format:

**Validation Error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Forbidden (403):**
```json
{
  "message": "Unauthorized"
}
```

**Not Found (404):**
```json
{
  "message": "Resource not found."
}
```

**Server Error (500):**
```json
{
  "message": "An error occurred: {error_details}"
}
```
