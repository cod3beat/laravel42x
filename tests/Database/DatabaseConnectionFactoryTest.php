<?php

use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Connectors\SqlServerConnector;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseConnectionFactoryPDOStub extends PDO {
	public function __construct() {}
}

class DatabaseConnectionFactoryTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testMakeCallsCreateConnection()
    {
        $factory = $this->getMock(
            ConnectionFactory::class,
            ['createConnector', 'createConnection'],
            [$container = m::mock(Container::class)]
        );
        $container->shouldReceive('bound')->andReturn(false);
        $connector = m::mock('stdClass');
		$config = ['driver' => 'mysql', 'prefix' => 'prefix', 'database' => 'database', 'name' => 'foo'];
		$pdo = new DatabaseConnectionFactoryPDOStub;
		$connector->shouldReceive('connect')->once()->with($config)->andReturn($pdo);
		$factory->expects($this->once())->method('createConnector')->with($config)->willReturn($connector);
		$mockConnection = m::mock('stdClass');
		$passedConfig = array_merge($config, ['name' => 'foo']);
		$factory->expects($this->once())->method('createConnection')->with($this->equalTo('mysql'), $this->equalTo($pdo), $this->equalTo('database'), $this->equalTo('prefix'), $this->equalTo($passedConfig))->willReturn(
            $mockConnection
        );
		$connection = $factory->make($config, 'foo');

		$this->assertEquals($mockConnection, $connection);
	}


	public function testMakeCallsCreateConnectionForReadWrite()
	{
		$factory = $this->getMock(ConnectionFactory::class, ['createConnector', 'createConnection'], [
            $container = m::mock(
            Container::class
        )
        ]);
		$container->shouldReceive('bound')->andReturn(false);
		$connector = m::mock('stdClass');
		$config = [
			'read' => ['database' => 'database'],
			'write' => ['database' => 'database'],
			'driver' => 'mysql', 'prefix' => 'prefix', 'name' => 'foo',
        ];
		$expect = $config;
		unset($expect['read']);
		unset($expect['write']);
		$expect['database'] = 'database';
		$pdo = new DatabaseConnectionFactoryPDOStub;
		$connector->shouldReceive('connect')->twice()->with($expect)->andReturn($pdo);
		$factory->expects($this->exactly(2))->method('createConnector')->with($expect)->willReturn($connector);
		$mockConnection = m::mock('stdClass');
		$mockConnection->shouldReceive('setReadPdo')->once()->andReturn($mockConnection);
		$passedConfig = array_merge($expect, ['name' => 'foo']);
		$factory->expects($this->once())->method('createConnection')->with($this->equalTo('mysql'), $this->equalTo($pdo), $this->equalTo('database'), $this->equalTo('prefix'), $this->equalTo($passedConfig))->willReturn(
            $mockConnection
        );
		$connection = $factory->make($config, 'foo');

		$this->assertEquals($mockConnection, $connection);
	}


	public function testMakeCanCallTheContainer()
	{
		$factory = $this->getMock(ConnectionFactory::class, ['createConnector'], [
            $container = m::mock(
            Container::class
        )
        ]);
		$container->shouldReceive('bound')->andReturn(true);
		$connector = m::mock('stdClass');
		$config = ['driver' => 'mysql', 'prefix' => 'prefix', 'database' => 'database', 'name' => 'foo'];
		$pdo = new DatabaseConnectionFactoryPDOStub;
		$connector->shouldReceive('connect')->once()->with($config)->andReturn($pdo);
		$passedConfig = array_merge($config, ['name' => 'foo']);
		$factory->expects($this->once())->method('createConnector')->with($config)->willReturn($connector);
		$container->shouldReceive('make')->once()->with('db.connection.mysql', [$pdo, 'database', 'prefix', $passedConfig]
        )->andReturn('foo');
		$connection = $factory->make($config, 'foo');

		$this->assertEquals('foo', $connection);
	}


	public function testProperInstancesAreReturnedForProperDrivers()
	{
		$factory = new Illuminate\Database\Connectors\ConnectionFactory($container = m::mock(
            Container::class
        ));
		$container->shouldReceive('bound')->andReturn(false);
		$this->assertInstanceOf(MySqlConnector::class, $factory->createConnector(['driver' => 'mysql']));
		$this->assertInstanceOf(PostgresConnector::class, $factory->createConnector(['driver' => 'pgsql']));
		$this->assertInstanceOf(SQLiteConnector::class, $factory->createConnector(['driver' => 'sqlite']));
		$this->assertInstanceOf(SqlServerConnector::class, $factory->createConnector(['driver' => 'sqlsrv']));
	}


    public function testIfDriverIsntSetExceptionIsThrown()
    {
        $this->expectException(InvalidArgumentException::class);
        $factory = new Illuminate\Database\Connectors\ConnectionFactory(
            $container = m::mock(Container::class)
        );
        $factory->createConnector(['foo']);
    }


    public function testExceptionIsThrownOnUnsupportedDriver()
    {
        $this->expectException(InvalidArgumentException::class);
        $factory = new Illuminate\Database\Connectors\ConnectionFactory(
            $container = m::mock(Container::class)
        );
        $container->shouldReceive('bound')->once()->andReturn(false);
        $factory->createConnector(['driver' => 'foo']);
    }


	public function testCustomConnectorsCanBeResolvedViaContainer()
	{
		$factory = new Illuminate\Database\Connectors\ConnectionFactory($container = m::mock(
            Container::class
        ));
		$container->shouldReceive('bound')->once()->with('db.connector.foo')->andReturn(true);
		$container->shouldReceive('make')->once()->with('db.connector.foo')->andReturn('connector');

		$this->assertEquals('connector', $factory->createConnector(['driver' => 'foo']));
	}

}
