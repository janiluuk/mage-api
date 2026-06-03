<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FilmProduction;
use App\Models\Sequence;
use App\Models\Shot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ============================================================================
    // Film Project CRUD Tests
    // ============================================================================

    public function testGetProjectsRequiresAuthentication(): void
    {
        $response = $this->getJson('/api/film-projects');

        $response->assertStatus(401);
    }

    public function testGetProjectsReturnsEmptyArrayWhenNoProjects(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/film-projects');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
        $this->assertIsArray($response->json('data'));
    }

    public function testCreateProjectRequiresAuthentication(): void
    {
        $response = $this->postJson('/api/film-projects', [
            'name' => 'Test Project',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(401);
    }

    public function testCreateProjectWithValidData(): void
    {
        $projectData = [
            'name' => 'My Film Project',
            'description' => 'A test film project',
            'status' => 'draft',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/film-projects', $projectData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'description',
                'status',
                'user_id',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertDatabaseHas('film_productions', [
            'name' => 'My Film Project',
            'description' => 'A test film project',
            'status' => 'draft',
            'user_id' => $this->user->id,
        ]);
    }

    public function testCreateProjectRequiresName(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/film-projects', [
                'description' => 'Test Description',
            ]);

        $response->assertStatus(400);
    }

    public function testGetProjectById(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Project',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/film-projects/{$project->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'user_id',
            ],
        ]);
        $this->assertEquals('Test Project', $response->json('data.name'));
    }

    public function testGetProjectByIdReturns404ForNonExistent(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/film-projects/99999');

        $response->assertStatus(404);
    }

    public function testUpdateProject(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/film-projects/{$project->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated Description',
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('film_productions', [
            'id' => $project->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status' => 'in_progress',
        ]);
    }

    public function testDeleteProject(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/film-projects/{$project->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('film_productions', [
            'id' => $project->id,
        ]);
    }

    // ============================================================================
    // Sequence CRUD Tests
    // ============================================================================

    public function testGetSequencesForProject(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Sequence::factory()->count(3)->create([
            'film_production_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/film-projects/{$project->id}/sequences");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'film_production_id',
                ],
            ],
        ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function testCreateSequence(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $sequenceData = [
            'name' => 'Opening Sequence',
            'description' => 'The opening scene',
            'order' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/film-projects/{$project->id}/sequences", $sequenceData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sequences', [
            'film_production_id' => $project->id,
            'name' => 'Opening Sequence',
            'description' => 'The opening scene',
            'order' => 1,
        ]);
    }

    public function testUpdateSequence(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}", [
                'name' => 'Updated Sequence Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sequences', [
            'id' => $sequence->id,
            'name' => 'Updated Sequence Name',
        ]);
    }

    public function testDeleteSequence(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('sequences', [
            'id' => $sequence->id,
        ]);
    }

    // ============================================================================
    // Shot CRUD Tests
    // ============================================================================

    public function testGetShotsForSequence(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);

        Shot::factory()->count(2)->create([
            'film_production_id' => $project->id,
            'sequence_id' => $sequence->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}/shots");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'film_production_id',
                    'sequence_id',
                ],
            ],
        ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function testCreateShot(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);

        $shotData = [
            'name' => 'Close-up Shot',
            'description' => 'A close-up of the character',
            'duration' => 300,
            'order' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}/shots", $shotData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('shots', [
            'film_production_id' => $project->id,
            'sequence_id' => $sequence->id,
            'name' => 'Close-up Shot',
            'duration' => 300,
        ]);
    }

    public function testUpdateShot(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);
        $shot = Shot::factory()->create([
            'film_production_id' => $project->id,
            'sequence_id' => $sequence->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}/shots/{$shot->id}", [
                'name' => 'Updated Shot Name',
                'duration' => 500,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('shots', [
            'id' => $shot->id,
            'name' => 'Updated Shot Name',
            'duration' => 500,
        ]);
    }

    public function testDeleteShot(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);
        $shot = Shot::factory()->create([
            'film_production_id' => $project->id,
            'sequence_id' => $sequence->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}/shots/{$shot->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('shots', [
            'id' => $shot->id,
        ]);
    }

    // ============================================================================
    // AI Generation Tests
    // ============================================================================

    public function testGetAvailableModelsRequiresAuthentication(): void
    {
        $response = $this->getJson('/api/film-projects/ai/models');

        $response->assertStatus(401);
    }

    public function testGetAvailableModels(): void
    {
        // Mock the AI service or test with actual service if available
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/film-projects/ai/models');

        // This will fail if AI service is not available, but structure should be correct
        $response->assertJsonStructure([
            'success',
        ]);
    }

    public function testGenerateScriptRequiresAuthentication(): void
    {
        $response = $this->postJson('/api/film-projects/1/generate/script', [
            'prompt' => 'Test prompt',
        ]);

        $response->assertStatus(401);
    }

    public function testGenerateScriptRequiresPrompt(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/film-projects/{$project->id}/generate/script", []);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function testGenerateSceneRequiresAuthentication(): void
    {
        $response = $this->postJson('/api/film-projects/1/sequences/1/shots/1/generate/scene', [
            'prompt' => 'Test prompt',
        ]);

        $response->assertStatus(401);
    }

    public function testGenerateSceneRequiresPrompt(): void
    {
        $project = FilmProduction::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $sequence = Sequence::factory()->create([
            'film_production_id' => $project->id,
        ]);
        $shot = Shot::factory()->create([
            'film_production_id' => $project->id,
            'sequence_id' => $sequence->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/film-projects/{$project->id}/sequences/{$sequence->id}/shots/{$shot->id}/generate/scene", []);

        $response->assertStatus(400);
    }

    // ============================================================================
    // Authorization Tests
    // ============================================================================

    public function testUserCannotAccessOtherUsersProject(): void
    {
        $otherUser = User::factory()->create();
        $project = FilmProduction::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/film-projects/{$project->id}");

        // Should either return 404 or 403 depending on implementation
        $this->assertContains($response->status(), [403, 404]);
    }
}

