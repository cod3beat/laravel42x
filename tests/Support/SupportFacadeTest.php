<?php

use Illuminate\Support\Facades\Facade;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Mockery\MockInterface;

class SupportFacadeTest extends BackwardCompatibleTestCase
{
    protected function setUp(): void
    {
        Facade::clearResolvedInstances();
        FacadeStub::setFacadeApplication(null);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testFacadeCallsUnderlyingApplication()
    {
        $app = new ApplicationStub;
        $app->setAttributes(array('foo' => $mock = m::mock('StdClass')));
        $mock->shouldReceive('bar')->once()->andReturn('baz');
		FacadeStub::setFacadeApplication($app);
		$this->assertEquals('baz', FacadeStub::bar());
	}

	public function testShouldReceiveReturnsAMockeryMock()
	{
		$app = new ApplicationStub;
		$app->setAttributes(array('foo' => new stdClass));
		FacadeStub::setFacadeApplication($app);

		$this->assertInstanceOf(MockInterface::class, $mock = FacadeStub::shouldReceive('foo')->once()->with('bar')->andReturn('baz')->getMock());
		$this->assertEquals('baz', $app['foo']->foo('bar'));
	}

	public function testShouldReceiveCanBeCalledTwice()
	{
		$app = new ApplicationStub;
		$app->setAttributes(array('foo' => new stdClass));
		FacadeStub::setFacadeApplication($app);

		$this->assertInstanceOf(MockInterface::class, $mock = FacadeStub::shouldReceive('foo')->once()->with('bar')->andReturn('baz')->getMock());
		$this->assertInstanceOf(MockInterface::class, $mock = FacadeStub::shouldReceive('foo2')->once()->with('bar2')->andReturn('baz2')->getMock());
		$this->assertEquals('baz', $app['foo']->foo('bar'));
		$this->assertEquals('baz2', $app['foo']->foo2('bar2'));
	}

	public function testCanBeMockedWithoutUnderlyingInstance()
	{
		FacadeStub::shouldReceive('foo')->once()->andReturn('bar');
		$this->assertEquals('bar', FacadeStub::foo());
	}
}

class FacadeStub extends Facade {
	protected static function getFacadeAccessor()
	{
		return 'foo';
	}
}

class ApplicationStub implements ArrayAccess
{
	protected array $attributes = array();

	public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
    }

	public function instance($key, $instance): void
    {
        $this->attributes[$key] = $instance;
    }

	public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

	public function offsetGet($key)
    {
        return $this->attributes[$key];
    }

	public function offsetSet($key, $value)
    {
        $this->attributes[$key] = $value;
    }

	public function offsetUnset($key)
    {
        unset($this->attributes[$key]);
    }
}
