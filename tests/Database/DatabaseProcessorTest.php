<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseProcessorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testInsertGetIdProcessing()
    {
        $pdo = $this->createMock(ProcessorTestPDOStub::class);
        $pdo->expects($this->once())->method('lastInsertId')->with($this->equalTo('id'))->willReturn('1');
        $connection = m::mock(Connection::class);
		$connection->shouldReceive('insert')->once()->with('sql', ['foo']);
		$connection->shouldReceive('getPdo')->once()->andReturn($pdo);
		$builder = m::mock(Builder::class);
		$builder->shouldReceive('getConnection')->andReturn($connection);
		$processor = new Illuminate\Database\Query\Processors\Processor;
		$result = $processor->processInsertGetId($builder, 'sql', ['foo'], 'id');
		$this->assertSame(1, $result);
	}

}

class ProcessorTestPDOStub extends PDO {

	public function __construct() {
        //
    }
    
	public function lastInsertId($sequence = null) {
        //
    }

}
