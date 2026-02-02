# Film Production API Documentation

## Overview

This API provides endpoints for managing film productions, sequences, and shots, with AI-powered script and scene generation capabilities. The structure is inspired by Kitsu's production management system.

## Base URL

All endpoints are prefixed with `/api/film-productions` and require authentication (`auth:api` middleware).

## Endpoints

### Productions

#### List All Productions
```
GET /api/film-productions
```
Returns all productions for the authenticated user.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "My Film",
      "description": "Film description",
      "status": "draft",
      "script": null,
      "thumbnail": null,
      "user_id": 1,
      "created_at": "2026-01-22T00:00:00.000000Z",
      "updated_at": "2026-01-22T00:00:00.000000Z"
    }
  ]
}
```

#### Get Production by ID
```
GET /api/film-productions/{id}
```
Returns a single production with its sequences and shots.

#### Create Production
```
POST /api/film-productions
```
**Body:**
```json
{
  "name": "My Film",
  "description": "Film description",
  "status": "draft",
  "script": null,
  "thumbnail": null,
  "metadata": {}
}
```

#### Update Production
```
PUT /api/film-productions/{id}
```
**Body:** Same as create (all fields optional)

#### Delete Production
```
DELETE /api/film-productions/{id}
```

### Sequences

#### List Sequences for a Production
```
GET /api/film-productions/{productionId}/sequences
```

#### Get Sequence by ID
```
GET /api/film-productions/{productionId}/sequences/{sequenceId}
```

#### Create Sequence
```
POST /api/film-productions/{productionId}/sequences
```
**Body:**
```json
{
  "name": "Sequence 1",
  "description": "Sequence description",
  "script": null,
  "order": 1,
  "metadata": {}
}
```

#### Update Sequence
```
PUT /api/film-productions/{productionId}/sequences/{sequenceId}
```

#### Delete Sequence
```
DELETE /api/film-productions/{productionId}/sequences/{sequenceId}
```

### Shots

#### List Shots for a Sequence
```
GET /api/film-productions/{productionId}/sequences/{sequenceId}/shots
```

#### Get Shot by ID
```
GET /api/film-productions/{productionId}/sequences/{sequenceId}/shots/{shotId}
```

#### Create Shot
```
POST /api/film-productions/{productionId}/sequences/{sequenceId}/shots
```
**Body:**
```json
{
  "name": "Shot 1",
  "description": "Shot description",
  "duration": 5.0,
  "order": 1,
  "scene_data": null,
  "metadata": {}
}
```

#### Update Shot
```
PUT /api/film-productions/{productionId}/sequences/{sequenceId}/shots/{shotId}
```

#### Delete Shot
```
DELETE /api/film-productions/{productionId}/sequences/{sequenceId}/shots/{shotId}
```

### AI Generation

#### Generate Script for Production
```
POST /api/film-productions/{id}/generate/script
```
**Body:**
```json
{
  "prompt": "A short film about a robot learning to love",
  "options": {
    "style": "screenplay",
    "length": 5
  }
}
```

**Response:**
```json
{
  "data": {
    "script": "Generated script content...",
    "production_id": 1
  }
}
```

#### Generate Scene for Shot
```
POST /api/film-productions/{productionId}/sequences/{sequenceId}/shots/{shotId}/generate/scene
```
**Body:**
```json
{
  "prompt": "A close-up of a robot's face, cinematic lighting, emotional expression",
  "options": {
    "style": "cinematic",
    "resolution": "1080p"
  }
}
```

**Response:**
```json
{
  "data": {
    "scene_data": {
      "prompt": "...",
      "style": "cinematic",
      "resolution": "1080p",
      "video_job_id": 123,
      "status": "pending",
      "generatedAt": "2026-01-22T00:00:00.000000Z"
    },
    "shot_id": 1
  }
}
```

## Database Schema

### film_productions
- id (bigint, primary key)
- name (string)
- description (text, nullable)
- status (string, default: 'draft')
- script (text, nullable) - AI-generated script
- thumbnail (string, nullable)
- user_id (foreign key to users)
- metadata (json, nullable)
- timestamps
- soft deletes

### sequences
- id (bigint, primary key)
- film_production_id (foreign key)
- name (string)
- description (text, nullable)
- script (text, nullable)
- order (integer, default: 1)
- metadata (json, nullable)
- timestamps
- soft deletes

### shots
- id (bigint, primary key)
- film_production_id (foreign key)
- sequence_id (foreign key)
- name (string)
- description (text, nullable)
- duration (decimal, nullable) - Duration in seconds
- order (integer, default: 1)
- scene_data (json, nullable) - AI-generated scene data
- metadata (json, nullable)
- timestamps
- soft deletes

## Status Values

### Production Status
- `draft` - Initial state
- `in_progress` - Active production
- `post_production` - Post-production phase
- `completed` - Finished
- `on_hold` - Temporarily paused

## Notes

1. All endpoints require authentication via `auth:api` middleware
2. Users can only access their own productions
3. AI generation endpoints create video jobs that integrate with the existing video generation system
4. Script generation currently returns placeholders - integrate with your preferred AI service (OpenAI, Anthropic, etc.)
5. Scene generation creates VideoJob records that are processed by the existing video generation pipeline

## Integration with Video Generation

Scene generation creates `Videojob` records with:
- `generator`: 'deforum' or 'vid2vid'
- `generation_parameters`: Includes shot_id, production_id, sequence_id
- `status`: 'pending' (processed by existing queue system)

The generated video URL will be available in the VideoJob once processing completes.

