<?php

namespace Tests\Feature;

use App\Models\Preset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_list_presets_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/presets');

        $response->assertStatus(401);
    }

    public function test_list_presets_returns_user_and_public_presets(): void
    {
        // User's own presets
        Preset::factory()->count(2)->for($this->user, 'user')->create();
        
        // Public presets from other users
        Preset::factory()->count(2)->create(['is_public' => true]);
        
        // Private presets from other users (should not be returned)
        Preset::factory()->count(3)->create(['is_public' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_list_presets_filters_by_category(): void
    {
        Preset::factory()->for($this->user, 'user')->create(['category' => 'vid2vid']);
        Preset::factory()->for($this->user, 'user')->create(['category' => 'deforum']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets?category=vid2vid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'vid2vid');
    }

    public function test_list_presets_filters_by_type(): void
    {
        Preset::factory()->for($this->user, 'user')->create(['type' => 'video']);
        Preset::factory()->for($this->user, 'user')->create(['type' => 'image']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets?type=video');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'video');
    }

    public function test_list_presets_filters_favorites(): void
    {
        Preset::factory()->for($this->user, 'user')->create(['is_favorite' => true]);
        Preset::factory()->for($this->user, 'user')->create(['is_favorite' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets?favorites_only=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_favorite', true);
    }

    public function test_list_presets_filters_own_only(): void
    {
        Preset::factory()->count(2)->for($this->user, 'user')->create();
        Preset::factory()->count(2)->create(['is_public' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets?own_only=true');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_create_preset_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/presets', [
            'name' => 'Test Preset',
            'type' => 'video',
            'settings' => ['key' => 'value'],
        ]);

        $response->assertStatus(401);
    }

    public function test_create_preset_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/presets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'settings']);
    }

    public function test_create_preset_validates_type(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/presets', [
                'name' => 'Test Preset',
                'type' => 'invalid_type',
                'settings' => ['key' => 'value'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_preset_creates_preset(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/presets', [
                'name' => 'Test Preset',
                'description' => 'Test description',
                'category' => 'vid2vid',
                'type' => 'video',
                'settings' => ['key' => 'value'],
                'is_public' => false,
                'is_favorite' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'preset' => ['id', 'name', 'settings']
            ]);

        $this->assertDatabaseHas('presets', [
            'user_id' => $this->user->id,
            'name' => 'Test Preset',
            'category' => 'vid2vid',
            'type' => 'video',
        ]);
    }

    public function test_show_preset_requires_authentication(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create();

        $response = $this->getJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(401);
    }

    public function test_show_preset_returns_own_preset(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $preset->id);
    }

    public function test_show_preset_returns_public_preset(): void
    {
        $preset = Preset::factory()->create(['is_public' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $preset->id);
    }

    public function test_show_preset_denies_private_preset_from_other_user(): void
    {
        $preset = Preset::factory()->create(['is_public' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(404);
    }

    public function test_update_preset_requires_authentication(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create();

        $response = $this->putJson("/api/v1/presets/{$preset->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_preset_checks_ownership(): void
    {
        $otherUser = User::factory()->create();
        $preset = Preset::factory()->for($otherUser, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/presets/{$preset->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(404);
    }

    public function test_update_preset_updates_preset(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create([
            'name' => 'Original Name',
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/presets/{$preset->id}", [
                'name' => 'Updated Name',
                'is_favorite' => true,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('presets', [
            'id' => $preset->id,
            'name' => 'Updated Name',
            'is_favorite' => true,
        ]);
    }

    public function test_delete_preset_requires_authentication(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create();

        $response = $this->deleteJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(401);
    }

    public function test_delete_preset_checks_ownership(): void
    {
        $otherUser = User::factory()->create();
        $preset = Preset::factory()->for($otherUser, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(404);
    }

    public function test_delete_preset_deletes_preset(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/presets/{$preset->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('presets', [
            'id' => $preset->id,
        ]);
    }

    public function test_mark_preset_as_used_increments_usage(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create([
            'usage_count' => 0,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/presets/{$preset->id}/use");

        $response->assertStatus(200);

        $this->assertDatabaseHas('presets', [
            'id' => $preset->id,
            'usage_count' => 1,
        ]);
    }

    public function test_toggle_favorite_toggles_status(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create([
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/presets/{$preset->id}/favorite");

        $response->assertStatus(200)
            ->assertJsonPath('preset.is_favorite', true);

        // Toggle again
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/presets/{$preset->id}/favorite");

        $response->assertStatus(200)
            ->assertJsonPath('preset.is_favorite', false);
    }

    public function test_duplicate_preset_creates_copy(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create([
            'name' => 'Original',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/presets/{$preset->id}/duplicate", [
                'name' => 'Copy of Original',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('preset.name', 'Copy of Original');

        $this->assertDatabaseHas('presets', [
            'user_id' => $this->user->id,
            'name' => 'Copy of Original',
        ]);
    }

    public function test_duplicate_preset_uses_default_name(): void
    {
        $preset = Preset::factory()->for($this->user, 'user')->create([
            'name' => 'Original',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/presets/{$preset->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('preset.name', 'Original (Copy)');
    }

    public function test_get_preset_categories_returns_categories(): void
    {
        Preset::factory()->for($this->user, 'user')->create(['category' => 'vid2vid']);
        Preset::factory()->for($this->user, 'user')->create(['category' => 'deforum']);
        Preset::factory()->for($this->user, 'user')->create(['category' => 'vid2vid']); // Duplicate

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/presets/categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'categories');
    }
}
