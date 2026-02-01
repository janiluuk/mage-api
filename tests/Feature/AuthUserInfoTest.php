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

    // V1 /api/auth/me endpoint removed - using V2 /api/v2/me instead
    // These tests are now covered by test_v2_me_endpoint_includes_role_information below

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
        
        // Verify role is included in the response
        $this->assertArrayHasKey('included', $responseData);
        $this->assertNotEmpty($responseData['included']);
        $roleIncluded = collect($responseData['included'])->firstWhere('type', 'roles');
        $this->assertNotNull($roleIncluded);
        $this->assertEquals(UserRoleConstant::ADMINISTRATOR, $roleIncluded['attributes']['type']);
    }

    public function test_v2_me_endpoint_returns_user_role_for_regular_user(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::REGISTERED]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/me');

        $response->assertOk();
        
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('relationships', $responseData['data']);
        
        if ($responseData['data']['relationships']['role']['data']) {
            $roleIncluded = collect($responseData['included'] ?? [])->firstWhere('id', $responseData['data']['relationships']['role']['data']['id']);
            if ($roleIncluded) {
                $this->assertEquals(UserRoleConstant::REGISTERED, $roleIncluded['attributes']['type']);
            }
        }
    }

    public function test_v2_me_endpoint_handles_user_without_role(): void
    {
        $user = User::factory()->create(['user_role_id' => null]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/me');

        $response->assertOk();
        
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('relationships', $responseData['data']);
        $this->assertNull($responseData['data']['relationships']['role']['data']);
    }

    public function test_v2_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/me');

        $response->assertStatus(401);
    }
}

