<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Sequence;

/**
 * Service for parsing scenes into shot descriptions using LLM
 */
class SceneParsingService
{
    private string $baseUrl;
    private string $defaultModel;

    public function __construct()
    {
        $this->baseUrl = config('services.local_ai.base_url', 'http://localhost:11434');
        $this->defaultModel = config('services.local_ai.default_model', 'qwen3-18b');
    }

    /**
     * Parse a scene into shot descriptions
     * 
     * @param Sequence $sequence The sequence (scene) to parse
     * @param string|null $model Optional model override
     * @param array $options Additional parsing options
     * @return array Array of shot descriptions with camera angles, movements, etc.
     */
    public function parseSceneIntoShots(
        Sequence $sequence,
        ?string $model = null,
        array $options = []
    ): array {
        $sceneText = $sequence->script ?? $sequence->description ?? '';
        
        if (empty($sceneText)) {
            throw new \Exception('Sequence script/description is empty. Please provide scene content first.');
        }

        $model = $model ?? $this->defaultModel;

        try {
            Log::info('Parsing scene into shots', [
                'sequence_id' => $sequence->id,
                'model' => $model,
            ]);

            // Build prompt for shot parsing
            $systemPrompt = $this->buildShotParsingSystemPrompt();
            $userPrompt = $this->buildShotParsingPrompt($sceneText, $sequence);

            // Call LLM
            $payload = [
                'model' => $model,
                'prompt' => $userPrompt,
                'system' => $systemPrompt,
                'stream' => false,
                'options' => array_merge([
                    'temperature' => $options['temperature'] ?? 0.3, // Lower temperature for more structured output
                    'top_p' => $options['top_p'] ?? 0.9,
                ], $options),
            ];

            $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", $payload);

            if (!$response->successful()) {
                throw new \Exception("AI service returned error: " . $response->body());
            }

            $data = $response->json();
            $parsedOutput = $data['response'] ?? '';

            if (empty($parsedOutput)) {
                throw new \Exception("Empty response from AI service");
            }

            // Parse JSON response into shot array
            $shots = $this->parseLLMResponse($parsedOutput);

            Log::info('Successfully parsed scene into shots', [
                'sequence_id' => $sequence->id,
                'shots_count' => count($shots),
            ]);

            return $shots;

        } catch (\Exception $e) {
            Log::error('Error parsing scene into shots', [
                'sequence_id' => $sequence->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build system prompt for shot parsing
     */
    private function buildShotParsingSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional cinematographer and film director. Your task is to analyze a film scene and break it down into individual shots.

A shot is defined as:
- A continuous camera take without cuts
- Each shot should have a specific purpose (establishing, dialogue, action, reaction, etc.)
- Shots should be numbered sequentially within the scene
- Consider camera angles, movements, and framing

For each shot, provide:
1. Shot number (sequential within the scene)
2. Shot name/title (brief, descriptive)
3. Description (what happens in this shot, what the camera captures)
4. Camera angle (Close-up, Medium, Wide, Extreme Wide, Over-the-shoulder, etc.)
5. Camera movement (Static, Pan, Tilt, Dolly, Crane, Handheld, etc.)
6. Framing (Single, Two-shot, Group, etc.)
7. Duration estimate (in seconds)
8. Key elements (what's important in this shot - characters, objects, actions)

Output your response as a valid JSON array with the following structure:
[
  {
    "shot_number": 1,
    "name": "Shot Name",
    "description": "Detailed description of what happens in this shot",
    "camera_angle": "Medium",
    "camera_movement": "Static",
    "framing": "Single",
    "duration_estimate": 5,
    "key_elements": ["character dialogue", "important prop", "emotional moment"]
  },
  ...
]

Be precise and ensure the JSON is valid. Break down the scene into logical shots that can be filmed individually.
PROMPT;
    }

    /**
     * Build user prompt for shot parsing
     */
    private function buildShotParsingPrompt(string $sceneText, Sequence $sequence): string
    {
        $prompt = "Analyze the following film scene and break it down into individual shots.\n\n";
        
        $prompt .= "SCENE INFORMATION:\n";
        $prompt .= sprintf("- Name: %s\n", $sequence->name ?? 'Untitled Scene');
        $prompt .= sprintf("- Location: %s\n", $sequence->metadata['location'] ?? 'Unknown');
        $prompt .= sprintf("- Time of Day: %s\n", $sequence->metadata['time_of_day'] ?? 'Day');
        
        if (!empty($sequence->description)) {
            $prompt .= sprintf("- Description: %s\n", $sequence->description);
        }
        
        $prompt .= "\n";
        $prompt .= "SCENE SCRIPT:\n";
        $prompt .= "---\n";
        $prompt .= $sceneText;
        $prompt .= "\n---\n\n";
        $prompt .= "Please parse this scene into shots and return a JSON array as specified.";

        return $prompt;
    }

    /**
     * Parse LLM response into structured shot data
     */
    private function parseLLMResponse(string $response): array
    {
        // Try to extract JSON from the response
        $jsonMatch = [];
        if (preg_match('/```json\s*(\[.*?\])\s*```/s', $response, $jsonMatch)) {
            $response = $jsonMatch[1];
        } elseif (preg_match('/```\s*(\[.*?\])\s*```/s', $response, $jsonMatch)) {
            $response = $jsonMatch[1];
        } elseif (preg_match('/\[.*\]/s', $response, $jsonMatch)) {
            $response = $jsonMatch[0];
        }

        $shots = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse LLM response as JSON', [
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200),
            ]);
            throw new \Exception('Failed to parse LLM response: Invalid JSON format');
        }

        if (!is_array($shots)) {
            throw new \Exception('LLM response is not an array');
        }

        // Validate and normalize shot structure
        $normalizedShots = [];
        foreach ($shots as $index => $shot) {
            $normalizedShots[] = [
                'shot_number' => $shot['shot_number'] ?? ($index + 1),
                'name' => $shot['name'] ?? "Shot " . ($index + 1),
                'description' => $shot['description'] ?? '',
                'camera_angle' => $shot['camera_angle'] ?? 'Medium',
                'camera_movement' => $shot['camera_movement'] ?? 'Static',
                'framing' => $shot['framing'] ?? 'Single',
                'duration_estimate' => $shot['duration_estimate'] ?? 5,
                'key_elements' => $shot['key_elements'] ?? [],
            ];
        }

        return $normalizedShots;
    }
}

