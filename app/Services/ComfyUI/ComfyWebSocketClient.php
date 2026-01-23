<?php

namespace App\Services\ComfyUI;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ComfyUI WebSocket client using HTTP polling as a fallback.
 * 
 * Note: PHP doesn't have native WebSocket support, so we use HTTP polling
 * to check for completion. For production, consider using ReactPHP/Ratchet
 * or a dedicated queue system.
 */
class ComfyWebSocketClient
{
    private string $host;
    private int $timeout;
    private int $pollInterval;

    public function __construct(?string $host = null)
    {
        $this->host = $host ?? config('services.comfy.host', '127.0.0.1:8188');
        $this->timeout = (int) config('services.comfy.ws_timeout', 60000);
        $this->pollInterval = (int) config('services.comfy.poll_interval', 500); // 500ms
    }

    /**
     * Wait for ComfyUI result by polling the history endpoint.
     *
     * @param string $clientId Unique client ID for tracking
     * @return array File information for the generated audio
     * @throws Exception
     */
    public function waitForResult(string $clientId): array
    {
        $startTime = microtime(true);
        $timeoutSeconds = $this->timeout / 1000;

        $httpClient = new \GuzzleHttp\Client([
            'timeout' => 5,
        ]);

        while (true) {
            // Check timeout
            if ((microtime(true) - $startTime) > $timeoutSeconds) {
                throw new Exception("Timeout waiting for ComfyUI result ({$this->timeout}ms)");
            }

            try {
                // Check history endpoint - ComfyUI stores completed jobs here
                $historyResponse = $httpClient->get("http://{$this->host}/history");
                
                if ($historyResponse->getStatusCode() === 200) {
                    $historyData = json_decode($historyResponse->getBody()->getContents(), true);
                    
                    // History structure: { "prompt_id": { "prompt": [...], "outputs": {...}, "status": {...} } }
                    // We need to find our client_id in the outputs
                    if (is_array($historyData)) {
                        foreach ($historyData as $promptId => $jobData) {
                            // Check if this job has outputs with our expected structure
                            if (isset($jobData['outputs']) && is_array($jobData['outputs'])) {
                                // Look through outputs for audio files
                                foreach ($jobData['outputs'] as $nodeId => $output) {
                                    if (isset($output['audio']) && is_array($output['audio']) && !empty($output['audio'])) {
                                        // Found audio output, return the file info
                                        return $output['audio'][0];
                                    }
                                    // Alternative structure: direct file reference
                                    if (isset($output['filename'])) {
                                        return [
                                            'filename' => $output['filename'],
                                            'subfolder' => $output['subfolder'] ?? '',
                                            'type' => $output['type'] ?? 'output',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                // Also check queue to see if job is still running
                try {
                    $queueResponse = $httpClient->get("http://{$this->host}/queue");
                    if ($queueResponse->getStatusCode() === 200) {
                        $queueData = json_decode($queueResponse->getBody()->getContents(), true);
                        // If queue is empty or doesn't contain our job, it might be done
                        // But we still need to check history for the output
                    }
                } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                    // Queue endpoint might not be available, continue
                }

            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                Log::warning('ComfyUI polling error', [
                    'client_id' => $clientId,
                    'error' => $e->getMessage(),
                ]);
                // Continue polling on error - network issues might be temporary
            }

            // Sleep before next poll
            usleep($this->pollInterval * 1000); // Convert ms to microseconds
        }
    }
}
