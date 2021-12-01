<?php

use Illuminate\Cache\CacheManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\Dispatcher;
use Illuminate\Pagination\Factory;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseConnectionTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testSettingDefaultCallsGetDefaultGrammar()
    {
        $connection = $this->getMockConnection();
        $mock = m::mock(stdClass::class);
        $connection->expects($this->once())->method('getDefaultQueryGrammar')->willReturn($mock);
		$connection->useDefaultQueryGrammar();
		$this->assertEquals($mock, $connection->getQueryGrammar());
	}


	public function testSettingDefaultCallsGetDefaultPostProcessor()
	{
		$connection = $this->getMockConnection();
		$mock = m::mock(stdClass::class);
		$connection->expects($this->once())->method('getDefaultPostProcessor')->willReturn($mock);
		$connection->useDefaultPostProcessor();
		$this->assertEquals($mock, $connection->getPostProcessor());
	}


	public function testSelectOneCallsSelectAndReturnsSingleResult()
	{
		$connection = $this->getMockConnection(['select']);
		$connection->expects($this->once())->method('select')->with('foo', ['bar' => 'baz'])->willReturn(
            ['foo']
        );
		$this->assertEquals('foo', $connection->selectOne('foo', ['bar' => 'baz']));
	}


	public function testSelectProperlyCallsPDO()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['prepare']);
		$writePdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['prepare']);
		$writePdo->expects($this->never())->method('prepare');
		$statement = $this->getMock('PDOStatement', ['execute', 'fetchAll']);
		$statement->expects($this->once())->method('execute')->with($this->equalTo(['foo' => 'bar']));
		$statement->expects($this->once())->method('fetchAll')->willReturn(['boom']);
		$pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
		$mock = $this->getMockConnection(['prepareBindings'], $writePdo);
		$mock->setReadPdo($pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(
            ['foo' => 'bar']
        );
		$results = $mock->select('foo', ['foo' => 'bar']);
		$this->assertEquals(['boom'], $results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
		$this->assertIsNumeric($log[0]['time']);
	}


	public function testInsertCallsTheStatementMethod()
	{
		$connection = $this->getMockConnection(['statement']);
		$connection->expects($this->once())->method('statement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn(
            'baz'
        );
		$results = $connection->insert('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}


	public function testUpdateCallsTheAffectingStatementMethod()
	{
		$connection = $this->getMockConnection(['affectingStatement']);
		$connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(
            ['bar']
        ))->willReturn(
            'baz'
        );
		$results = $connection->update('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}


	public function testDeleteCallsTheAffectingStatementMethod()
	{
		$connection = $this->getMockConnection(['affectingStatement']);
		$connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(
            ['bar']
        ))->willReturn(
            'baz'
        );
		$results = $connection->delete('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}


	public function testStatementProperlyCallsPDO()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['prepare']);
		$statement = $this->getMock('PDOStatement', ['execute']);
		$statement->expects($this->once())->method('execute')->with($this->equalTo(['bar']))->willReturn('foo');
		$pdo->expects($this->once())->method('prepare')->with($this->equalTo('foo'))->willReturn($statement);
		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['bar']))->willReturn(
            ['bar']
        );
		$results = $mock->statement('foo', ['bar']);
		$this->assertEquals('foo', $results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['bar'], $log[0]['bindings']);
		$this->assertIsNumeric($log[0]['time']);
	}


	public function testAffectingStatementProperlyCallsPDO()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['prepare']);
		$statement = $this->getMock('PDOStatement', ['execute', 'rowCount']);
		$statement->expects($this->once())->method('execute')->with($this->equalTo(['foo' => 'bar']));
		$statement->expects($this->once())->method('rowCount')->willReturn(['boom']);
		$pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(
            ['foo' => 'bar']
        );
		$results = $mock->update('foo', ['foo' => 'bar']);
		$this->assertEquals(['boom'], $results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
		$this->assertIsNumeric($log[0]['time']);
	}


	public function testBeganTransactionFiresEventsIfSet()
	{
		$pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
		$connection = $this->getMockConnection(['getName'], $pdo);
		$connection->expects($this->any())->method('getName')->willReturn('name');
		$connection->setEventDispatcher($events = m::mock(Dispatcher::class));
		$events->shouldReceive('fire')->once()->with('connection.name.beganTransaction', $connection);
		$connection->beginTransaction();
	}


	public function testCommitedFiresEventsIfSet()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class);
		$connection = $this->getMockConnection(['getName'], $pdo);
		$connection->expects($this->once())->method('getName')->willReturn('name');
		$connection->setEventDispatcher($events = m::mock(Dispatcher::class));
		$events->shouldReceive('fire')->once()->with('connection.name.committed', $connection);
		$connection->commit();
	}


	public function testRollBackedFiresEventsIfSet()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class);
		$connection = $this->getMockConnection(['getName'], $pdo);
		$connection->expects($this->once())->method('getName')->willReturn('name');
		$connection->setEventDispatcher($events = m::mock(Dispatcher::class));
		$events->shouldReceive('fire')->once()->with('connection.name.rollingBack', $connection);
		$connection->rollBack();
	}


	public function testTransactionMethodRunsSuccessfully()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['beginTransaction', 'commit']);
		$mock = $this->getMockConnection([], $pdo);
		$pdo->expects($this->once())->method('beginTransaction');
		$pdo->expects($this->once())->method('commit');
		$result = $mock->transaction(function($db) { return $db; });
		$this->assertEquals($mock, $result);
	}


	public function testTransactionMethodRollsbackAndThrows()
	{
		$pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['beginTransaction', 'commit', 'rollBack']);
		$mock = $this->getMockConnection([], $pdo);
		$pdo->expects($this->once())->method('beginTransaction');
		$pdo->expects($this->once())->method('rollBack');
		$pdo->expects($this->never())->method('commit');
		try
		{
			$mock->transaction(function() { throw new Exception('foo'); });
		}
		catch (Exception $e)
		{
			$this->assertEquals('foo', $e->getMessage());
		}
	}

    public function testTransactionMethodDisallowPDOChanging()
    {
        $this->expectException(RuntimeException::class);
        $pdo = $this->getMock(DatabaseConnectionTestMockPDO::class, ['beginTransaction', 'commit', 'rollBack']);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        $mock = $this->getMockConnection([], $pdo);

        $mock->setReconnector(
            function ($connection) {
                $connection->setPDO(null);
            }
        );

		$mock->transaction(function ($connection) { $connection->reconnect(); });
	}


	public function testFromCreatesNewQueryBuilder()
	{
		$conn = $this->getMockConnection();
		$conn->setQueryGrammar(m::mock(Grammar::class));
		$conn->setPostProcessor(m::mock(Processor::class));
		$builder = $conn->table('users');
		$this->assertInstanceOf(\Illuminate\Database\Query\Builder::class, $builder);
		$this->assertEquals('users', $builder->from);
	}


	public function testPrepareBindings()
	{
		$date = m::mock('DateTime');
		$date->shouldReceive('format')->once()->with('foo')->andReturn('bar');
		$bindings = ['test' => $date];
		$conn = $this->getMockConnection();
		$grammar = m::mock(Grammar::class);
		$grammar->shouldReceive('getDateFormat')->once()->andReturn('foo');
		$conn->setQueryGrammar($grammar);
		$result = $conn->prepareBindings($bindings);
		$this->assertEquals(['test' => 'bar'], $result);
	}


	public function testLogQueryFiresEventsIfSet()
	{
		$connection = $this->getMockConnection();
		$connection->logQuery('foo', [], time());
		$connection->setEventDispatcher($events = m::mock(Dispatcher::class));
		$events->shouldReceive('fire')->once()->with('illuminate.query', ['foo', [], null, null]);
		$connection->logQuery('foo', [], null);
	}


	public function testPretendOnlyLogsQueries()
	{
		$connection = $this->getMockConnection();
		$queries = $connection->pretend(function($connection)
		{
			$connection->select('foo bar', ['baz']);
		});
		$this->assertEquals('foo bar', $queries[0]['query']);
		$this->assertEquals(['baz'], $queries[0]['bindings']);
	}


	public function testSchemaBuilderCanBeCreated()
	{
		$connection = $this->getMockConnection();
		$schema = $connection->getSchemaBuilder();
		$this->assertInstanceOf(Builder::class, $schema);
		$this->assertSame($connection, $schema->getConnection());
	}


	public function testResolvingPaginatorThroughClosure()
	{
		$connection = $this->getMockConnection();
		$paginator  = m::mock(Factory::class);
		$connection->setPaginator(function() use ($paginator)
		{
			return $paginator;
		});
		$this->assertEquals($paginator, $connection->getPaginator());
	}


	public function testResolvingCacheThroughClosure()
	{
		$connection = $this->getMockConnection();
		$cache  = m::mock(CacheManager::class);
		$connection->setCacheManager(function() use ($cache)
		{
			return $cache;
		});
		$this->assertEquals($cache, $connection->getCacheManager());
	}


	protected function getMockConnection($methods = [], $pdo = null)
	{
		$pdo = $pdo ?: new DatabaseConnectionTestMockPDO;
		$defaults = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];

		$connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(array_merge($defaults, $methods))
            ->setConstructorArgs([$pdo])
            ->getMock();

        $connection->enableQueryLog();

        return $connection;
	}

}

class DatabaseConnectionTestMockPDO extends PDO {
    public function __construct() {
        //
    }
}
