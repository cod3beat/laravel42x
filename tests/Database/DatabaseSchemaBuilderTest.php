<?php

use Illuminate\Database\Schema\Builder;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseSchemaBuilderTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testHasTableCorrectlyCallsGrammar()
    {
        $connection = m::mock(\Illuminate\Database\Connection::class);
        $grammar = m::mock('StdClass');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
		$builder = new Builder($connection);
		$grammar->shouldReceive('compileTableExists')->once()->andReturn('sql');
		$connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
		$connection->shouldReceive('select')->once()->with('sql', array('prefix_table'))->andReturn(array('prefix_table'));

		$this->assertTrue($builder->hasTable('table'));
	}

}
