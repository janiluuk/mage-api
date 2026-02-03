<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Service for generating film scripts using LocalAI/Ollama
 * Supports manual entry or AI generation with character consistency
 */
class ScriptGenerationService
{
    private string $baseUrl;
    private string $defaultModel;

    public function __construct()
    {
        $this->baseUrl = config('services.local_ai.base_url', 'http://localhost:11434');
        $this->defaultModel = config('services.local_ai.default_model', 'qwen3-18b');
    }

    /**
     * Get available models from LocalAI/Ollama instance
     */
    public function getAvailableModels(): array
    {
        try {
            // Check cache first (5 minute cache)
            return Cache::remember('ai_available_models', 300, function () {
                $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
                
                if (!$response->successful()) {
                    Log::warning('Failed to fetch models from LocalAI', [
                        'url' => $this->baseUrl,
                        'status' => $response->status(),
                    ]);
                    return [];
                }

                $data = $response->json();
                $models = [];

                if (isset($data['models']) && is_array($data['models'])) {
                    foreach ($data['models'] as $model) {
                        $models[] = [
                            'id' => $model['name'] ?? $model['model'] ?? null,
                            'name' => $model['name'] ?? $model['model'] ?? 'Unknown',
                            'size' => $model['size'] ?? null,
                            'modified_at' => $model['modified_at'] ?? null,
                        ];
                    }
                }

                return $models;
            });
        } catch (\Exception $e) {
            Log::error('Error fetching available models', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);
            return [];
        }
    }

    /**
     * Generate script based on mode
     * 
     * @param string $mode 'manual' or 'generate'
     * @param string $prompt User's story text or generation prompt
     * @param string $length 'short', 'medium', 'long', or duration like '5min'
     * @param array $characters Character definitions for consistency
     * @param string $model AI model to use
     * @param array $options Additional generation options
     */
    public function generateScript(
        string $mode,
        string $prompt,
        string $length = 'medium',
        array $characters = [],
        string $model = null,
        array $options = []
    ): string {
        $model = $model ?? $this->defaultModel;

        if ($mode === 'manual') {
            // User provided text - just format it and return
            return $this->formatManualScript($prompt, $length, $characters);
        }

        // AI generation mode
        return $this->generateWithAI($prompt, $length, $characters, $model, $options);
    }

    /**
     * Format manual script entry with character consistency
     */
    private function formatManualScript(string $text, string $length, array $characters): string
    {
        $formatted = $text;

        // Add character definitions at the top if provided
        if (!empty($characters)) {
            $characterSection = "\n\n=== CHARACTERS ===\n";
            foreach ($characters as $character) {
                $characterSection .= sprintf(
                    "%s: %s\n",
                    $character['name'] ?? 'Unknown',
                    $character['description'] ?? 'No description'
                );
                if (isset($character['traits']) && is_array($character['traits'])) {
                    $characterSection .= "  Traits: " . implode(', ', $character['traits']) . "\n";
                }
            }
            $formatted = $characterSection . "\n\n=== SCRIPT ===\n" . $formatted;
        }

        return $formatted;
    }

    /**
     * Generate script using AI
     */
    private function generateWithAI(
        string $prompt,
        string $length,
        array $characters,
        string $model,
        array $options
    ): string {
        try {
            // Build system prompt with character consistency
            $systemPrompt = $this->buildSystemPrompt($length, $characters);
            
            // Build user prompt
            $userPrompt = $this->buildUserPrompt($prompt, $length);

            // Prepare request payload (Ollama/LocalAI compatible)
            $payload = [
                'model' => $model,
                'prompt' => $userPrompt,
                'system' => $systemPrompt,
                'stream' => false,
                'options' => array_merge([
                    'temperature' => $options['temperature'] ?? 0.7,
                    'top_p' => $options['top_p'] ?? 0.9,
                    'top_k' => $options['top_k'] ?? 40,
                ], $options),
            ];

            Log::info('Generating script with AI', [
                'model' => $model,
                'length' => $length,
                'characters_count' => count($characters),
            ]);

            $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", $payload);

            if (!$response->successful()) {
                throw new \Exception("AI service returned error: " . $response->body());
            }

            $data = $response->json();
            $script = $data['response'] ?? '';

            if (empty($script)) {
                throw new \Exception("Empty response from AI service");
            }

            // Format the script with character definitions
            return $this->formatGeneratedScript($script, $characters);

        } catch (\Exception $e) {
            Log::error('Error generating script with AI', [
                'error' => $e->getMessage(),
                'model' => $model,
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    /**
     * Build system prompt for script generation
     */
    private function buildSystemPrompt(string $length, array $characters): string
    {
        $lengthGuidance = $this->getLengthGuidance($length);
        
        $prompt = "You are a professional film scriptwriter. Generate a complete film script based on the user's story idea.\n\n";
        $prompt .= "Script Requirements:\n";
        $prompt .= "- Length: {$lengthGuidance}\n";
        $prompt .= "- Format: Professional screenplay format with scene headings, action lines, and dialogue\n";
        $prompt .= "- Structure: Include clear scene breaks and transitions\n";
        
        if (!empty($characters)) {
            $prompt .= "\nCharacters to include:\n";
            foreach ($characters as $character) {
                $prompt .= sprintf(
                    "- %s: %s\n",
                    $character['name'] ?? 'Unknown',
                    $character['description'] ?? 'No description'
                );
                if (isset($character['traits']) && is_array($character['traits'])) {
                    $prompt .= "  Key traits: " . implode(', ', $character['traits']) . "\n";
                }
            }
            $prompt .= "\nMaintain character consistency throughout the script.\n";
        }

        return $prompt;
    }

    /**
     * Build user prompt
     */
    private function buildUserPrompt(string $prompt, string $length): string
    {
        return "Write a film script based on this story: {$prompt}\n\n";
    }

    /**
     * Get length guidance text
     */
    private function getLengthGuidance(string $length): string
    {
        return match($length) {
            'short' => 'Short film (5-15 minutes, approximately 5-10 pages)',
            'long' => 'Feature film (90-120 minutes, approximately 90-120 pages)',
            default => 'Medium length film (20-60 minutes, approximately 20-60 pages)',
        };
    }

    /**
     * Format generated script with character definitions
     */
    private function formatGeneratedScript(string $script, array $characters): string
    {
        if (empty($characters)) {
            return $script;
        }

        // Add character definitions at the top
        $characterSection = "=== CHARACTERS ===\n";
        foreach ($characters as $character) {
            $characterSection .= sprintf(
                "%s: %s\n",
                $character['name'] ?? 'Unknown',
                $character['description'] ?? 'No description'
            );
            if (isset($character['traits']) && is_array($character['traits'])) {
                $characterSection .= "  Traits: " . implode(', ', $character['traits']) . "\n";
            }
        }
        $characterSection .= "\n";

        return $characterSection . "=== SCRIPT ===\n\n" . $script;
    }
}

