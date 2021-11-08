<?php

use Illuminate\Foundation\AliasLoader;
use L4\Tests\BackwardCompatibleTestCase;

class FoundationAliasLoaderTest extends BackwardCompatibleTestCase {

	public function testLoaderCanBeCreatedAndRegisteredOnce()
	{
		$loader = $this->getMock(AliasLoader::class, ['prependToLoaderStack'], [['foo' => 'bar']]);
		$loader->expects($this->once())->method('prependToLoaderStack');

		$this->assertEquals(['foo' => 'bar'], $loader->getAliases());
		$this->assertFalse($loader->isRegistered());
		$loader->register();

		$this->assertTrue($loader->isRegistered());
	}


	public function testGetInstanceCreatesOneInstance()
	{
		$loader = AliasLoader::getInstance(['foo' => 'bar']);
		$this->assertEquals($loader, AliasLoader::getInstance());
	}

}
