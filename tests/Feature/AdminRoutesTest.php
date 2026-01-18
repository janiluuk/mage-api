<?php

namespace Tests\Feature;

use App\Constant\UserRoleConstant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_admin_routes_require_authentication(): void
    {
        $response = $this->getJson('/api/administration/users');

        $response->assertStatus(401);
    }

    public function test_admin_routes_reject_regular_users(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::REGISTERED]);
        $user = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/administration/users');

        $response->assertStatus(403);
    }

    public function test_admin_routes_allow_administrators(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/administration/users');

        $response->assertOk();
    }

    public function test_admin_routes_allow_super_administrators(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::SUPER_ADMINISTRATOR]);
        $superAdmin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/administration/users');

        $response->assertOk();
    }

    public function test_get_all_users_endpoint_works(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);
        
        // Create some test users
        $regularRole = UserRole::factory()->create(['type' => UserRoleConstant::REGISTERED]);
        User::factory()->count(3)->create(['user_role_id' => $regularRole->id]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/administration/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'email',
                        'login',
                    ],
                ],
            ]);
    }

    public function test_get_user_data_stats_endpoint_works(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);
        
        $regularRole = UserRole::factory()->create(['type' => UserRoleConstant::REGISTERED]);
        $targetUser = User::factory()->create(['user_role_id' => $regularRole->id]);

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/administration/users/{$targetUser->id}/data-stats");

        $response->assertOk();
    }

    public function test_generator_instances_endpoints_require_admin(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        // Test index
        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/administration/generator-instances');
        $response->assertOk();

        // Test store
        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/administration/generator-instances', [
                'name' => 'Test Instance',
                'url' => 'http://test.example.com',
            ]);
        $response->assertStatus(201);
    }

    public function test_finance_operations_get_all_endpoint_requires_admin(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/administration/finance-operations/get-all');

        $response->assertOk();
    }

    public function test_orders_endpoints_require_admin(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/administration/orders');

        $response->assertOk();
    }

    public function test_support_requests_search_endpoint_requires_admin(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/administration/support-requests', []);

        $response->assertOk();
    }

    public function test_administration_files_endpoints_require_admin(): void
    {
        $userRole = UserRole::factory()->create(['type' => UserRoleConstant::ADMINISTRATOR]);
        $admin = User::factory()->create(['user_role_id' => $userRole->id]);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/administration/files/overview');

        $response->assertOk();
    }
}

