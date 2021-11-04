<?php

use Illuminate\Foundation\AliasLoader;

class FoundationAliasLoaderTest extends \L4\Tests\BackwardCompatibleTestCase {

	public function testLoaderCanBeCreatedAndRegisteredOnce()
	{
		$loader = $this->getMock(AliasLoader::class, array('prependToLoaderStack'), array(array('foo' => 'bar')));
		$loader->expects($this->once())->method('prependToLoaderStack');

		$this->assertEquals(array('foo' => 'bar'), $loader->getAliases());
		$this->assertFalse($loader->isRegistered());
		$loader->register();

		$this->assertTrue($loader->isRegistered());
	}


	public function testGetInstanceCreatesOneInstance()
	{
		$loader = AliasLoader::getInstance(array('foo' => 'bar'));
		$this->assertEquals($loader, AliasLoader::getInstance());
	}

}
