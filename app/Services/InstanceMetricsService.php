<?php

namespace App\Services;

use App\Models\GeneratorInstance;
use App\Services\ComfyUI\ComfyUIClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InstanceMetricsService
{
    protected Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }

    /**
     * Collect metrics for all enabled instances.
     *
     * @return void
     */
    public function collectMetricsForAllInstances(): void
    {
        $instances = GeneratorInstance::enabled()->get();

        foreach ($instances as $instance) {
            // Use cache to prevent hammering instances (skip if checked in last 10 seconds)
            $cacheKey = "instance_metrics_check_{$instance->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            try {
                $this->collectMetrics($instance);
                // Cache for 10 seconds to prevent rapid successive checks
                Cache::put($cacheKey, true, 10);
            } catch (\Exception $e) {
                Log::warning('Failed to collect metrics for instance', [
                    'instance_id' => $instance->id,
                    'instance_name' => $instance->name,
                    'error' => $e->getMessage(),
                ]);

                // Mark as offline on error
                $instance->update([
                    'health_status' => 'offline',
                    'last_health_check_at' => now(),
                ]);
            }
        }

        // Clean up old metrics history periodically (once per 100 runs to avoid overhead)
        if (rand(1, 100) === 1) {
            $this->cleanupOldMetricsHistory();
        }
    }

    /**
     * Collect metrics for a specific instance.
     *
     * @param GeneratorInstance $instance
     * @return array|null
     */
    public function collectMetrics(GeneratorInstance $instance): ?array
    {
        $metrics = [
            'health_status' => 'offline',
            'current_model' => null,
            'gpu_utilization' => null,
            'cpu_utilization' => null,
            'memory_utilization' => null,
            'last_health_check_at' => now(),
        ];

        try {
            if ($instance->type === 'comfyui') {
                $metrics = array_merge($metrics, $this->collectComfyUIMetrics($instance));
            } elseif ($instance->type === 'stable_diffusion_forge') {
                $metrics = array_merge($metrics, $this->collectSDForgeMetrics($instance));
            }

            // Calculate health status based on queue load if not explicitly set
            if ($metrics['health_status'] === 'online') {
                $totalLoad = $instance->queue_size + $instance->processing_count;
                // Consider instance degraded if queue is very full
                if ($totalLoad > 50) {
                    $metrics['health_status'] = 'degraded';
                }
            }

            // Update instance with collected metrics
            $instance->update($metrics);

            // Store in history (keep last 24 hours)
            $this->storeMetricsHistory($instance, $metrics);

            Log::debug('Metrics collected for instance', [
                'instance_id' => $instance->id,
                'metrics' => $metrics,
            ]);

            return $metrics;

        } catch (\Exception $e) {
            Log::error('Error collecting metrics', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            $instance->update([
                'health_status' => 'offline',
                'last_health_check_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Collect metrics from ComfyUI instance.
     *
     * @param GeneratorInstance $instance
     * @return array
     */
    protected function collectComfyUIMetrics(GeneratorInstance $instance): array
    {
        $metrics = [
            'health_status' => 'online',
        ];

        try {
            // Get queue status
            $queueResponse = $this->httpClient->get(rtrim($instance->url, '/') . '/queue', [
                'timeout' => 3,
            ]);

            $queueData = json_decode($queueResponse->getBody()->getContents(), true);

            // Extract queue info
            if (isset($queueData['queue_running'])) {
                $metrics['processing_count'] = count($queueData['queue_running']);
            }

            if (isset($queueData['queue_pending'])) {
                // Update queue_size from queue data
                // Note: We also track this separately via instance_jobs table
            }

            // Try to get system info (if available)
            try {
                $systemResponse = $this->httpClient->get(rtrim($instance->url, '/') . '/system_stats', [
                    'timeout' => 2,
                ]);

                $systemData = json_decode($systemResponse->getBody()->getContents(), true);

                if (isset($systemData['gpu_utilization'])) {
                    $metrics['gpu_utilization'] = (int) $systemData['gpu_utilization'];
                }

                if (isset($systemData['cpu_utilization'])) {
                    $metrics['cpu_utilization'] = (int) $systemData['cpu_utilization'];
                }

                if (isset($systemData['memory_utilization'])) {
                    $metrics['memory_utilization'] = (int) $systemData['memory_utilization'];
                }

                if (isset($systemData['current_model'])) {
                    $metrics['current_model'] = $systemData['current_model'];
                }
            } catch (\Exception $e) {
                // System stats endpoint might not exist, that's OK
                Log::debug('System stats endpoint not available', [
                    'instance_id' => $instance->id,
                ]);
            }

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $metrics['health_status'] = 'offline';
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() < 500) {
                // Server is responding but might be degraded
                $metrics['health_status'] = 'degraded';
            } else {
                $metrics['health_status'] = 'offline';
            }
        }

        return $metrics;
    }

    /**
     * Collect metrics from Stable Diffusion Forge instance.
     *
     * @param GeneratorInstance $instance
     * @return array
     */
    protected function collectSDForgeMetrics(GeneratorInstance $instance): array
    {
        $metrics = [
            'health_status' => 'online',
        ];

        try {
            // Try to check health endpoint
            $healthResponse = $this->httpClient->get(rtrim($instance->url, '/') . '/health', [
                'timeout' => 3,
            ]);

            // If we get here, instance is online
            $healthData = json_decode($healthResponse->getBody()->getContents(), true);

            // Extract metrics if available
            if (isset($healthData['gpu_utilization'])) {
                $metrics['gpu_utilization'] = (int) $healthData['gpu_utilization'];
            }

            if (isset($healthData['cpu_utilization'])) {
                $metrics['cpu_utilization'] = (int) $healthData['cpu_utilization'];
            }

            if (isset($healthData['memory_utilization'])) {
                $metrics['memory_utilization'] = (int) $healthData['memory_utilization'];
            }

            if (isset($healthData['current_model'])) {
                $metrics['current_model'] = $healthData['current_model'];
            }

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $metrics['health_status'] = 'offline';
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Try basic connection check
            try {
                $this->httpClient->get(rtrim($instance->url, '/'), ['timeout' => 2]);
                $metrics['health_status'] = 'degraded';
            } catch (\Exception $e2) {
                $metrics['health_status'] = 'offline';
            }
        }

        return $metrics;
    }

    /**
     * Store metrics in history table.
     *
     * @param GeneratorInstance $instance
     * @param array $metrics
     * @return void
     */
    protected function storeMetricsHistory(GeneratorInstance $instance, array $metrics): void
    {
        try {
            DB::table('instance_metrics_history')->insert([
                'instance_id' => $instance->id,
                'current_model' => $metrics['current_model'] ?? null,
                'gpu_utilization' => $metrics['gpu_utilization'] ?? null,
                'cpu_utilization' => $metrics['cpu_utilization'] ?? null,
                'memory_utilization' => $metrics['memory_utilization'] ?? null,
                'queue_size' => $instance->queue_size,
                'processing_count' => $instance->processing_count,
                'health_status' => $metrics['health_status'] ?? 'offline',
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store metrics history', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up old metrics history (called periodically to avoid overhead).
     *
     * @return void
     */
    protected function cleanupOldMetricsHistory(): void
    {
        try {
            $deleted = DB::table('instance_metrics_history')
                ->where('recorded_at', '<', now()->subHours(24))
                ->delete();

            if ($deleted > 0) {
                Log::debug('Cleaned up old metrics history', ['deleted_count' => $deleted]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old metrics history', ['error' => $e->getMessage()]);
        }
    }
}
