<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AudioQueueManager
{
    private const CACHE_PREFIX = 'audio_queue:';
    private const HISTORY_PREFIX = 'audio_history:';
    private const HISTORY_LIMIT = 25;

    /**
     * Add a new job to the queue.
     *
     * @param array $metadata Additional metadata for the job
     * @return array The created job with id, status, createdAt, and metadata
     */
    public function enqueue(array $metadata = []): array
    {
        $job = [
            'id' => (string) Str::uuid(),
            'status' => 'queued',
            'createdAt' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];

        $this->addToActiveJobs($job);

        return $job;
    }

    /**
     * Mark a job as processing.
     *
     * @param string $id Job ID
     * @return array|null The updated job or null if not found
     */
    public function markProcessing(string $id): ?array
    {
        $job = $this->findJob($id);
        if (!$job) {
            return null;
        }

        $job['status'] = 'processing';
        $job['startedAt'] = now()->toIso8601String();
        $this->updateActiveJob($job);

        return $job;
    }

    /**
     * Mark a job as completed and move it to history.
     *
     * @param string $id Job ID
     * @return array|null The completed job or null if not found
     */
    public function markComplete(string $id): ?array
    {
        $job = $this->removeJob($id);
        if (!$job) {
            return null;
        }

        $job['status'] = 'completed';
        $job['completedAt'] = now()->toIso8601String();
        $this->addToHistory($job);

        return $job;
    }

    /**
     * Mark a job as failed and move it to history.
     *
     * @param string $id Job ID
     * @param \Throwable|string $error Error object or message
     * @return array|null The failed job or null if not found
     */
    public function markFailed(string $id, $error): ?array
    {
        $job = $this->removeJob($id);
        if (!$job) {
            return null;
        }

        $job['status'] = 'failed';
        $job['completedAt'] = now()->toIso8601String();
        $job['error'] = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
        $this->addToHistory($job);

        return $job;
    }

    /**
     * Get the current queue state with queued jobs, processing jobs, and history.
     *
     * @return array Object containing queued, processing, and history arrays
     */
    public function getQueue(): array
    {
        $activeJobs = $this->getActiveJobs();
        $queued = array_filter($activeJobs, fn($job) => $job['status'] === 'queued');
        $processing = array_filter($activeJobs, fn($job) => $job['status'] === 'processing');

        return [
            'queued' => array_values($queued),
            'processing' => array_values($processing),
            'history' => $this->getHistory(),
        ];
    }

    /**
     * Get a summary of the current queue status.
     *
     * @return array Object with current processing job, queue count, and recent history
     */
    public function getStatus(): array
    {
        $queue = $this->getQueue();
        $processing = !empty($queue['processing']) ? $queue['processing'][0] : null;
        $recent = array_slice($queue['history'], 0, 5);

        return [
            'processing' => $processing,
            'queued' => count($queue['queued']),
            'recent' => $recent,
        ];
    }

    /**
     * Reset the queue manager, clearing all active jobs and history.
     */
    public function reset(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'active');
        Cache::forget(self::HISTORY_PREFIX . 'list');
    }

    private function getActiveJobs(): array
    {
        return Cache::get(self::CACHE_PREFIX . 'active', []);
    }

    private function addToActiveJobs(array $job): void
    {
        $jobs = $this->getActiveJobs();
        $jobs[] = $job;
        Cache::forever(self::CACHE_PREFIX . 'active', $jobs);
    }

    private function updateActiveJob(array $job): void
    {
        $jobs = $this->getActiveJobs();
        $index = array_search($job['id'], array_column($jobs, 'id'));
        if ($index !== false) {
            $jobs[$index] = $job;
            Cache::forever(self::CACHE_PREFIX . 'active', $jobs);
        }
    }

    private function findJob(string $id): ?array
    {
        $jobs = $this->getActiveJobs();
        $index = array_search($id, array_column($jobs, 'id'));
        return $index !== false ? $jobs[$index] : null;
    }

    private function removeJob(string $id): ?array
    {
        $jobs = $this->getActiveJobs();
        $index = array_search($id, array_column($jobs, 'id'));
        if ($index === false) {
            return null;
        }

        $job = $jobs[$index];
        unset($jobs[$index]);
        $jobs = array_values($jobs);
        Cache::forever(self::CACHE_PREFIX . 'active', $jobs);

        return $job;
    }

    private function getHistory(): array
    {
        return Cache::get(self::HISTORY_PREFIX . 'list', []);
    }

    private function addToHistory(array $job): void
    {
        $history = $this->getHistory();
        array_unshift($history, $job);
        
        // Limit history size
        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, 0, self::HISTORY_LIMIT);
        }

        Cache::forever(self::HISTORY_PREFIX . 'list', $history);
    }
}

