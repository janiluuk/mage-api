<?php

namespace App\Services;

use App\Models\GeneratorInstance;
use Illuminate\Support\Facades\Log;

class GeneratorInstanceService
{
    protected LoadBalancerService $loadBalancer;

    public function __construct(LoadBalancerService $loadBalancer)
    {
        $this->loadBalancer = $loadBalancer;
    }

    /**
     * Get a load-balanced generator instance URL.
     *
     * @param string|null $type Filter by instance type (stable_diffusion_forge or comfyui)
     * @return string|null
     */
    public function getEnabledInstanceUrl(?string $type = null): ?string
    {
        $instance = $this->getEnabledInstance($type);

        if (!$instance) {
            Log::warning('No enabled generator instance found', ['type' => $type]);
            return null;
        }

        return rtrim($instance->url, '/');
    }

    /**
     * Get an enabled generator instance using load balancing.
     *
     * @param string|null $type Filter by instance type
     * @return GeneratorInstance|null
     */
    public function getEnabledInstance(?string $type = null): ?GeneratorInstance
    {
        return $this->loadBalancer->selectInstance($type);
    }
}
