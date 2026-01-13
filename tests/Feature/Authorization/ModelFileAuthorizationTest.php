<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;
use App\Models\User;
use App\Models\ModelFile;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for ModelFile Authorization
 * 
 * These tests verify that model file access is properly restricted
 * with admin-only write access and public read access.
 */
class ModelFileAuthorizationTest extends TestCase
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
        if (!Role::where('name', 'administrator')->exists()) {
            Role::create(['name' => 'administrator', 'guard_name' => 'api']);
        }
        if (!Role::where('name', 'user')->exists()) {
            Role::create(['name' => 'user', 'guard_name' => 'api']);
        }
    }

    public function test_model_file_authorization_exists(): void
    {
        $authorizerClass = \App\JsonApi\Authorizers\ModelFileAuthorizer::class;
        $this->assertTrue(class_exists($authorizerClass));
    }

    public function test_model_file_authorizer_implements_interface(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $this->assertInstanceOf(\LaravelJsonApi\Contracts\Auth\Authorizer::class, $authorizer);
    }

    public function test_model_file_index_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $request = new \Illuminate\Http\Request();
        
        $result = $authorizer->index($request, ModelFile::class);
        $this->assertTrue($result);
    }

    public function test_model_file_show_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $request = new \Illuminate\Http\Request();
        $modelFile = new ModelFile(['name' => 'Test Model']);
        
        $result = $authorizer->show($request, $modelFile);
        $this->assertTrue($result);
    }

    public function test_non_admin_cannot_create_model_file(): void
    {
        $user = User::factory()->create();
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->store($request, ModelFile::class);
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_update_model_file(): void
    {
        $user = User::factory()->create();
        $modelFile = new ModelFile(['name' => 'Test Model']);
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->update($request, $modelFile);
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_delete_model_file(): void
    {
        $user = User::factory()->create();
        $modelFile = new ModelFile(['name' => 'Test Model']);
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->destroy($request, $modelFile);
        $this->assertFalse($result);
    }

    public function test_unauthenticated_user_cannot_create_model_file(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $request = new \Illuminate\Http\Request();
        
        $result = $authorizer->store($request, ModelFile::class);
        $this->assertFalse($result);
    }

    public function test_show_related_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $request = new \Illuminate\Http\Request();
        $modelFile = new ModelFile(['name' => 'Test']);
        
        $result = $authorizer->showRelated($request, $modelFile, 'some_relation');
        $this->assertTrue($result);
    }

    public function test_show_relationship_allows_public_access(): void
    {
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        $request = new \Illuminate\Http\Request();
        $modelFile = new ModelFile(['name' => 'Test']);
        
        $result = $authorizer->showRelationship($request, $modelFile, 'some_relation');
        $this->assertTrue($result);
    }

    public function test_non_admin_cannot_update_relationships(): void
    {
        $user = User::factory()->create();
        $modelFile = new ModelFile(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->updateRelationship($request, $modelFile, 'some_relation');
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_attach_relationships(): void
    {
        $user = User::factory()->create();
        $modelFile = new ModelFile(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->attachRelationship($request, $modelFile, 'some_relation');
        $this->assertFalse($result);
    }

    public function test_non_admin_cannot_detach_relationships(): void
    {
        $user = User::factory()->create();
        $modelFile = new ModelFile(['name' => 'Test']);
        $authorizer = new \App\JsonApi\Authorizers\ModelFileAuthorizer();
        
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(fn() => $user);
        
        $result = $authorizer->detachRelationship($request, $modelFile, 'some_relation');
        $this->assertFalse($result);
    }
}
