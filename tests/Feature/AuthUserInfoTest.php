<?php

namespace Tests\Feature;

use App\Constant\UserRoleConstant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUserInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_endpoint_returns_user_role_for_regular_user(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::REGISTERED]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'login',
                    'roles',
                    'userRole',
                    'isAdmin',
                ],
            ])
            ->assertJson([
                'data' => [
                    'userRole' => UserRoleConstant::REGISTERED,
                    'isAdmin' => false,
                ],
            ]);
    }

    public function test_me_endpoint_returns_user_role_for_administrator(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'userRole' => UserRoleConstant::ADMINISTRATOR,
                    'isAdmin' => true,
                ],
            ]);
    }

    public function test_me_endpoint_returns_user_role_for_super_administrator(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::SUPER_ADMINISTRATOR]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'userRole' => UserRoleConstant::SUPER_ADMINISTRATOR,
                    'isAdmin' => true,
                ],
            ]);
    }

    public function test_me_endpoint_handles_user_without_role(): void
    {
        $user = User::factory()->create(['user_role_id' => null]);

        $response = $this->actingAs($user, 'api')->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'userRole' => null,
                    'isAdmin' => false,
                ],
            ]);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_v2_me_endpoint_includes_role_information(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/me');

        $response->assertOk();
        
        // V2 uses JSON:API format, check that user data includes role info
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        
        // Verify the role relationship is present
        $this->assertArrayHasKey('relationships', $responseData['data']);
        $this->assertArrayHasKey('role', $responseData['data']['relationships']);
    }
}

