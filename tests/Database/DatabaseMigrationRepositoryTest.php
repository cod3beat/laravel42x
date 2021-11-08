<?php

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseMigrationRepositoryTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testGetRanMigrationsListMigrationsByPackage()
    {
        $repo = $this->getRepository();
        $query = m::mock('stdClass');
        $connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
		$query->shouldReceive('lists')->once()->with('migration')->andReturn('bar');

		$this->assertEquals('bar', $repo->getRan());
	}


	public function testGetLastMigrationsGetsAllMigrationsWithTheLatestBatchNumber()
	{
		$repo = $this->getMock(DatabaseMigrationRepository::class, array('getLastBatchNumber'), array(
			$resolver = m::mock(ConnectionResolverInterface::class), 'migrations'
		));
		$repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);
		$query = m::mock('stdClass');
		$connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
		$query->shouldReceive('where')->once()->with('batch', 1)->andReturn($query);
		$query->shouldReceive('orderBy')->once()->with('migration', 'desc')->andReturn($query);
		$query->shouldReceive('get')->once()->andReturn('foo');

		$this->assertEquals('foo', $repo->getLast());
	}


	public function testLogMethodInsertsRecordIntoMigrationTable()
	{
		$repo = $this->getRepository();
		$query = m::mock('stdClass');
		$connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array('migration' => 'bar', 'batch' => 1));

		$repo->log('bar', 1);
	}


	public function testDeleteMethodRemovesAMigrationFromTheTable()
	{
		$repo = $this->getRepository();
		$query = m::mock('stdClass');
		$connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
		$query->shouldReceive('where')->once()->with('migration', 'foo')->andReturn($query);
		$query->shouldReceive('delete')->once();
		$migration = (object) array('migration' => 'foo');

		$repo->delete($migration);
	}


	public function testGetNextBatchNumberReturnsLastBatchNumberPlusOne()
	{
		$repo = $this->getMock(DatabaseMigrationRepository::class, array('getLastBatchNumber'), array(
			m::mock(ConnectionResolverInterface::class), 'migrations'
		));
		$repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);

		$this->assertEquals(2, $repo->getNextBatchNumber());
	}


	public function testGetLastBatchNumberReturnsMaxBatch()
	{
		$repo = $this->getRepository();
		$query = m::mock('stdClass');
		$connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
		$query->shouldReceive('max')->once()->andReturn(1);

		$this->assertEquals(1, $repo->getLastBatchNumber());
	}


	public function testCreateRepositoryCreatesProperDatabaseTable()
	{
		$repo = $this->getRepository();
		$schema = m::mock('stdClass');
		$connectionMock = m::mock(Connection::class);
		$repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
		$repo->getConnection()->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
		$schema->shouldReceive('create')->once()->with('migrations', m::type('Closure'));

		$repo->createRepository();
	}


	protected function getRepository()
	{
		return new DatabaseMigrationRepository(m::mock(ConnectionResolverInterface::class), 'migrations');
	}

}
