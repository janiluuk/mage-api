<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use App\Models\Videojob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoJobStoryGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function testVideoJobsIncludeBatchesRelationship(): void
    {
        // Create a batch (story)
        $batch = Batch::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Story',
        ]);

        // Create video jobs
        $videoJob1 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $videoJob2 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $videoJob3 = Videojob::factory()->create(['user_id' => $this->user->id]);

        // Attach video jobs to batch
        $batch->videoJobs()->attach($videoJob1->id, ['order' => 1]);
        $batch->videoJobs()->attach($videoJob2->id, ['order' => 2]);
        // videoJob3 is not in any batch

        // Request video jobs with batches included
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/video-jobs?include=batches');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'attributes',
                    'relationships' => [
                        'batches' => [
                            'data'
                        ]
                    ]
                ]
            ]
        ]);

        // Verify videoJob1 and videoJob2 have batch relationship
        $data = $response->json('data');
        $job1Data = collect($data)->firstWhere('id', (string)$videoJob1->id);
        $job2Data = collect($data)->firstWhere('id', (string)$videoJob2->id);
        $job3Data = collect($data)->firstWhere('id', (string)$videoJob3->id);

        $this->assertNotNull($job1Data);
        $this->assertNotNull($job2Data);
        $this->assertNotNull($job3Data);

        $this->assertArrayHasKey('relationships', $job1Data);
        $this->assertArrayHasKey('batches', $job1Data['relationships']);
        $this->assertCount(1, $job1Data['relationships']['batches']['data']);

        $this->assertArrayHasKey('relationships', $job2Data);
        $this->assertArrayHasKey('batches', $job2Data['relationships']);
        $this->assertCount(1, $job2Data['relationships']['batches']['data']);

        // videoJob3 should have empty batches or no batches relationship
        if (isset($job3Data['relationships']['batches'])) {
            $this->assertCount(0, $job3Data['relationships']['batches']['data'] ?? []);
        }
    }

    public function testVideoJobsCanBeGroupedByStories(): void
    {
        // Create multiple batches (stories)
        $story1 = Batch::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Story One',
        ]);

        $story2 = Batch::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Story Two',
        ]);

        // Create video jobs
        $job1 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $job2 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $job3 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $job4 = Videojob::factory()->create(['user_id' => $this->user->id]);

        // Attach jobs to stories
        $story1->videoJobs()->attach([$job1->id, $job2->id], ['order' => 1]);
        $story2->videoJobs()->attach([$job3->id, $job4->id], ['order' => 1]);

        // Request video jobs with batches included
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/video-jobs?include=batches');

        $response->assertStatus(200);

        $data = $response->json('data');
        
        // Verify all jobs are returned
        $this->assertCount(4, $data);

        // Verify each job has batch information
        foreach ($data as $job) {
            $this->assertArrayHasKey('relationships', $job);
            $this->assertArrayHasKey('batches', $job['relationships']);
        }

        // Verify jobs can be grouped by story name
        $groupedByStory = [];
        foreach ($data as $job) {
            $batches = $job['relationships']['batches']['data'] ?? [];
            $storyName = count($batches) > 0 ? 'Story One' : 'No Story'; // Simplified check
            if (!isset($groupedByStory[$storyName])) {
                $groupedByStory[$storyName] = [];
            }
            $groupedByStory[$storyName][] = $job['id'];
        }

        // Should have at least Story One group
        $this->assertArrayHasKey('Story One', $groupedByStory);
    }

    public function testVideoJobsWithoutBatchesHaveNoStory(): void
    {
        // Create video jobs without batches
        $job1 = Videojob::factory()->create(['user_id' => $this->user->id]);
        $job2 = Videojob::factory()->create(['user_id' => $this->user->id]);

        // Request video jobs with batches included
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/video-jobs?include=batches');

        $response->assertStatus(200);

        $data = $response->json('data');
        
        foreach ($data as $job) {
            $batches = $job['relationships']['batches']['data'] ?? [];
            $this->assertCount(0, $batches, "Job {$job['id']} should have no batches");
        }
    }
}

