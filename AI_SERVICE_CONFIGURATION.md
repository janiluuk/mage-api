# Local AI Service Configuration

This document describes how to configure and use the Local AI service for film production script and scene generation.

## Configuration

The Local AI service is configured via environment variables in your `.env` file:

```env
# Local AI Service Configuration
LOCAL_AI_BASE_URL=http://localhost:1234
LOCAL_AI_DEFAULT_MODEL=qwen-8b
LOCAL_AI_TIMEOUT=300
```

### Configuration Options

- **LOCAL_AI_BASE_URL**: The base URL of your local AI server (default: `http://localhost:1234`)
  - Should be an OpenAI-compatible API endpoint
  - Common local AI servers: LM Studio, Ollama, LocalAI, etc.
  
- **LOCAL_AI_DEFAULT_MODEL**: The default model to use for generation (default: `qwen-8b`)
  - This model will be used if no model is specified in the request
  
- **LOCAL_AI_TIMEOUT**: Request timeout in seconds (default: `300`)

## API Endpoints

### Get Available Models

Retrieve a list of all available models from the local AI server.

**Endpoint:** `GET /api/film-productions/ai/models`

**Authentication:** Required (JWT)

**Response:**
```json
{
  "success": true,
  "data": {
    "models": [
      {
        "id": "qwen-8b",
        "name": "qwen-8b",
        "object": "model",
        "created": 1234567890,
        "owned_by": "local"
      }
    ],
    "default_model": "qwen-8b"
  }
}
```

### Generate Script

Generate a screenplay script for a film production using AI.

**Endpoint:** `POST /api/film-productions/{productionId}/generate/script`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "prompt": "A sci-fi thriller about time travel",
  "options": {
    "model": "qwen-8b",
    "temperature": 0.7,
    "max_tokens": 4000
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "script": "TITLE: Time Travel Thriller\n\nINT. LABORATORY - NIGHT\n...",
    "production_id": 1,
    "model": "qwen-8b"
  }
}
```

### Generate Scene

Generate a scene description and create a video job for a shot.

**Endpoint:** `POST /api/film-productions/{productionId}/sequences/{sequenceId}/shots/{shotId}/generate/scene`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "prompt": "A dramatic sunset scene with a character walking",
  "options": {
    "model": "qwen-8b",
    "temperature": 0.7,
    "max_tokens": 1500,
    "style": "cinematic",
    "resolution": "1080p",
    "generator": "deforum"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "scene_data": {
      "description": "A cinematic scene with dramatic lighting...",
      "prompt": "A dramatic sunset scene with a character walking",
      "model": "qwen-8b",
      "style": "cinematic",
      "resolution": "1080p",
      "video_job_id": 123,
      "status": "pending",
      "generated_at": "2026-01-22T12:00:00.000000Z"
    },
    "shot_id": 1
  }
}
```

## Options Parameters

### Script Generation Options

- **model** (string): Model to use for generation (default: from config)
- **temperature** (float): Sampling temperature, 0.0-2.0 (default: 0.7)
- **max_tokens** (integer): Maximum tokens to generate (default: 4000)

### Scene Generation Options

- **model** (string): Model to use for generation (default: from config)
- **temperature** (float): Sampling temperature, 0.0-2.0 (default: 0.7)
- **max_tokens** (integer): Maximum tokens to generate (default: 1500)
- **style** (string): Visual style (default: "cinematic")
- **resolution** (string): Video resolution (default: "1080p")
- **generator** (string): Video generator type - "deforum" or "vid2vid" (default: "deforum")

## Local AI Server Requirements

The local AI server must implement the OpenAI-compatible API:

### Required Endpoints

1. **GET /v1/models** - List available models
   - Should return: `{"data": [{"id": "model-name", ...}]}`

2. **POST /v1/chat/completions** - Generate text
   - Accepts: `{"model": "model-name", "messages": [...], "temperature": 0.7, "max_tokens": 2000}`
   - Returns: `{"choices": [{"message": {"content": "generated text"}}]}`

### Compatible Servers

- **LM Studio**: Set server port and enable OpenAI-compatible API
- **Ollama**: Use `ollama serve` with OpenAI compatibility
- **LocalAI**: Configure with OpenAI-compatible endpoints
- **vLLM**: Use with OpenAI-compatible API server

## Usage Example

```php
use App\Services\AI\LocalAIService;

$aiService = app(LocalAIService::class);

// Get available models
$models = $aiService->getAvailableModels();

// Generate a script
$script = $aiService->generateScript(
    "A thriller about a detective solving a mystery",
    ['model' => 'qwen-8b', 'temperature' => 0.8]
);

// Generate a scene description
$scene = $aiService->generateSceneDescription(
    "A dramatic confrontation in a dark alley",
    ['model' => 'qwen-8b']
);
```

## Error Handling

The service will throw `GuzzleHttp\Exception\GuzzleException` if:
- The AI server is unreachable
- The request times out
- The server returns an error response

All errors are logged to Laravel's log system with context information.

## Testing

To test the AI service connection:

```bash
# Check if service is available
php artisan tinker
>>> $service = app(\App\Services\AI\LocalAIService::class);
>>> $service->isAvailable(); // Returns true/false
>>> $service->getAvailableModels(); // Returns array of models
```

