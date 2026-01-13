<?php

namespace App\Services;

use App\Models\SdInstance;
use Illuminate\Support\Facades\Log;

class SdInstanceService
{
    /**
     * Get a random enabled SD instance URL.
     *
     * @param string|null $type Filter by instance type (stable_diffusion_forge or comfyui)
     * @return string|null
     */
    public function getEnabledInstanceUrl(?string $type = null): ?string
    {
        $query = SdInstance::enabled();

        if ($type) {
            $query->where('type', $type);
        }

        $instance = $query->inRandomOrder()->first();

        if (!$instance) {
            Log::warning('No enabled SD instance found', ['type' => $type]);
            return null;
        }

        Log::info('Selected SD instance', [
            'id' => $instance->id,
            'name' => $instance->name,
            'url' => $instance->url,
            'type' => $instance->type,
        ]);

        return rtrim($instance->url, '/');
    }

    /**
     * Get an enabled SD instance.
     *
     * @param string|null $type Filter by instance type
     * @return SdInstance|null
     */
    public function getEnabledInstance(?string $type = null): ?SdInstance
    {
        $query = SdInstance::enabled();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->inRandomOrder()->first();
    }
}
