<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\FilmProject;
use App\Models\Sequence;

/**
 * Service for parsing scripts into scenes using LLM
 */
class ScriptParsingService
{
    private ScriptGenerationService $scriptService;
    private string $baseUrl;
    private string $defaultModel;

    public function __construct(ScriptGenerationService $scriptService)
    {
        $this->scriptService = $scriptService;
        $this->baseUrl = config('services.local_ai.base_url', 'http://localhost:11434');
        $this->defaultModel = config('services.local_ai.default_model', 'qwen3-18b');
    }

    /**
     * Parse a script into scenes
     * 
     * @param FilmProject $project The film project with script
     * @param string|null $model Optional model override
     * @param array $options Additional parsing options
     * @return array Array of scene data with name, description, script excerpt, order
     */
    public function parseScriptIntoScenes(
        FilmProject $project,
        ?string $model = null,
        array $options = []
    ): array {
        if (empty($project->script)) {
            throw new \Exception('Project script is empty. Please generate or provide a script first.');
        }

        $model = $model ?? $this->defaultModel;

        try {
            Log::info('Parsing script into scenes', [
                'project_id' => $project->id,
                'model' => $model,
                'script_length' => strlen($project->script),
            ]);

            // Build prompt for scene parsing
            $systemPrompt = $this->buildSceneParsingSystemPrompt();
            $userPrompt = $this->buildSceneParsingPrompt($project->script, $project->metadata['characters'] ?? []);

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

            // Parse JSON response into scene array
            $scenes = $this->parseLLMResponse($parsedOutput);

            Log::info('Successfully parsed script into scenes', [
                'project_id' => $project->id,
                'scenes_count' => count($scenes),
            ]);

            return $scenes;

        } catch (\Exception $e) {
            Log::error('Error parsing script into scenes', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build system prompt for scene parsing
     */
    private function buildSceneParsingSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional film script analyst. Your task is to analyze a film script and break it down into distinct scenes.

A scene is defined as:
- A continuous action in a single location
- A change in location or time typically indicates a new scene
- Each scene should have a clear beginning and end
- Scenes should be numbered sequentially

For each scene, provide:
1. Scene number (sequential)
2. Scene name/title (brief, descriptive)
3. Location (where the scene takes place)
4. Time of day (Day, Night, Dawn, Dusk, etc.)
5. Description (brief summary of what happens in the scene)
6. Script excerpt (the actual script text for this scene)
7. Estimated duration (in seconds or minutes)

Output your response as a valid JSON array with the following structure:
[
  {
    "scene_number": 1,
    "name": "Scene Name",
    "location": "Location description",
    "time_of_day": "Day",
    "description": "What happens in this scene",
    "script_excerpt": "The actual script text...",
    "estimated_duration": "30 seconds"
  },
  ...
]

Be precise and ensure the JSON is valid. Include all scenes from the script.
PROMPT;
    }

    /**
     * Build user prompt for scene parsing
     */
    private function buildSceneParsingPrompt(string $script, array $characters = []): string
    {
        $prompt = "Analyze the following film script and break it down into distinct scenes.\n\n";
        
        if (!empty($characters)) {
            $prompt .= "Characters in this script:\n";
            foreach ($characters as $character) {
                $prompt .= sprintf("- %s: %s\n", $character['name'] ?? 'Unknown', $character['description'] ?? '');
            }
            $prompt .= "\n";
        }

        $prompt .= "SCRIPT:\n";
        $prompt .= "---\n";
        $prompt .= $script;
        $prompt .= "\n---\n\n";
        $prompt .= "Please parse this script into scenes and return a JSON array as specified.";

        return $prompt;
    }

    /**
     * Parse LLM response into structured scene data
     */
    private function parseLLMResponse(string $response): array
    {
        // Try to extract JSON from the response
        // LLM might wrap JSON in markdown code blocks or add explanatory text
        $jsonMatch = [];
        if (preg_match('/```json\s*(\[.*?\])\s*```/s', $response, $jsonMatch)) {
            $response = $jsonMatch[1];
        } elseif (preg_match('/```\s*(\[.*?\])\s*```/s', $response, $jsonMatch)) {
            $response = $jsonMatch[1];
        } elseif (preg_match('/\[.*\]/s', $response, $jsonMatch)) {
            $response = $jsonMatch[0];
        }

        $scenes = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse LLM response as JSON', [
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200),
            ]);
            throw new \Exception('Failed to parse LLM response: Invalid JSON format');
        }

        if (!is_array($scenes)) {
            throw new \Exception('LLM response is not an array');
        }

        // Validate and normalize scene structure
        $normalizedScenes = [];
        foreach ($scenes as $index => $scene) {
            $normalizedScenes[] = [
                'scene_number' => $scene['scene_number'] ?? ($index + 1),
                'name' => $scene['name'] ?? "Scene " . ($index + 1),
                'location' => $scene['location'] ?? 'Unknown',
                'time_of_day' => $scene['time_of_day'] ?? 'Day',
                'description' => $scene['description'] ?? '',
                'script_excerpt' => $scene['script_excerpt'] ?? '',
                'estimated_duration' => $scene['estimated_duration'] ?? '30 seconds',
            ];
        }

        return $normalizedScenes;
    }
}

