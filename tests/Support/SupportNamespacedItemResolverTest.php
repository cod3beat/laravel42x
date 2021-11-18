<?php

use Illuminate\Support\NamespacedItemResolver;
use L4\Tests\BackwardCompatibleTestCase;

class SupportNamespacedItemResolverTest extends BackwardCompatibleTestCase {

	public function testResolution()
	{
		$r = new NamespacedItemResolver;

		$this->assertEquals(['foo', 'bar', 'baz'], $r->parseKey('foo::bar.baz'));
		$this->assertEquals(['foo', 'bar', null], $r->parseKey('foo::bar'));
		$this->assertEquals([null, 'bar', 'baz'], $r->parseKey('bar.baz'));
		$this->assertEquals([null, 'bar', null], $r->parseKey('bar'));
	}


	public function testParsedItemsAreCached()
	{
		$r = $this->getMock(NamespacedItemResolver::class, ['parseBasicSegments', 'parseNamespacedSegments']);
		$r->setParsedKey('foo.bar', ['foo']);
		$r->expects($this->never())->method('parseBasicSegments');
		$r->expects($this->never())->method('parseNamespacedSegments');

		$this->assertEquals(['foo'], $r->parseKey('foo.bar'));
	}

}
