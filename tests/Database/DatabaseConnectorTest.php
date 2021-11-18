<?php

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Connectors\SqlServerConnector;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseConnectorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testOptionResolution()
    {
        $connector = new Illuminate\Database\Connectors\Connector;
        $connector->setDefaultOptions([0 => 'foo', 1 => 'bar']);
        $this->assertEquals(
            [0 => 'baz', 1 => 'bar', 2 => 'boom'],
            $connector->getOptions(['options' => [0 => 'baz', 2 => 'boom']])
        );
	}


	/**
	 * @dataProvider mySqlConnectProvider
	 */
	public function testMySqlConnectCallsCreateConnectionWithProperArguments($dsn, $config)
	{
		$connector = $this->getMock(MySqlConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$connection->shouldReceive('prepare')->once()->with('set names \'utf8\' collate \'utf8_unicode_ci\'')->andReturn($connection);
		$connection->shouldReceive('prepare')->once()->with('set session sql_mode=\'\'')->andReturn($connection);
		$connection->shouldReceive('execute')->times(2);
		$connection->shouldReceive('exec')->zeroOrMoreTimes();
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}


	public function mySqlConnectProvider()
	{
		return [
			['mysql:host=foo;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'collation' => 'utf8_unicode_ci', 'charset' => 'utf8']],
			['mysql:host=foo;port=111;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'port' => 111, 'collation' => 'utf8_unicode_ci', 'charset' => 'utf8']],
			['mysql:unix_socket=baz;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'port' => 111, 'unix_socket' => 'baz', 'collation' => 'utf8_unicode_ci', 'charset' => 'utf8']],
        ];
	}


	public function testPostgresConnectCallsCreateConnectionWithProperArguments()
	{
		$dsn = 'pgsql:host=foo;dbname=bar;port=111';
		$config = ['host' => 'foo', 'database' => 'bar', 'port' => 111, 'charset' => 'utf8'];
		$connector = $this->getMock(PostgresConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$connection->shouldReceive('prepare')->once()->with('set names \'utf8\'')->andReturn($connection);
		$connection->shouldReceive('execute')->once();
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}


	public function testPostgresSearchPathIsSet()
	{
		$dsn = 'pgsql:host=foo;dbname=bar';
		$config = ['host' => 'foo', 'database' => 'bar', 'schema' => 'public', 'charset' => 'utf8'];
		$connector = $this->getMock(PostgresConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$connection->shouldReceive('prepare')->once()->with('set names \'utf8\'')->andReturn($connection);
		$connection->shouldReceive('prepare')->once()->with("set search_path to public")->andReturn($connection);
		$connection->shouldReceive('execute')->twice();
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}


	public function testSQLiteMemoryDatabasesMayBeConnectedTo()
	{
		$dsn = 'sqlite::memory:';
		$config = ['database' => ':memory:'];
		$connector = $this->getMock(SQLiteConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}


	public function testSQLiteFileDatabasesMayBeConnectedTo()
	{
		$dsn = 'sqlite:'.__DIR__;
		$config = ['database' => __DIR__];
		$connector = $this->getMock(SQLiteConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}


	public function testSqlServerConnectCallsCreateConnectionWithProperArguments()
	{
		$config = ['host' => 'foo', 'database' => 'bar', 'port' => 111];
		$dsn = $this->getDsn($config);
		$connector = $this->getMock(SqlServerConnector::class, ['createConnection', 'getOptions']);
		$connection = m::mock('stdClass');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->willReturn(
            ['options']
        );
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(
            ['options']
        ))->willReturn(
            $connection
        );
		$result = $connector->connect($config);

		$this->assertSame($result, $connection);
	}

	protected function getDsn(array $config)
	{
		extract($config);

		if (in_array('dblib', PDO::getAvailableDrivers()))
		{
			$port = isset($config['port']) ? ':'.$port : '';
			return "dblib:host={$host}{$port};dbname={$database}";
		}
		else
		{
			$port = isset($config['port']) ? ','.$port : '';
			return "sqlsrv:Server={$host}{$port};Database={$database}";
		}
	}

}
