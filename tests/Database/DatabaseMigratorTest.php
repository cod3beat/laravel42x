<?php

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseMigratorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testMigrationAreRunUpWhenOutstandingMigrationsExist()
    {
        $migrator = $this->getMock(
            Migrator::class,
            array('resolve'),
            array(
                m::mock(MigrationRepositoryInterface::class),
                $resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getFilesystem()->shouldReceive('glob')->once()->with(__DIR__.'/*_*.php')->andReturn(array(
			__DIR__.'/2_bar.php',
			__DIR__.'/1_foo.php',
			__DIR__.'/3_baz.php',
		));

		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/2_bar.php');
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/1_foo.php');
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/3_baz.php');

		$migrator->getRepository()->shouldReceive('getRan')->once()->andReturn(array(
			'1_foo',
		));
		$migrator->getRepository()->shouldReceive('getNextBatchNumber')->once()->andReturn(1);
		$migrator->getRepository()->shouldReceive('log')->once()->with('2_bar', 1);
		$migrator->getRepository()->shouldReceive('log')->once()->with('3_baz', 1);
		$barMock = m::mock('stdClass');
		$barMock->shouldReceive('up')->once();
		$bazMock = m::mock('stdClass');
		$bazMock->shouldReceive('up')->once();
		$migrator->expects($this->at(0))->method('resolve')->with($this->equalTo('2_bar'))->willReturn($barMock);
		$migrator->expects($this->at(1))->method('resolve')->with($this->equalTo('3_baz'))->willReturn($bazMock);

		$migrator->run(__DIR__);
	}


	public function testUpMigrationCanBePretended()
	{
		$migrator = $this->getMock(Migrator::class, array('resolve'), array(
			m::mock(MigrationRepositoryInterface::class),
			$resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getFilesystem()->shouldReceive('glob')->once()->with(__DIR__.'/*_*.php')->andReturn(array(
			__DIR__.'/2_bar.php',
			__DIR__.'/1_foo.php',
			__DIR__.'/3_baz.php',
		));
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/2_bar.php');
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/1_foo.php');
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/3_baz.php');
		$migrator->getRepository()->shouldReceive('getRan')->once()->andReturn(array(
			'1_foo',
		));
		$migrator->getRepository()->shouldReceive('getNextBatchNumber')->once()->andReturn(1);

		$barMock = m::mock('stdClass');
		$barMock->shouldReceive('getConnection')->once()->andReturn(null);
		$barMock->shouldReceive('up')->once();

		$bazMock = m::mock('stdClass');
		$bazMock->shouldReceive('getConnection')->once()->andReturn(null);
		$bazMock->shouldReceive('up')->once();

		$migrator->expects($this->at(0))->method('resolve')->with($this->equalTo('2_bar'))->willReturn($barMock);
		$migrator->expects($this->at(1))->method('resolve')->with($this->equalTo('3_baz'))->willReturn($bazMock);

		$connection = m::mock('stdClass');
		$connection->shouldReceive('pretend')->with(m::type('Closure'))->andReturnUsing(function($closure)
		{
			$closure();
			return array(array('query' => 'foo'));
		},
		function($closure)
		{
			$closure();
			return array(array('query' => 'bar'));
		});
		$resolver->shouldReceive('connection')->with(null)->andReturn($connection);

		$migrator->run(__DIR__, true);
	}


	public function testNothingIsDoneWhenNoMigrationsAreOutstanding()
	{
		$migrator = $this->getMock(Migrator::class, array('resolve'), array(
			m::mock(MigrationRepositoryInterface::class),
			$resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getFilesystem()->shouldReceive('glob')->once()->with(__DIR__.'/*_*.php')->andReturn(array(
			__DIR__.'/1_foo.php',
		));
		$migrator->getFilesystem()->shouldReceive('requireOnce')->with(__DIR__.'/1_foo.php');
		$migrator->getRepository()->shouldReceive('getRan')->once()->andReturn(array(
			'1_foo',
		));

		$migrator->run(__DIR__);
	}


	public function testLastBatchOfMigrationsCanBeRolledBack()
	{
		$migrator = $this->getMock(Migrator::class, array('resolve'), array(
			m::mock(MigrationRepositoryInterface::class),
			$resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getRepository()->shouldReceive('getLast')->once()->andReturn(array(
			$fooMigration = new MigratorTestMigrationStub('foo'),
			$barMigration = new MigratorTestMigrationStub('bar'),
		));

		$barMock = m::mock('stdClass');
		$barMock->shouldReceive('down')->once();

		$fooMock = m::mock('stdClass');
		$fooMock->shouldReceive('down')->once();

		$migrator->expects($this->at(0))->method('resolve')->with($this->equalTo('foo'))->willReturn($barMock);
		$migrator->expects($this->at(1))->method('resolve')->with($this->equalTo('bar'))->willReturn($fooMock);

		$migrator->getRepository()->shouldReceive('delete')->once()->with($barMigration);
		$migrator->getRepository()->shouldReceive('delete')->once()->with($fooMigration);

		$migrator->rollback();
	}


	public function testRollbackMigrationsCanBePretended()
	{
		$migrator = $this->getMock(Migrator::class, array('resolve'), array(
			m::mock(MigrationRepositoryInterface::class),
			$resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getRepository()->shouldReceive('getLast')->once()->andReturn(array(
			$fooMigration = new MigratorTestMigrationStub('foo'),
			$barMigration = new MigratorTestMigrationStub('bar'),
		));

		$barMock = m::mock('stdClass');
		$barMock->shouldReceive('getConnection')->once()->andReturn(null);
		$barMock->shouldReceive('down')->once();

		$fooMock = m::mock('stdClass');
		$fooMock->shouldReceive('getConnection')->once()->andReturn(null);
		$fooMock->shouldReceive('down')->once();

		$migrator->expects($this->at(0))->method('resolve')->with($this->equalTo('foo'))->willReturn($barMock);
		$migrator->expects($this->at(1))->method('resolve')->with($this->equalTo('bar'))->willReturn($fooMock);

		$connection = m::mock('stdClass');
		$connection->shouldReceive('pretend')->with(m::type('Closure'))->andReturnUsing(function($closure)
		{
			$closure();
			return array(array('query' => 'bar'));
		},
		function($closure)
		{
			$closure();
			return array(array('query' => 'foo'));
		});
		$resolver->shouldReceive('connection')->with(null)->andReturn($connection);

		$migrator->rollback(true);
	}


	public function testNothingIsRolledBackWhenNothingInRepository()
	{
		$migrator = $this->getMock(Migrator::class, array('resolve'), array(
			m::mock(MigrationRepositoryInterface::class),
			$resolver = m::mock(ConnectionResolverInterface::class),
			m::mock(Filesystem::class),
		));
		$migrator->getRepository()->shouldReceive('getLast')->once()->andReturn(array());

		$migrator->rollback();
	}

}


class MigratorTestMigrationStub {
	public function __construct($migration) { $this->migration = $migration; }
	public $migration;
}
