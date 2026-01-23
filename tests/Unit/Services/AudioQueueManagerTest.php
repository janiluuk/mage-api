<?php

namespace Tests\Unit\Services;

use App\Services\Audio\AudioQueueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AudioQueueManagerTest extends TestCase
{
    use RefreshDatabase;

    private AudioQueueManager $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = new AudioQueueManager();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_enqueue_creates_new_job(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);

        $this->assertIsArray($job);
        $this->assertArrayHasKey('id', $job);
        $this->assertArrayHasKey('status', $job);
        $this->assertArrayHasKey('createdAt', $job);
        $this->assertArrayHasKey('metadata', $job);
        $this->assertEquals('queued', $job['status']);
        $this->assertEquals('test', $job['metadata']['text']);
        $this->assertNotEmpty($job['id']);
    }

    public function test_mark_processing_updates_job_status(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);
        
        $updated = $this->queueManager->markProcessing($job['id']);

        $this->assertNotNull($updated);
        $this->assertEquals('processing', $updated['status']);
        $this->assertArrayHasKey('startedAt', $updated);
        $this->assertNotEmpty($updated['startedAt']);
    }

    public function test_mark_processing_returns_null_for_invalid_id(): void
    {
        $result = $this->queueManager->markProcessing('invalid-id');

        $this->assertNull($result);
    }

    public function test_mark_complete_moves_job_to_history(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);
        $this->queueManager->markProcessing($job['id']);
        
        $completed = $this->queueManager->markComplete($job['id']);

        $this->assertNotNull($completed);
        $this->assertEquals('completed', $completed['status']);
        $this->assertArrayHasKey('completedAt', $completed);
        
        $queue = $this->queueManager->getQueue();
        $this->assertCount(0, $queue['queued']);
        $this->assertCount(0, $queue['processing']);
        $this->assertCount(1, $queue['history']);
        $this->assertEquals($job['id'], $queue['history'][0]['id']);
    }

    public function test_mark_failed_moves_job_to_history_with_error(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);
        $this->queueManager->markProcessing($job['id']);
        
        $error = new \Exception('Test error');
        $failed = $this->queueManager->markFailed($job['id'], $error);

        $this->assertNotNull($failed);
        $this->assertEquals('failed', $failed['status']);
        $this->assertArrayHasKey('error', $failed);
        $this->assertArrayHasKey('completedAt', $failed);
        $this->assertEquals('Test error', $failed['error']);
        
        $queue = $this->queueManager->getQueue();
        $this->assertCount(1, $queue['history']);
        $this->assertEquals('failed', $queue['history'][0]['status']);
    }

    public function test_get_queue_returns_correct_structure(): void
    {
        $queue = $this->queueManager->getQueue();

        $this->assertIsArray($queue);
        $this->assertArrayHasKey('queued', $queue);
        $this->assertArrayHasKey('processing', $queue);
        $this->assertArrayHasKey('history', $queue);
        $this->assertIsArray($queue['queued']);
        $this->assertIsArray($queue['processing']);
        $this->assertIsArray($queue['history']);
    }

    public function test_get_queue_separates_queued_and_processing(): void
    {
        $job1 = $this->queueManager->enqueue(['text' => 'job1']);
        $job2 = $this->queueManager->enqueue(['text' => 'job2']);
        $this->queueManager->markProcessing($job1['id']);

        $queue = $this->queueManager->getQueue();

        $this->assertCount(1, $queue['queued']);
        $this->assertCount(1, $queue['processing']);
        $this->assertEquals($job2['id'], $queue['queued'][0]['id']);
        $this->assertEquals($job1['id'], $queue['processing'][0]['id']);
    }

    public function test_get_status_returns_summary(): void
    {
        $status = $this->queueManager->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('processing', $status);
        $this->assertArrayHasKey('queued', $status);
        $this->assertArrayHasKey('recent', $status);
        $this->assertNull($status['processing']);
        $this->assertEquals(0, $status['queued']);
        $this->assertIsArray($status['recent']);
    }

    public function test_get_status_includes_processing_job(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);
        $this->queueManager->markProcessing($job['id']);

        $status = $this->queueManager->getStatus();

        $this->assertNotNull($status['processing']);
        $this->assertEquals($job['id'], $status['processing']['id']);
        $this->assertEquals(0, $status['queued']);
    }

    public function test_get_status_includes_recent_history(): void
    {
        $job1 = $this->queueManager->enqueue(['text' => 'job1']);
        $job2 = $this->queueManager->enqueue(['text' => 'job2']);
        $this->queueManager->markProcessing($job1['id']);
        $this->queueManager->markComplete($job1['id']);
        $this->queueManager->markProcessing($job2['id']);
        $this->queueManager->markComplete($job2['id']);

        $status = $this->queueManager->getStatus();

        $this->assertCount(2, $status['recent']);
        // Recent should be in reverse order (newest first)
        $this->assertEquals($job2['id'], $status['recent'][0]['id']);
        $this->assertEquals($job1['id'], $status['recent'][1]['id']);
    }

    public function test_history_is_limited(): void
    {
        // Create more than HISTORY_LIMIT (25) jobs
        for ($i = 0; $i < 30; $i++) {
            $job = $this->queueManager->enqueue(['text' => "job{$i}"]);
            $this->queueManager->markProcessing($job['id']);
            $this->queueManager->markComplete($job['id']);
        }

        $queue = $this->queueManager->getQueue();

        // History should be limited to 25
        $this->assertLessThanOrEqual(25, count($queue['history']));
    }

    public function test_reset_clears_all_jobs(): void
    {
        $job = $this->queueManager->enqueue(['text' => 'test']);
        $this->queueManager->markProcessing($job['id']);
        $this->queueManager->markComplete($job['id']);

        $this->queueManager->reset();

        $queue = $this->queueManager->getQueue();
        $this->assertCount(0, $queue['queued']);
        $this->assertCount(0, $queue['processing']);
        $this->assertCount(0, $queue['history']);
    }
}

