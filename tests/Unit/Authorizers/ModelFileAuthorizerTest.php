<?php

namespace Tests\Unit\Authorizers;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\JsonApi\Authorizers\ModelFileAuthorizer;
use App\Models\User;
use App\Models\UserRole;
use App\Models\ModelFile;
use App\Constant\UserRoleConstant;
use Mockery;

class ModelFileAuthorizerTest extends TestCase
{
    private ModelFileAuthorizer $authorizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizer = new ModelFileAuthorizer();
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

        $result = $this->authorizer->index($request, ModelFile::class);

        $this->assertTrue($result);
    }

    public function test_show_allows_public_access(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn(null);
        
        $modelFile = new ModelFile();

        $result = $this->authorizer->show($request, $modelFile);

        $this->assertTrue($result);
    }

    public function test_store_denies_non_admin_users(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::REGISTERED);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('userRole')->andReturn($userRole);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $result = $this->authorizer->store($request, ModelFile::class);

        $this->assertFalse($result);
    }

    public function test_store_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('userRole')->andReturn($userRole);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $result = $this->authorizer->store($request, ModelFile::class);

        $this->assertTrue($result);
    }

    public function test_update_denies_non_admin_users(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::REGISTERED);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('userRole')->andReturn($userRole);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $modelFile = new ModelFile();
        $result = $this->authorizer->update($request, $modelFile);

        $this->assertFalse($result);
    }

    public function test_update_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('userRole')->andReturn($userRole);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $modelFile = new ModelFile();
        $result = $this->authorizer->update($request, $modelFile);

        $this->assertTrue($result);
    }

    public function test_destroy_allows_administrators(): void
    {
        $userRole = Mockery::mock(UserRole::class);
        $userRole->shouldReceive('getType')->andReturn(UserRoleConstant::ADMINISTRATOR);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('userRole')->andReturn($userRole);
        $user->userRole = $userRole;

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $modelFile = new ModelFile();
        $result = $this->authorizer->destroy($request, $modelFile);

        $this->assertTrue($result);
    }
}
