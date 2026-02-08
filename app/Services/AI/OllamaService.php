<?php

namespace App\Services\AI;

use App\Models\GeneratorInstance;
use App\Services\LoadBalancerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected LoadBalancerService $loadBalancer;

    /**
     * Cache TTL for model lists (in seconds).
     */
    protected int $cacheTtl = 300;

    public function __construct(LoadBalancerService $loadBalancer)
    {
        $this->loadBalancer = $loadBalancer;
    }

    /**
     * Get available models from an Ollama instance.
     *
     * @param GeneratorInstance|null $instance Specific instance to query, or null to auto-select.
     * @return array
     */
    public function getAvailableModels(?GeneratorInstance $instance = null): array
    {
        $instance = $instance ?? $this->getOllamaInstance();

        if (!$instance) {
            return [];
        }

        $cacheKey = "ollama_models_{$instance->id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($instance) {
            try {
                $baseUrl = $this->normalizeUrl($instance->url);
                $response = Http::timeout(10)->get("{$baseUrl}/api/tags");

                if ($response->successful()) {
                    return $response->json('models', []);
                }

                Log::warning('OllamaService: Failed to fetch models', [
                    'instance_id' => $instance->id,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('OllamaService: Error fetching models', [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Generate text using an Ollama model.
     *
     * @param string $prompt The prompt to send.
     * @param string $model The model name.
     * @param array $options Generation options (temperature, top_p, top_k, etc.).
     * @param GeneratorInstance|null $instance Specific instance, or null to auto-select.
     * @return string The generated text.
     *
     * @throws \Exception If no instance is available or the request fails.
     */
    public function generate(string $prompt, string $model, array $options = [], ?GeneratorInstance $instance = null): string
    {
        $instance = $instance ?? $this->getOllamaInstance();

        if (!$instance) {
            throw new \Exception('No available Ollama instances');
        }

        $baseUrl = $this->normalizeUrl($instance->url);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if (!empty($options)) {
            $payload['options'] = $options;
        }

        return $this->sendGenerateRequest($baseUrl, $payload);
    }

    /**
     * Generate text with a system prompt.
     *
     * @param string $system The system prompt.
     * @param string $prompt The user prompt.
     * @param string $model The model name.
     * @param array $options Generation options.
     * @param GeneratorInstance|null $instance Specific instance, or null to auto-select.
     * @return string The generated text.
     *
     * @throws \Exception If no instance is available or the request fails.
     */
    public function generateWithSystem(string $system, string $prompt, string $model, array $options = [], ?GeneratorInstance $instance = null): string
    {
        $instance = $instance ?? $this->getOllamaInstance();

        if (!$instance) {
            throw new \Exception('No available Ollama instances');
        }

        $baseUrl = $this->normalizeUrl($instance->url);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'system' => $system,
            'stream' => false,
        ];

        if (!empty($options)) {
            $payload['options'] = $options;
        }

        return $this->sendGenerateRequest($baseUrl, $payload);
    }

    /**
     * Check the health of an Ollama instance.
     *
     * @param GeneratorInstance $instance
     * @return bool
     */
    public function checkHealth(GeneratorInstance $instance): bool
    {
        try {
            $baseUrl = $this->normalizeUrl($instance->url);
            $response = Http::timeout(5)->get("{$baseUrl}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('OllamaService: Health check failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Pull (download) a model to an Ollama instance.
     *
     * @param string $model The model name to pull.
     * @param GeneratorInstance $instance The target instance.
     * @return bool True if the pull was successful.
     */
    public function pullModel(string $model, GeneratorInstance $instance): bool
    {
        try {
            $baseUrl = $this->normalizeUrl($instance->url);
            $response = Http::timeout(600)->post("{$baseUrl}/api/pull", [
                'name' => $model,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('OllamaService: Failed to pull model', [
                'model' => $model,
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a generate request and handle the response.
     *
     * @param string $baseUrl
     * @param array $payload
     * @return string
     *
     * @throws \Exception
     */
    protected function sendGenerateRequest(string $baseUrl, array $payload): string
    {
        $response = Http::timeout(120)->post("{$baseUrl}/api/generate", $payload);

        if (!$response->successful()) {
            throw new \Exception('Ollama service returned error: ' . ($response->json('error') ?? 'HTTP ' . $response->status()));
        }

        $text = $response->json('response', '');

        if (empty($text)) {
            throw new \Exception('Empty response from Ollama service');
        }

        return $text;
    }

    /**
     * Get an available Ollama instance via the load balancer.
     *
     * @return GeneratorInstance|null
     */
    protected function getOllamaInstance(): ?GeneratorInstance
    {
        return $this->loadBalancer->selectInstance('ollama');
    }

    /**
     * Normalize a URL to ensure it has a protocol and no trailing slash.
     *
     * @param string $url
     * @return string
     */
    protected function normalizeUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (!preg_match('#^https?://#', $url)) {
            $url = 'http://' . $url;
        }

        return $url;
    }
}

