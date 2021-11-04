<?php

use Illuminate\Cache\NullStore;
use L4\Tests\BackwardCompatibleTestCase;

class CacheNullStoreTest extends BackwardCompatibleTestCase {

	public function testItemsCanNotBeCached()
	{
		$store = new NullStore;
		$store->put('foo', 'bar', 10);
		$this->assertNull($store->get('foo'));
	}

}
