# API Endpoint Review - mage-app vs mage-api

## Review Date: 2026-01-18

### Purpose
This document reviews all API endpoints called by mage-app and compares them with available endpoints in mage-api to identify missing implementations.

---

## Summary

### Endpoints Called by mage-app

#### 1. Authentication & Profile
- ✅ `/api/auth/login` - Available
- ✅ `/api/auth/register` - Available  
- ✅ `/api/auth/logout` - Available
- ✅ `/api/auth/me` - Available
- ✅ `/api/auth/forgot-password` - Available
- ✅ `/api/auth/reset-password` - Available
- ✅ `/api/v2/me` (GET/PATCH) - Available
- ✅ `/api/v1/uploads/users/{id}/profile-image` - Available

#### 2. Video Jobs
- ✅ `/api/v1/video-jobs` (GET, POST) - Available via JSON:API
- ✅ `/api/v1/video-jobs/{id}` (GET, PATCH, DELETE) - Available
- ✅ `/api/v1/video-jobs/add-soundtrack` - Available
- ✅ `/api/v1/video-jobs/extend` - Available
- ✅ `/api/v1/video-jobs/trim` - Available
- ✅ `/api/video-jobs/{id}/status` - Available
- ✅ `/api/upload` - Available
- ✅ `/api/generate` - Available
- ✅ `/api/finalize` - Available
- ✅ `/api/cancelJob/{id}` - Available
- ✅ `/api/queue` - Available

#### 3. Custom Jobs
- ✅ `/api/v1/custom-jobs/process` - Available
- ✅ `/api/v1/custom-jobs/{id}/status` - Available

#### 4. Files
- ✅ `/api/v1/files` (GET, POST, DELETE) - Available
- ✅ `/api/v1/files/{id}` (GET) - Available
- ✅ `/api/v1/files/{id}/tags` (POST, PUT, DELETE) - Available
- ✅ `/api/v1/files/by-tags` - Available
- ✅ `/api/v1/files/by-tag/{id}` - Available
- ✅ `/api/v1/files/{id}/unzip` - Available
- ✅ `/api/v1/files/merge` - Available
- ✅ `/api/v1/files/{id}/import` - Available
- ✅ `/api/v1/files/{id}/transcode` - Available
- ✅ `/api/v1/files/{id}/attach-audio` - Available
- ✅ `/api/v1/files/quota` - Available

#### 5. Batches
- ✅ `/api/v1/batches` (GET, POST) - Available
- ✅ `/api/v1/batches/{id}` (GET, PUT, DELETE) - Available
- ✅ `/api/v1/batches/{id}/jobs` (POST, DELETE) - Available
- ✅ `/api/v1/batches/{id}/process` - Available
- ✅ `/api/v1/batches/{id}/status` - Available

#### 6. Presets
- ✅ `/api/v1/presets` (GET, POST) - Available
- ✅ `/api/v1/presets/{id}` (GET, PUT, DELETE) - Available
- ✅ `/api/v1/presets/categories` - Available
- ✅ `/api/v1/presets/{id}/use` - Available
- ✅ `/api/v1/presets/{id}/favorite` - Available
- ✅ `/api/v1/presets/{id}/duplicate` - Available

#### 7. Stats
- ✅ `/api/v1/stats` - Available
- ✅ `/api/v1/stats/recent` - Available

#### 8. Tags
- ✅ `/api/v1/tags` (GET, POST) - Available via JSON:API
- ✅ `/api/v1/tags/{id}` (GET, PATCH, DELETE) - Available

#### 9. Model Files
- ✅ `/api/v1/model-files` (GET) - Available via JSON:API

#### 10. Generators
- ✅ `/api/v1/generators` (GET) - Available via JSON:API

---

## ❌ MISSING ENDPOINTS

### 1. Story Generation API
**Called by:** `src/services/story/GenerationService.js`

**Missing Endpoints:**
- ❌ `POST /api/story/generate` - Start story generation
- ❌ `GET /api/story/batch/{batchId}` - Get batch status
- ❌ `POST /api/story/batch/{batchId}/pause` - Pause batch
- ❌ `POST /api/story/batch/{batchId}/resume` - Resume batch
- ❌ `DELETE /api/story/batch/{batchId}` - Cancel batch
- ❌ `POST /api/story/batch/{batchId}/frames` - Persist frame
- ❌ `POST /api/story/share` - Create share link
- ❌ WebSocket endpoint for live preview

**Priority:** HIGH - Used by Story Creator feature

### 2. Deforum Live Control API
**Called by:** `src/services/deforum/DeforumControlService.js`

**Missing Endpoints:**
- ❌ `POST /deforum/live` - Send live update
- ❌ `GET /deforum/live/status` - Get live status

**Priority:** MEDIUM - Used by Deforum control features

---

## 📋 IMPLEMENTATION PLAN

### Phase 1: Story Generation API (High Priority)

#### Endpoints to Implement:

1. **POST /api/v1/story/generate**
   - Create new story generation job
   - Accept generation config
   - Return batch ID and initial status
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `generate`
   - **Validation:** Story generation config

2. **GET /api/v1/story/batch/{batchId}**
   - Get batch status and progress
   - Return job list and status
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `getBatchStatus`

3. **POST /api/v1/story/batch/{batchId}/pause**
   - Pause batch processing
   - Update all jobs in batch to paused
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `pauseBatch`

4. **POST /api/v1/story/batch/{batchId}/resume**
   - Resume batch processing
   - Update all jobs in batch to queued
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `resumeBatch`

5. **DELETE /api/v1/story/batch/{batchId}**
   - Cancel batch processing
   - Cancel all jobs in batch
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `cancelBatch`

6. **POST /api/v1/story/batch/{batchId}/frames**
   - Persist frame from generation
   - Store frame data
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `persistFrame`

7. **POST /api/v1/story/share**
   - Create shareable link for story
   - Generate unique share token
   - **Controller:** `App\Http\Controllers\Api\V1\StoryController`
   - **Method:** `createShareLink`

8. **WebSocket: /ws/story/live/{batchId}**
   - WebSocket endpoint for live preview
   - Stream generation progress
   - **Implementation:** Laravel WebSockets or Pusher integration

#### Database Requirements:
- Use existing `Batch` model
- Use existing `Videojob` model
- May need `story_frames` table for frame persistence
- May need `story_shares` table for share links

#### Services:
- `App\Services\StoryGenerationService` - Main generation logic
- `App\Services\StoryBatchService` - Batch management
- `App\Services\StoryShareService` - Share link management

---

### Phase 2: Deforum Live Control API (Medium Priority)

#### Endpoints to Implement:

1. **POST /api/v1/deforum/live**
   - Send live control update to Deforum instance
   - Forward update to ComfyUI/Deforum service
   - **Controller:** `App\Http\Controllers\Api\V1\DeforumController`
   - **Method:** `sendLiveUpdate`

2. **GET /api/v1/deforum/live/status**
   - Get current live session status
   - Return active Deforum instance info
   - **Controller:** `App\Http\Controllers\Api\V1\DeforumController`
   - **Method:** `getLiveStatus`

#### Services:
- `App\Services\Deforum\DeforumLiveControlService` - Live control logic
- Integration with ComfyUI/Deforum backend

---

## Implementation Notes

### Story Generation API
- Should integrate with existing batch processing system
- May reuse or extend `BatchController` functionality
- WebSocket implementation should use Laravel Echo or Pusher
- Frame persistence needs efficient storage strategy

### Deforum Live Control API
- Requires connection to running Deforum/ComfyUI instance
- May need WebSocket connection for real-time updates
- Should handle instance availability gracefully

### Testing Requirements
- Integration tests for all new endpoints
- WebSocket connection tests
- Error handling tests
- Authorization tests (ensure auth:api middleware)

### Documentation
- API documentation for all new endpoints
- WebSocket connection protocol documentation
- Example requests/responses

---

## Priority Ranking

1. **HIGH PRIORITY:**
   - Story Generation API (used by active feature)

2. **MEDIUM PRIORITY:**
   - Deforum Live Control API (used by specialized feature)

3. **LOW PRIORITY:**
   - None identified

---

## Next Steps

1. ✅ Create this review document
2. ⏳ Implement Story Generation API endpoints
3. ⏳ Implement Deforum Live Control API endpoints
4. ⏳ Add WebSocket support for live previews
5. ⏳ Write integration tests
6. ⏳ Update API documentation

