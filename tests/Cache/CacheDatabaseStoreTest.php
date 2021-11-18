<?php

use Illuminate\Cache\DatabaseStore;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class CacheDatabaseStoreTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testNullIsReturnedWhenItemNotFound()
    {
        $store = $this->getStore();
        $table = m::mock('StdClass');
        $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$table->shouldReceive('where')->once()->with('key', '=', 'prefixfoo')->andReturn($table);
		$table->shouldReceive('first')->once()->andReturn(null);

		$this->assertNull($store->get('foo'));
	}


	public function testNullIsReturnedAndItemDeletedWhenItemIsExpired()
	{
		$store = $this->getMock(DatabaseStore::class, ['forget'], $this->getMocks());
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$table->shouldReceive('where')->once()->with('key', '=', 'prefixfoo')->andReturn($table);
		$table->shouldReceive('first')->once()->andReturn((object) ['expiration' => 1]);
		$store->expects($this->once())->method('forget')->with($this->equalTo('foo'))->willReturn(null);

		$this->assertNull($store->get('foo'));
	}


	public function testDecryptedValueIsReturnedWhenItemIsValid()
	{
		$store = $this->getStore();
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$table->shouldReceive('where')->once()->with('key', '=', 'prefixfoo')->andReturn($table);
		$table->shouldReceive('first')->once()->andReturn((object) ['value' => 'bar', 'expiration' => 999999999999999]);
		$store->getEncrypter()->shouldReceive('decrypt')->once()->with('bar')->andReturn('bar');

		$this->assertEquals('bar', $store->get('foo'));
	}


	public function testEncryptedValueIsInsertedWhenNoExceptionsAreThrown()
	{
		$store = $this->getMock(DatabaseStore::class, ['getTime'], $this->getMocks());
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$store->getEncrypter()->shouldReceive('encrypt')->once()->with('bar')->andReturn('bar');
		$store->expects($this->once())->method('getTime')->willReturn(1);
		$table->shouldReceive('insert')->once()->with(['key' => 'prefixfoo', 'value' => 'bar', 'expiration' => 61]);

		$store->put('foo', 'bar', 1);
	}


	public function testEncryptedValueIsUpdatedWhenInsertThrowsException()
	{
		$store = $this->getMock(DatabaseStore::class, ['getTime'], $this->getMocks());
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->with('table')->andReturn($table);
		$store->getEncrypter()->shouldReceive('encrypt')->once()->with('bar')->andReturn('bar');
		$store->expects($this->once())->method('getTime')->willReturn(1);
		$table->shouldReceive('insert')->once()->with(['key' => 'prefixfoo', 'value' => 'bar', 'expiration' => 61])->andReturnUsing(function()
		{
			throw new Exception;
		});
		$table->shouldReceive('where')->once()->with('key', '=', 'prefixfoo')->andReturn($table);
		$table->shouldReceive('update')->once()->with(['value' => 'bar', 'expiration' => 61]);

		$store->put('foo', 'bar', 1);
	}


	public function testForeverCallsStoreItemWithReallyLongTime()
	{
		$store = $this->getMock(DatabaseStore::class, ['put'], $this->getMocks());
		$store->expects($this->once())->method('put')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(5256000));
		$store->forever('foo', 'bar');
	}


	public function testItemsMayBeRemovedFromCache()
	{
		$store = $this->getStore();
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$table->shouldReceive('where')->once()->with('key', '=', 'prefixfoo')->andReturn($table);
		$table->shouldReceive('delete')->once();

		$store->forget('foo');
	}


	public function testItemsMayBeFlushedFromCache()
	{
		$store = $this->getStore();
		$table = m::mock('StdClass');
		$store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
		$table->shouldReceive('delete')->once();

		$store->flush();
	}


	protected function getStore()
	{
		return new DatabaseStore(m::mock(Connection::class), m::mock(
            Encrypter::class
        ), 'table', 'prefix');
	}


	protected function getMocks()
	{
		return [m::mock(Connection::class), m::mock(Encrypter::class), 'table', 'prefix'];
	}

}
