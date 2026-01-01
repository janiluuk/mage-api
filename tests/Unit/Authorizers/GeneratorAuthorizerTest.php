<?php

namespace Tests\Unit\Authorizers;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\JsonApi\Authorizers\GeneratorAuthorizer;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Generator;
use App\Constant\UserRoleConstant;
use Mockery;

class GeneratorAuthorizerTest extends TestCase
{
    private GeneratorAuthorizer $authorizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizer = new GeneratorAuthorizer();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_allows_public_access(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn(null);

        $result = $this->authorizer->index($request, Generator::class);

        $this->assertTrue($result);
    }

    public function test_show_allows_public_access(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn(null);
        
        $generator = new Generator();

        $result = $this->authorizer->show($request, $generator);

        $this->assertTrue($result);
    }

    public function test_store_denies_non_admin_users(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::REGISTERED);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $result = $this->authorizer->store($request, Generator::class);

        $this->assertFalse($result);
    }

    public function test_store_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $result = $this->authorizer->store($request, Generator::class);

        $this->assertTrue($result);
    }

    public function test_store_allows_super_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::SUPER_ADMINISTRATOR);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $result = $this->authorizer->store($request, Generator::class);

        $this->assertTrue($result);
    }

    public function test_store_denies_unauthenticated_users(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn(null);

        $result = $this->authorizer->store($request, Generator::class);

        $this->assertFalse($result);
    }

    public function test_update_denies_non_admin_users(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::REGISTERED);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $generator = new Generator();
        $result = $this->authorizer->update($request, $generator);

        $this->assertFalse($result);
    }

    public function test_update_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $generator = new Generator();
        $result = $this->authorizer->update($request, $generator);

        $this->assertTrue($result);
    }

    public function test_destroy_denies_non_admin_users(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::REGISTERED);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $generator = new Generator();
        $result = $this->authorizer->destroy($request, $generator);

        $this->assertFalse($result);
    }

    public function test_destroy_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $generator = new Generator();
        $result = $this->authorizer->destroy($request, $generator);

        $this->assertTrue($result);
    }

    public function test_admin_actions_deny_users_without_role(): void
    {
        $user = Mockery::mock(User::class);
        $user->userRole = null;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $generator = new Generator();

        $this->assertFalse($this->authorizer->store($request, Generator::class));
        $this->assertFalse($this->authorizer->update($request, $generator));
        $this->assertFalse($this->authorizer->destroy($request, $generator));
    }
}
