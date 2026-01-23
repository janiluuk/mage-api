# ComfyUI Workflow Examples

This directory contains example ComfyUI workflow files that can be used with the `/api/comfyui/workflow/process` endpoint.

## Basic Workflow (`comfyui_workflow_basic.json`)

A simple text-to-image workflow using Stable Diffusion XL. This workflow:
- Uses SD XL base 1.0 model
- Generates 1024x1024 images
- Supports custom prompts and negative prompts
- Uses KSampler with configurable parameters

### Usage

#### 1. Submit workflow with default parameters

```bash
curl -X POST https://your-api-domain.com/api/comfyui/workflow/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d @comfyui_workflow_basic.json
```

#### 2. Submit workflow with custom prompt

```bash
curl -X POST https://your-api-domain.com/api/comfyui/workflow/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "workflow": '$(cat comfyui_workflow_basic.json)',
    "inputs": {
      "prompt": "a majestic mountain landscape at sunset",
      "negative_prompt": "blurry, low quality, distorted",
      "seed": 42,
      "steps": 30,
      "cfg": 7.5
    }
  }'
```

#### 3. Submit workflow file with inputs

```bash
curl -X POST https://your-api-domain.com/api/comfyui/workflow/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "workflow_file=@comfyui_workflow_basic.json" \
  -F "inputs[prompt]=a futuristic city with flying cars" \
  -F "inputs[seed]=12345" \
  -F "inputs[steps]=25"
```

#### 4. Wait for completion

```bash
curl -X POST https://your-api-domain.com/api/comfyui/workflow/process \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "workflow": '$(cat comfyui_workflow_basic.json)',
    "inputs": {
      "prompt": "a beautiful forest scene"
    },
    "wait_for_completion": true,
    "max_wait_seconds": 300
  }'
```

### Response

The endpoint returns a JSON response with:

```json
{
  "success": true,
  "prompt_id": "abc123-def456-ghi789",
  "status": "queued",
  "message": "Workflow queued successfully. Use the prompt_id to check status."
}
```

If `wait_for_completion` is true and the workflow completes:

```json
{
  "success": true,
  "prompt_id": "abc123-def456-ghi789",
  "status": "completed",
  "outputs": [
    {
      "type": "image",
      "filename": "ComfyUI_00001_.png",
      "subfolder": "",
      "type_name": "output"
    }
  ],
  "history": { ... }
}
```

### Checking Status

```bash
curl -X GET https://your-api-domain.com/api/comfyui/workflow/status/abc123-def456-ghi789 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Getting Output Image

```bash
curl -X GET "https://your-api-domain.com/api/comfyui/image?filename=ComfyUI_00001_.png" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  --output result.png
```

### Cancelling Workflow

```bash
curl -X POST https://your-api-domain.com/api/comfyui/workflow/cancel/abc123-def456-ghi789 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Customizable Parameters

The workflow processor automatically injects the following parameters into appropriate nodes:

- **prompt**: Injected into nodes with `text`, `positive`, or `prompt` inputs
- **negative_prompt**: Injected into nodes with `negative` or `negative_prompt` inputs
- **seed**: Injected into nodes with `seed` input
- **steps**: Injected into nodes with `steps` input
- **cfg**: Injected into nodes with `cfg` input
- **denoise**: Injected into nodes with `denoise` input
- **width**: Injected into nodes with `width` input
- **height**: Injected into nodes with `height` input
- **image**: Injected into nodes with `image` or `input_image` inputs (after upload)

## Requirements

- A ComfyUI instance must be configured in the system (type: `comfyui`, enabled: true)
- Valid authentication token (JWT)
- The model referenced in the workflow (e.g., `sd_xl_base_1.0.safetensors`) must be available in ComfyUI

## Creating Your Own Workflows

1. Create a workflow in ComfyUI web interface
2. Export the workflow as API format (not the regular workflow format)
3. Save the JSON file
4. Submit it using the endpoint with your custom inputs

## Notes

- The workflow processor preserves the structure of your workflow while intelligently injecting parameters
- If a parameter isn't found in the workflow, it's simply ignored (no error)
- You can still use the workflow with its default values by not providing any inputs
- Image uploads are automatically handled - just provide the image file in the request
