<?php

namespace App\Services\Audio;

use App\Services\ComfyUI\ComfyClient;
use App\Services\ComfyUI\ComfyWebSocketClient;
use App\Services\ComfyUI\PromptBuilder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AudioGenerationService
{
    private PromptBuilder $promptBuilder;
    private ComfyClient $comfyClient;
    private ComfyWebSocketClient $wsClient;
    private AudioProcessor $audioProcessor;
    private AudioQueueManager $queueManager;

    public function __construct(
        PromptBuilder $promptBuilder,
        ComfyClient $comfyClient,
        ComfyWebSocketClient $wsClient,
        AudioProcessor $audioProcessor,
        AudioQueueManager $queueManager
    ) {
        $this->promptBuilder = $promptBuilder;
        $this->comfyClient = $comfyClient;
        $this->wsClient = $wsClient;
        $this->audioProcessor = $audioProcessor;
        $this->queueManager = $queueManager;
    }

    /**
     * Generate audio from text using ComfyUI and return processed audio.
     *
     * @param string $text Text prompt for audio generation
     * @param string|null $host ComfyUI host and port (optional, uses config default)
     * @return string Processed AAC audio data
     * @throws \Exception
     */
    public function generateAudio(string $text, ?string $host = null): string
    {
        $clientId = (string) Str::uuid();
        
        if ($host) {
            $this->comfyClient = new ComfyClient($host);
            $this->wsClient = new ComfyWebSocketClient($host);
        }

        try {
            $prompt = $this->promptBuilder->buildPrompt($text);
            
            Log::info('Queueing ComfyUI prompt', [
                'client_id' => $clientId,
                'text_length' => strlen($text),
            ]);

            $this->comfyClient->queuePrompt($prompt, $clientId);

            Log::info('Waiting for ComfyUI result', ['client_id' => $clientId]);
            $fileInfo = $this->wsClient->waitForResult($clientId);

            Log::info('Fetching audio from ComfyUI', [
                'client_id' => $clientId,
                'file_info' => $fileInfo,
            ]);
            $audioBuffer = $this->comfyClient->fetchAudio($fileInfo);

            Log::info('Processing audio', ['client_id' => $clientId]);
            return $this->audioProcessor->processAudio($audioBuffer);
        } catch (\Exception $e) {
            Log::error('Audio generation failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate and stream audio directly to output.
     *
     * @param string $text Text prompt for audio generation
     * @param resource $outputStream Output stream
     * @param string|null $host ComfyUI host and port (optional)
     * @return void
     * @throws \Exception
     */
    public function generateAndStream(string $text, $outputStream, ?string $host = null): void
    {
        $processedAudio = $this->generateAudio($text, $host);
        fwrite($outputStream, $processedAudio);
    }
}

