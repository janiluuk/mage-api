<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class LocalAIService
{
    protected Client $httpClient;
    protected string $baseUrl;
    protected ?string $defaultModel = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.local_ai.base_url', 'http://localhost:1234'), '/');
        $this->defaultModel = config('services.local_ai.default_model', 'qwen-8b');
        
        $this->httpClient = new Client([
            'timeout' => 300,
            'connect_timeout' => 10,
            'base_uri' => $this->baseUrl,
        ]);
    }

    /**
     * Get all available models from the local AI server
     *
     * @return array List of available models
     * @throws GuzzleException
     */
    public function getAvailableModels(): array
    {
        try {
            $response = $this->httpClient->get('/v1/models');
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                return array_map(function ($model) {
                    return [
                        'id' => $model['id'] ?? null,
                        'name' => $model['name'] ?? $model['id'] ?? null,
                        'object' => $model['object'] ?? 'model',
                        'created' => $model['created'] ?? null,
                        'owned_by' => $model['owned_by'] ?? 'local',
                    ];
                }, $data['data']);
            }
            
            return [];
        } catch (GuzzleException $e) {
            Log::error('Failed to fetch available models from local AI', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl . '/v1/models',
            ]);
            throw $e;
        }
    }

    /**
     * Generate text using the local AI service
     *
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options (model, temperature, max_tokens, etc.)
     * @return string Generated text
     * @throws GuzzleException
     */
    public function generateText(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2000;
        $systemPrompt = $options['system_prompt'] ?? null;

        $messages = [];
        
        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        Log::info('Local AI: Generating text', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'url' => $this->baseUrl . '/v1/chat/completions',
        ]);

        try {
            $response = $this->httpClient->post('/v1/chat/completions', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['choices'][0]['message']['content'])) {
                $generatedText = $data['choices'][0]['message']['content'];
                
                Log::info('Local AI: Text generated successfully', [
                    'model' => $model,
                    'response_length' => strlen($generatedText),
                ]);

                return $generatedText;
            }

            throw new \RuntimeException('Invalid response format from local AI service');
        } catch (GuzzleException $e) {
            Log::error('Local AI: Failed to generate text', [
                'error' => $e->getMessage(),
                'model' => $model,
                'url' => $this->baseUrl . '/v1/chat/completions',
            ]);
            throw $e;
        }
    }

    /**
     * Generate a script for a film production
     *
     * @param string $prompt The prompt describing the film
     * @param array $options Additional options
     * @return string Generated script
     * @throws GuzzleException
     */
    public function generateScript(string $prompt, array $options = []): string
    {
        $systemPrompt = "You are a professional screenwriter. Generate a well-structured screenplay based on the user's prompt. " .
            "Format the script in standard screenplay format with scene headings, action lines, character names, and dialogue. " .
            "Make it engaging, cinematic, and ready for production.";

        $fullPrompt = "Create a screenplay based on the following: {$prompt}\n\n" .
            "Include:\n" .
            "- Title\n" .
            "- Scene headings (INT./EXT. LOCATION - TIME)\n" .
            "- Action descriptions\n" .
            "- Character dialogue\n" .
            "- Proper formatting";

        return $this->generateText($fullPrompt, array_merge($options, [
            'system_prompt' => $systemPrompt,
            'max_tokens' => $options['max_tokens'] ?? 4000,
        ]));
    }

    /**
     * Generate scene description for a shot
     *
     * @param string $prompt The prompt describing the scene
     * @param array $options Additional options
     * @return array Scene data including description and generation parameters
     * @throws GuzzleException
     */
    public function generateSceneDescription(string $prompt, array $options = []): array
    {
        $systemPrompt = "You are a professional film director and cinematographer. Generate detailed scene descriptions " .
            "that can be used for AI video generation. Provide vivid, visual descriptions that include camera angles, " .
            "lighting, composition, and visual style.";

        $fullPrompt = "Create a detailed scene description for video generation based on: {$prompt}\n\n" .
            "Include:\n" .
            "- Visual description of the scene\n" .
            "- Camera angles and movements\n" .
            "- Lighting and mood\n" .
            "- Color palette\n" .
            "- Visual style and aesthetic";

        $description = $this->generateText($fullPrompt, array_merge($options, [
            'system_prompt' => $systemPrompt,
            'max_tokens' => $options['max_tokens'] ?? 1500,
        ]));

        return [
            'description' => $description,
            'prompt' => $prompt,
            'model' => $options['model'] ?? $this->defaultModel,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Check if the AI service is available
     *
     * @return bool True if service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->get('/v1/models', [
                'timeout' => 5,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}

