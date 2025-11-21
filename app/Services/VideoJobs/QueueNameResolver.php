<?php

namespace App\Services\VideoJobs;

class QueueNameResolver
{
    public function resolve(string $envKey, string $default): string
    {
        $queue = env($envKey);

        return ! empty($queue) ? $queue : $default;
    }
}
