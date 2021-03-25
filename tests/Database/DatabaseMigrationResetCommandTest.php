<?php

use Illuminate\Database\Console\Migrations\ResetCommand;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseMigrationResetCommandTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testResetCommandCallsMigratorWithProperArguments()
    {
        $command = new ResetCommand($migrator = m::mock('Illuminate\Database\Migrations\Migrator'));
        $command->setLaravel(new AppDatabaseMigrationStub());
        $migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('rollback')->twice()->with(false)->andReturn(true, false);
		$migrator->shouldReceive('getNotes')->andReturn(array());

		$this->runCommand($command);
	}


	public function testResetCommandCanBePretended()
	{
		$command = new ResetCommand($migrator = m::mock('Illuminate\Database\Migrations\Migrator'));
		$command->setLaravel(new AppDatabaseMigrationStub());
		$migrator->shouldReceive('setConnection')->once()->with('foo');
		$migrator->shouldReceive('rollback')->twice()->with(true)->andReturn(true, false);
		$migrator->shouldReceive('getNotes')->andReturn(array());

		$this->runCommand($command, array('--pretend' => true, '--database' => 'foo'));
	}


	protected function runCommand($command, $input = array())
	{
		return $command->run(new Symfony\Component\Console\Input\ArrayInput($input), new Symfony\Component\Console\Output\NullOutput);
	}
}

class AppDatabaseMigrationStub {
	public $env = 'development';
	public function environment() { return $this->env; }
}
