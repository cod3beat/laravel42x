<?php

use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Migrations\Migrator;
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
        $command = new ResetCommand($migrator = m::mock(Migrator::class));
        $command->setLaravel(new AppDatabaseMigrationStub());
        $migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('rollback')->twice()->with(false)->andReturn(true, false);
		$migrator->shouldReceive('getNotes')->andReturn([]);

		$this->runCommand($command);
	}


	public function testResetCommandCanBePretended()
	{
		$command = new ResetCommand($migrator = m::mock(Migrator::class));
		$command->setLaravel(new AppDatabaseMigrationStub());
		$migrator->shouldReceive('setConnection')->once()->with('foo');
		$migrator->shouldReceive('rollback')->twice()->with(true)->andReturn(true, false);
		$migrator->shouldReceive('getNotes')->andReturn([]);

		$this->runCommand($command, ['--pretend' => true, '--database' => 'foo']);
	}


	protected function runCommand($command, $input = [])
	{
		return $command->run(new Symfony\Component\Console\Input\ArrayInput($input), new Symfony\Component\Console\Output\NullOutput);
	}
}

class AppDatabaseMigrationStub {
	public $env = 'development';
	public function environment() { return $this->env; }
}
