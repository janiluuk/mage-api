<?php

namespace App\Services\ComfyUI;

use App\Exceptions\SdInstanceUnavailableException;
use App\Services\SdInstanceService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ComfyUIClient
{
    protected Client $httpClient;
    protected SdInstanceService $sdInstanceService;
    protected ?string $baseUrl = null;

    public function __construct(SdInstanceService $sdInstanceService)
    {
        $this->httpClient = new Client([
            'timeout' => 300,
            'connect_timeout' => 10,
        ]);
        $this->sdInstanceService = $sdInstanceService;
    }

    /**
     * Get the base URL for ComfyUI instance
     *
     * @return string
     * @throws SdInstanceUnavailableException
     */
    protected function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $url = $this->sdInstanceService->getEnabledInstanceUrl('comfyui');
            if (!$url) {
                throw SdInstanceUnavailableException::forType('comfyui');
            }
            $this->baseUrl = $url;
        }

        return $this->baseUrl;
    }

    /**
     * Queue a prompt workflow for processing
     *
     * @param array $workflow The workflow data
     * @return array Response from ComfyUI
     * @throws GuzzleException
     */
    public function queuePrompt(array $workflow): array
    {
        $url = $this->getBaseUrl() . '/prompt';

        Log::info('ComfyUI: Queueing prompt', [
            'url' => $url,
            'workflow_keys' => array_keys($workflow),
        ]);

        $response = $this->httpClient->post($url, [
            'json' => ['prompt' => $workflow],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        Log::info('ComfyUI: Prompt queued successfully', [
            'response' => $data,
        ]);

        return $data;
    }

    /**
     * Get the status and history of a prompt
     *
     * @param string $promptId The prompt ID
     * @return array Response from ComfyUI
     * @throws GuzzleException
     */
    public function getHistory(string $promptId): array
    {
        $url = $this->getBaseUrl() . '/history/' . $promptId;

        Log::debug('ComfyUI: Getting history', [
            'prompt_id' => $promptId,
            'url' => $url,
        ]);

        $response = $this->httpClient->get($url);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the current queue status
     *
     * @return array Response from ComfyUI
     * @throws GuzzleException
     */
    public function getQueue(): array
    {
        $url = $this->getBaseUrl() . '/queue';

        Log::debug('ComfyUI: Getting queue status', [
            'url' => $url,
        ]);

        $response = $this->httpClient->get($url);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Cancel a prompt by ID
     *
     * @param string $promptId The prompt ID to cancel
     * @return array Response from ComfyUI
     * @throws GuzzleException
     */
    public function cancelPrompt(string $promptId): array
    {
        $url = $this->getBaseUrl() . '/interrupt';

        Log::info('ComfyUI: Cancelling prompt', [
            'prompt_id' => $promptId,
            'url' => $url,
        ]);

        $response = $this->httpClient->post($url, [
            'json' => ['delete' => [$promptId]],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Upload an image to ComfyUI
     *
     * @param string $filePath Path to the image file
     * @param string $subfolder Optional subfolder to organize uploads
     * @return array Response from ComfyUI with image info
     * @throws GuzzleException
     */
    public function uploadImage(string $filePath, string $subfolder = ''): array
    {
        $url = $this->getBaseUrl() . '/upload/image';

        Log::info('ComfyUI: Uploading image', [
            'file_path' => $filePath,
            'subfolder' => $subfolder,
            'url' => $url,
        ]);

        $response = $this->httpClient->post($url, [
            'multipart' => [
                [
                    'name' => 'image',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ],
                [
                    'name' => 'subfolder',
                    'contents' => $subfolder,
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        Log::info('ComfyUI: Image uploaded successfully', [
            'response' => $data,
        ]);

        return $data;
    }

    /**
     * Get an output image from ComfyUI
     *
     * @param string $filename The filename of the output
     * @param string $subfolder Optional subfolder
     * @param string $type Type of output (output, input, temp)
     * @return string Binary image data
     * @throws GuzzleException
     */
    public function getImage(string $filename, string $subfolder = '', string $type = 'output'): string
    {
        $url = $this->getBaseUrl() . '/view';

        $params = [
            'filename' => $filename,
            'type' => $type,
        ];

        if ($subfolder) {
            $params['subfolder'] = $subfolder;
        }

        Log::debug('ComfyUI: Getting image', [
            'filename' => $filename,
            'subfolder' => $subfolder,
            'type' => $type,
        ]);

        $response = $this->httpClient->get($url, [
            'query' => $params,
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Check if a prompt has completed
     *
     * @param string $promptId The prompt ID
     * @return bool True if completed
     * @throws GuzzleException
     */
    public function isPromptComplete(string $promptId): bool
    {
        $history = $this->getHistory($promptId);

        return isset($history[$promptId]);
    }

    /**
     * Wait for a prompt to complete and get the results
     *
     * @param string $promptId The prompt ID
     * @param int $maxWaitSeconds Maximum time to wait in seconds
     * @param int $pollInterval Seconds between checks
     * @return array|null History data if completed, null if timeout
     * @throws GuzzleException
     */
    public function waitForPrompt(string $promptId, int $maxWaitSeconds = 300, int $pollInterval = 2): ?array
    {
        $startTime = time();

        while ((time() - $startTime) < $maxWaitSeconds) {
            $history = $this->getHistory($promptId);

            if (isset($history[$promptId])) {
                Log::info('ComfyUI: Prompt completed', [
                    'prompt_id' => $promptId,
                    'duration' => time() - $startTime,
                ]);

                return $history[$promptId];
            }

            sleep($pollInterval);
        }

        Log::warning('ComfyUI: Prompt timeout', [
            'prompt_id' => $promptId,
            'max_wait_seconds' => $maxWaitSeconds,
        ]);

        return null;
    }
}
