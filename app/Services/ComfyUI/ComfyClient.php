<?php

namespace App\Services\ComfyUI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ComfyClient
{
    private Client $httpClient;
    private string $host;
    private int $queueTimeout;
    private int $fetchTimeout;

    public function __construct(?string $host = null)
    {
        $this->host = $host ?? config('services.comfy.host', '127.0.0.1:8188');
        $this->queueTimeout = (int) config('services.comfy.queue_timeout', 10000);
        $this->fetchTimeout = (int) config('services.comfy.fetch_timeout', 30000);
        
        $this->httpClient = new Client([
            'timeout' => max($this->queueTimeout, $this->fetchTimeout) / 1000,
            'http_errors' => true,
        ]);
    }

    /**
     * Queue a prompt to ComfyUI for processing.
     *
     * @param array $prompt ComfyUI workflow prompt object
     * @param string $clientId Unique client ID for tracking
     * @return void
     * @throws \Exception
     */
    public function queuePrompt(array $prompt, string $clientId): void
    {
        try {
            $response = $this->httpClient->post("http://{$this->host}/prompt", [
                'json' => [
                    'prompt' => $prompt,
                    'client_id' => $clientId,
                ],
                'timeout' => $this->queueTimeout / 1000,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to queue prompt: HTTP {$response->getStatusCode()}");
            }
        } catch (GuzzleException $e) {
            Log::error('ComfyUI queue prompt failed', [
                'host' => $this->host,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to queue prompt to ComfyUI at {$this->host}: {$e->getMessage()}");
        }
    }

    /**
     * Fetch generated audio file from ComfyUI.
     *
     * @param array $fileInfo File information from ComfyUI result
     * @return string Audio file content as binary string
     * @throws \Exception
     */
    public function fetchAudio(array $fileInfo): string
    {
        if (empty($fileInfo['filename'])) {
            throw new \Exception('Invalid file info: missing filename');
        }

        $filename = $fileInfo['filename'];
        $subfolder = $fileInfo['subfolder'] ?? '';
        $type = $fileInfo['type'] ?? 'output';

        $url = "http://{$this->host}/view?" . http_build_query([
            'filename' => $filename,
            'subfolder' => $subfolder,
            'type' => $type,
        ]);

        try {
            $response = $this->httpClient->get($url, [
                'timeout' => $this->fetchTimeout / 1000,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to fetch audio: HTTP {$response->getStatusCode()}");
            }

            return (string) $response->getBody();
        } catch (GuzzleException $e) {
            Log::error('ComfyUI fetch audio failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch audio from ComfyUI: {$e->getMessage()}");
        }
    }
}

