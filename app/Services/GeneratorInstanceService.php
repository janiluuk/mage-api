<?php

namespace App\Services;

use App\Models\GeneratorInstance;
use Illuminate\Support\Facades\Log;

class GeneratorInstanceService
{
    /**
     * Get a random enabled generator instance URL.
     *
     * @param string|null $type Filter by instance type (stable_diffusion_forge or comfyui)
     * @return string|null
     */
    public function getEnabledInstanceUrl(?string $type = null): ?string
    {
        $query = GeneratorInstance::enabled();

        if ($type) {
            $query->where('type', $type);
        }

        $instance = $query->inRandomOrder()->first();

        if (!$instance) {
            Log::warning('No enabled generator instance found', ['type' => $type]);
            return null;
        }

        Log::info('Selected generator instance', [
            'id' => $instance->id,
            'name' => $instance->name,
            'url' => $instance->url,
            'type' => $instance->type,
        ]);

        return rtrim($instance->url, '/');
    }

    /**
     * Get an enabled generator instance.
     *
     * @param string|null $type Filter by instance type
     * @return GeneratorInstance|null
     */
    public function getEnabledInstance(?string $type = null): ?GeneratorInstance
    {
        $query = GeneratorInstance::enabled();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->inRandomOrder()->first();
    }
}
