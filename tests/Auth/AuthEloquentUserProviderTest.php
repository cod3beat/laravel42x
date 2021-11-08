<?php

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\UserInterface;
use Illuminate\Database\Connection;
use Illuminate\Hashing\HasherInterface;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class AuthEloquentUserProviderTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testRetrieveByIDReturnsUser()
    {
        $provider = $this->getProviderMock();
        $mock = m::mock('stdClass');
        $mock->shouldReceive('newQuery')->once()->andReturn($mock);
		$mock->shouldReceive('find')->once()->with(1)->andReturn('bar');
		$provider->expects($this->once())->method('createModel')->willReturn($mock);
		$user = $provider->retrieveByID(1);

		$this->assertEquals('bar', $user);
	}


	public function testRetrieveByCredentialsReturnsUser()
	{
		$provider = $this->getProviderMock();
		$mock = m::mock('stdClass');
		$mock->shouldReceive('newQuery')->once()->andReturn($mock);
		$mock->shouldReceive('where')->once()->with('username', 'dayle');
		$mock->shouldReceive('first')->once()->andReturn('bar');
		$provider->expects($this->once())->method('createModel')->willReturn($mock);
		$user = $provider->retrieveByCredentials(array('username' => 'dayle', 'password' => 'foo'));

		$this->assertEquals('bar', $user);
	}


	public function testCredentialValidation()
	{
		$conn = m::mock(Connection::class);
		$hasher = m::mock(HasherInterface::class);
		$hasher->shouldReceive('check')->once()->with('plain', 'hash')->andReturn(true);
		$provider = new Illuminate\Auth\EloquentUserProvider($hasher, 'foo');
		$user = m::mock(UserInterface::class);
		$user->shouldReceive('getAuthPassword')->once()->andReturn('hash');
		$result = $provider->validateCredentials($user, array('password' => 'plain'));

		$this->assertTrue($result);
	}


	public function testModelsCanBeCreated()
	{
		$conn = m::mock(Connection::class);
		$hasher = m::mock(HasherInterface::class);
		$provider = new Illuminate\Auth\EloquentUserProvider($hasher, 'EloquentProviderUserStub');
		$model = $provider->createModel();

		$this->assertInstanceOf('EloquentProviderUserStub', $model);
	}


	protected function getProviderMock()
	{
		$hasher = m::mock(HasherInterface::class);
		return $this->getMockBuilder(EloquentUserProvider::class)
            ->setMethods(array('createModel'))
            ->setConstructorArgs(array($hasher, 'foo'))
            ->getMock();
	}

}

class EloquentProviderUserStub {}
