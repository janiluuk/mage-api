<?php

namespace App\Services\ComfyUI;

use Illuminate\Support\Facades\Storage;

class PromptBuilder
{
    private array $workflow;

    public function __construct()
    {
        $workflowPath = storage_path('app/comfy/audio-workflow.json');
        
        if (!file_exists($workflowPath)) {
            throw new \RuntimeException("Audio workflow file not found: {$workflowPath}");
        }

        $workflowContent = file_get_contents($workflowPath);
        $this->workflow = json_decode($workflowContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in audio workflow file: ' . json_last_error_msg());
        }
    }

    /**
     * Build a ComfyUI prompt from the audio workflow template with the given text.
     *
     * @param string $text Text prompt for audio generation
     * @return array ComfyUI workflow prompt object
     */
    public function buildPrompt(string $text): array
    {
        // Deep copy the workflow
        $prompt = json_decode(json_encode($this->workflow), true);

        // Set the text input if the workflow structure matches
        if (isset($prompt['1']) && isset($prompt['1']['inputs']) && array_key_exists('text', $prompt['1']['inputs'])) {
            $prompt['1']['inputs']['text'] = $text;
        }

        return $prompt;
    }
}

