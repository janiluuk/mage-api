<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\UserRole;
use App\Constant\UserRoleConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_user_can_upload_to_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/uploads/users/{$user->id}/profile-image", [
                'attachment' => UploadedFile::fake()->image('avatar.jpg')
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['url']);
    }

    public function test_user_cannot_upload_to_another_users_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/uploads/users/{$otherUser->id}/profile-image", [
                'attachment' => UploadedFile::fake()->image('avatar.jpg')
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have permission to upload files to this resource.']);
    }

    public function test_user_can_upload_to_own_item(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/uploads/items/{$item->id}/image", [
                'attachment' => UploadedFile::fake()->image('item.jpg')
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['url']);
    }

    public function test_user_cannot_upload_to_another_users_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/uploads/items/{$item->id}/image", [
                'attachment' => UploadedFile::fake()->image('item.jpg')
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have permission to upload files to this resource.']);
    }

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $response = $this->postJson("/api/v1/uploads/users/1/profile-image", [
            'attachment' => UploadedFile::fake()->image('avatar.jpg')
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_to_disallowed_path_returns_400(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/uploads/invalid-resource/{$user->id}/field", [
                'attachment' => UploadedFile::fake()->image('file.jpg')
            ]);

        $response->assertStatus(400);
    }
}
