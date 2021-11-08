<?php

use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SessionMiddlewareTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testSessionIsProperlyStartedAndClosed()
    {
        $request = Symfony\Component\HttpFoundation\Request::create('/', 'GET');
        $response = new Symfony\Component\HttpFoundation\Response;

		$middle = new Illuminate\Session\Middleware(
			$app = m::mock(HttpKernelInterface::class),
			$manager = m::mock(SessionManager::class)
		);

		$manager->shouldReceive('getSessionConfig')->andReturn(array(
			'driver' => 'file',
			'lottery' => array(100, 100),
			'path' => '/',
			'domain' => null,
			'lifetime' => 120,
			'expire_on_close' => false,
		));

		$manager->shouldReceive('driver')->andReturn($driver = m::mock(Store::class)->makePartial());
		$driver->shouldReceive('setRequestOnHandler')->once()->with($request);
		$driver->shouldReceive('start')->once();
		$app->shouldReceive('handle')->once()->with($request, Symfony\Component\HttpKernel\HttpKernelInterface::MASTER_REQUEST, true)->andReturn($response);
		$driver->shouldReceive('save')->once();
		$driver->shouldReceive('getHandler')->andReturn($handler = m::mock('StdClass'));
		$handler->shouldReceive('gc')->once()->with(120 * 60);
		$driver->shouldReceive('getName')->andReturn('name');
		$driver->shouldReceive('getId')->andReturn(1);

		$middleResponse = $middle->handle($request);

		$this->assertSame($response, $middleResponse);
		$this->assertEquals(1, head($response->headers->getCookies())->getValue());
	}


	public function testSessionIsNotUsedWhenNoDriver()
	{
		$request = Symfony\Component\HttpFoundation\Request::create('/', 'GET');
		$response = new Symfony\Component\HttpFoundation\Response;
		$middle = new Illuminate\Session\Middleware(
			$app = m::mock(HttpKernelInterface::class),
			$manager = m::mock(SessionManager::class)
		);
		$manager->shouldReceive('getSessionConfig')->andReturn(array(
			'driver' => null,
		));
		$app->shouldReceive('handle')->once()->with($request, Symfony\Component\HttpKernel\HttpKernelInterface::MASTER_REQUEST, true)->andReturn($response);
		$middleResponse = $middle->handle($request);

		$this->assertSame($response, $middleResponse);
	}


	public function testCheckingForRequestUsingArraySessions()
	{
		$middleware = new Illuminate\Session\Middleware(
			m::mock(HttpKernelInterface::class),
			$manager = m::mock(SessionManager::class),
			function() { return true; }
		);

		$manager->shouldReceive('setDefaultDriver')->once()->with('array');

		$middleware->checkRequestForArraySessions(new Symfony\Component\HttpFoundation\Request);
	}

}
