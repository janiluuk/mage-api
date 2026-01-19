<?php

namespace Tests\Feature;

use App\Models\GeneratorInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        $this->adminUser->assignRole('administrator');
    }

    public function test_can_get_instance_status_without_authentication(): void
    {
        $response = $this->getJson('/api/administration/instances/status');

        $response->assertStatus(401);
    }

    public function test_can_get_instance_status_as_admin(): void
    {
        $instance = GeneratorInstance::factory()->create([
            'name' => 'Test Instance',
            'type' => 'comfyui',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instances' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'queue_size',
                        'processing_count',
                        'health_status',
                        'ffmpeg',
                ],
                ],
                'summary',
            ]);

        $this->assertCount(1, $response->json('instances'));
        $this->assertEquals('Test Instance', $response->json('instances.0.name'));
    }

    public function test_status_includes_ffmpeg_information(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/administration/instances/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ffmpeg' => [
                    'active_encoding_count',
                    'pending_encoding_count',
                    'total_queue_size',
                    'active_jobs',
                ],
            ]);
    }
}


