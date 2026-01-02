<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;
use App\Models\User;
use App\Models\Generator;
use App\Models\Role;
use App\Constant\UserRoleConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for Generator Authorization
 * 
 * These tests verify that generator access is properly restricted
 * with admin-only write access and public read access.
 */
class GeneratorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles if needed
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        // Create basic roles if they don't exist
        if (!Role::where('name', 'administrator')->exists()) {
            Role::create(['name' => 'administrator', 'guard_name' => 'api']);
        }
        if (!Role::where('name', 'user')->exists()) {
            Role::create(['name' => 'user', 'guard_name' => 'api']);
        }
    }

    public function test_generator_authorization_exists(): void
    {
        $authorizerClass = \App\JsonApi\Authorizers\GeneratorAuthorizer::class;
        $this->assertTrue(class_exists($authorizerClass));
    }

    public function test_generator_authorizer_implements_interface(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $this->assertInstanceOf(\LaravelJsonApi\Contracts\Auth\Authorizer::class, $authorizer);
    }

    public function test_generator_index_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $request = new \Illuminate\Http\Request();
        
        $result = $authorizer->index($request, Generator::class);
        $this->assertTrue($result);
    }

    public function test_generator_show_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $request = new \Illuminate\Http\Request();
        $generator = new Generator(['name' => 'Test Generator']);
        
        $result = $authorizer->show($request, $generator);
        $this->assertTrue($result);
    }

    public function test_non_admin_cannot_create_generator(): void
    {
        $user = User::factory()->create();
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->store($request, Generator::class);
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_update_generator(): void
    {
        $user = User::factory()->create();
        $generator = new Generator(['name' => 'Test Generator']);
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->update($request, $generator);
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_delete_generator(): void
    {
        $user = User::factory()->create();
        $generator = new Generator(['name' => 'Test Generator']);
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->destroy($request, $generator);
        $this->assertFalse($result);
    }

    public function test_unauthenticated_user_cannot_create_generator(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $request = new \Illuminate\Http\Request();
        
        $result = $authorizer->store($request, Generator::class);
        $this->assertFalse($result);
    }

    public function test_show_related_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $request = new \Illuminate\Http\Request();
        $generator = new Generator(['name' => 'Test']);
        
        $result = $authorizer->showRelated($request, $generator, 'some_relation');
        $this->assertTrue($result);
    }

    public function test_show_relationship_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        $request = new \Illuminate\Http\Request();
        $generator = new Generator(['name' => 'Test']);
        
        $result = $authorizer->showRelationship($request, $generator, 'some_relation');
        $this->assertTrue($result);
    }

    public function test_non_admin_cannot_update_relationships(): void
    {
        $user = User::factory()->create();
        $generator = new Generator(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->updateRelationship($request, $generator, 'some_relation');
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_attach_relationships(): void
    {
        $user = User::factory()->create();
        $generator = new Generator(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->attachRelationship($request, $generator, 'some_relation');
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_detach_relationships(): void
    {
        $user = User::factory()->create();
        $generator = new Generator(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\GeneratorAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->detachRelationship($request, $generator, 'some_relation');
        $this->assertFalse($result);
    }
}
