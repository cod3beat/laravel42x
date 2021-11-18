<?php

use L4\Tests\BackwardCompatibleTestCase;

class ViewEngineResolverTest extends BackwardCompatibleTestCase {

	public function testResolversMayBeResolved()
	{
		$resolver = new Illuminate\View\Engines\EngineResolver;
		$resolver->register('foo', function() { return new StdClass; });
		$result = $resolver->resolve('foo');

		$this->assertEquals(spl_object_hash($result), spl_object_hash($resolver->resolve('foo')));
	}


	public function testResolverThrowsExceptionOnUnknownEngine()
	{
		$this->expectException('InvalidArgumentException');
		$resolver = new Illuminate\View\Engines\EngineResolver;
		$resolver->resolve('foo');
	}

}
