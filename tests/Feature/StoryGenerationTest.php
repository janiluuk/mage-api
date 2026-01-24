<?php

namespace Tests\Feature;

use App\Models\StoryBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_generation_creates_batch(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $payload = [
            'story' => [
                'name' => 'Test Story',
                'scenes' => [
                    [
                        'name' => 'Scene 1',
                        'frames' => [
                            ['id' => 0, 'prompt' => 'Frame 1'],
                            ['id' => 10, 'prompt' => 'Frame 2'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/story/generate', $payload);

        $response->assertOk();
        $response->assertJsonStructure(['batchId', 'totalFrames']);
        $this->assertSame(2, $response->json('totalFrames'));

        $this->assertDatabaseHas('story_batches', [
            'user_id' => $user->id,
            'total_frames' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_story_batch_status_returns_progress(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $batch = StoryBatch::create([
            'user_id' => $user->id,
            'status' => 'processing',
            'total_frames' => 10,
            'completed_frames' => 4,
            'progress' => 40,
        ]);

        $response = $this->getJson("/api/story/batch/{$batch->id}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'processing',
            'progress' => 40,
            'completedFrames' => 4,
            'totalFrames' => 10,
        ]);
    }

    public function test_can_fetch_saved_story_config(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $config = [
            'story' => [
                'name' => 'Saved Story',
                'scenes' => [
                    [
                        'name' => 'Scene 1',
                        'frames' => [
                            ['id' => 0, 'prompt' => 'Frame 1'],
                        ],
                    ],
                ],
            ],
            'config' => [
                'fps' => 24,
            ],
        ];

        $batch = StoryBatch::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_frames' => 1,
            'completed_frames' => 0,
            'progress' => 0,
            'config_json' => json_encode($config),
        ]);

        $response = $this->getJson("/api/story/batch/{$batch->id}/config");

        $response->assertOk();
        $response->assertJsonFragment([
            'batchId' => (string) $batch->id,
        ]);
        $this->assertSame('Saved Story', $response->json('config.story.name'));
    }

    public function test_can_update_saved_story_config(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $batch = StoryBatch::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_frames' => 1,
            'completed_frames' => 0,
            'progress' => 0,
            'config_json' => json_encode([
                'story' => [
                    'name' => 'Original Story',
                    'scenes' => [
                        [
                            'name' => 'Scene 1',
                            'frames' => [
                                ['id' => 0, 'prompt' => 'Frame 1'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->patchJson("/api/story/batch/{$batch->id}", [
            'story' => [
                'name' => 'Updated Story',
                'scenes' => [
                    [
                        'name' => 'Scene 1',
                        'frames' => [
                            ['id' => 0, 'prompt' => 'Frame 1'],
                        ],
                    ],
                ],
            ],
            'config' => [
                'fps' => 30,
            ],
        ]);

        $response->assertOk();
        $batch->refresh();
        $saved = json_decode($batch->config_json, true);

        $this->assertSame('Updated Story', $saved['story']['name']);
        $this->assertSame(30, $saved['config']['fps']);
    }

    public function test_persist_frame_stores_image_and_updates_progress(): void
    {
        $this->withoutMiddleware();
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $batch = StoryBatch::create([
            'user_id' => $user->id,
            'status' => 'processing',
            'total_frames' => 1,
            'completed_frames' => 0,
            'progress' => 0,
        ]);

        $base64 = 'data:image/png;base64,' . base64_encode('fake-image-data');
        $response = $this->postJson("/api/story/batch/{$batch->id}/frames", [
            'frameId' => 1,
            'prompt' => 'Frame prompt',
            'image' => $base64,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['frameId', 'imageUrl', 'thumbnailUrl']);

        $batch->refresh();
        $this->assertSame(1, $batch->completed_frames);
        $this->assertSame(100, $batch->progress);
        $this->assertSame('complete', $batch->status);
    }

    public function test_share_story_returns_share_url(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $batch = StoryBatch::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_frames' => 0,
            'completed_frames' => 0,
            'progress' => 0,
        ]);

        $response = $this->postJson('/api/story/share', [
            'story' => [
                'name' => 'Shared Story',
                'scenes' => [],
            ],
            'batchId' => (string) $batch->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['shareUrl']);
        $this->assertNotNull($batch->fresh()->share_token);
    }
}

