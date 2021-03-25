<?php

use Illuminate\Container\Container;
use Illuminate\Support\Traits\CapsuleManagerTrait;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class SupportCapsuleManagerTraitTest extends BackwardCompatibleTestCase
{

    use CapsuleManagerTrait;

    protected function tearDown(): void
    {
        m::close();
    }

    public function testSetupContainerForCapsule()
    {
        $this->container = null;
        $app = new Container;

        $this->assertNull($this->setupContainer($app));
		$this->assertEquals($app, $this->getContainer());
		$this->assertInstanceOf('\Illuminate\Support\Fluent', $app['config']);
	}


	public function testSetupContainerForCapsuleWhenConfigIsBound()
	{
		$this->container = null;
		$app = new Container;
		$app['config'] = m::mock('\Illuminate\Config\Repository');

		$this->assertNull($this->setupContainer($app));
		$this->assertEquals($app, $this->getContainer());
		$this->assertInstanceOf('\Illuminate\Config\Repository', $app['config']);
	}
}
