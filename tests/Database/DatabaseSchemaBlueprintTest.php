<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseSchemaBlueprintTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testToSqlRunsCommandsFromBlueprint()
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('statement')->once()->with('foo');
        $conn->shouldReceive('statement')->once()->with('bar');
		$grammar = m::mock(MySqlGrammar::class);
		$blueprint = $this->getMock(Blueprint::class, array('toSql'), array('users'));
		$blueprint->expects($this->once())->method('toSql')->with($this->equalTo($conn), $this->equalTo($grammar))->willReturn(
            array('foo', 'bar')
        );

		$blueprint->build($conn, $grammar);
	}


	public function testIndexDefaultNames()
	{
		$blueprint = new Blueprint('users');
		$blueprint->unique(array('foo', 'bar'));
		$commands = $blueprint->getCommands();
		$this->assertEquals('users_foo_bar_unique', $commands[0]->index);

		$blueprint = new Blueprint('users');
		$blueprint->index('foo');
		$commands = $blueprint->getCommands();
		$this->assertEquals('users_foo_index', $commands[0]->index);
	}


	public function testDropIndexDefaultNames()
	{
		$blueprint = new Blueprint('users');
		$blueprint->dropUnique(array('foo', 'bar'));
		$commands = $blueprint->getCommands();
		$this->assertEquals('users_foo_bar_unique', $commands[0]->index);

		$blueprint = new Blueprint('users');
		$blueprint->dropIndex(array('foo'));
		$commands = $blueprint->getCommands();
		$this->assertEquals('users_foo_index', $commands[0]->index);
	}


}
