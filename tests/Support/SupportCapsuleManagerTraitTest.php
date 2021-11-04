<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Fluent;
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
		$this->assertInstanceOf(Fluent::class, $app['config']);
	}


	public function testSetupContainerForCapsuleWhenConfigIsBound()
	{
		$this->container = null;
		$app = new Container;
		$app['config'] = m::mock(Repository::class);

		$this->assertNull($this->setupContainer($app));
		$this->assertEquals($app, $this->getContainer());
		$this->assertInstanceOf(Repository::class, $app['config']);
	}
}
