<?php

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class RoutingControllerDispatcherTest extends BackwardCompatibleTestCase
{

    public function setUp()
    {
        $_SERVER['ControllerDispatcherTestControllerStub'] = null;
    }


    protected function tearDown(): void
    {
        unset($_SERVER['ControllerDispatcherTestControllerStub']);
        m::close();
    }


    public function testBasicDispatchToMethod()
    {
        $request = Request::create('controller');
        $route = new Route(
            array('GET'), 'controller', array(
            'uses' => function () {
            }
        )
        );
		$route->bind($request);
		$dispatcher = new ControllerDispatcher(m::mock('Illuminate\Routing\RouteFiltererInterface'), new Container);

		$this->assertNull($_SERVER['ControllerDispatcherTestControllerStub']);

		$response = $dispatcher->dispatch($route, $request, 'ControllerDispatcherTestControllerStub', 'getIndex');
		$this->assertEquals('getIndex', $response);
		$this->assertEquals('setupLayout', $_SERVER['ControllerDispatcherTestControllerStub']);
	}

}


class ControllerDispatcherTestControllerStub extends Controller {

	public function __construct()
	{
		// construct shouldn't affect setupLayout.
	}

	protected function setupLayout()
	{
		$_SERVER['ControllerDispatcherTestControllerStub'] = __FUNCTION__;
	}


	public function getIndex()
	{
		return __FUNCTION__;
	}


	public function getFoo()
	{
		return __FUNCTION__;
	}

}
