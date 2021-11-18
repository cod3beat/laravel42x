<?php

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Migrations\Migrator;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseMigrationMigrateCommandTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testBasicMigrationsCallMigratorWithProperArguments()
    {
        $command = new MigrateCommand(
            $migrator = m::mock(Migrator::class),
            __DIR__ . '/vendor'
        );
        $app = new ApplicationDatabaseMigrationStub(['path' => __DIR__]);
        $command->setLaravel($app);
		$migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/database/migrations', false);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

		$this->runCommand($command);
	}


	public function testMigrationRepositoryCreatedWhenNecessary()
	{
		$params = [$migrator = m::mock(Migrator::class), __DIR__.'/vendor'];
		$command = $this->getMock(MigrateCommand::class, ['call'], $params);
		$app = new ApplicationDatabaseMigrationStub(['path' => __DIR__]);
		$command->setLaravel($app);
		$migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/database/migrations', false);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(false);
		$command->expects($this->once())->method('call')->with($this->equalTo('migrate:install'), $this->equalTo(
            ['--database' => null]
        ));

		$this->runCommand($command);
	}


	public function testPackageIsRespectedWhenMigrating()
	{
		$command = new MigrateCommand($migrator = m::mock(Migrator::class), __DIR__.'/vendor');
		$command->setLaravel(new ApplicationDatabaseMigrationStub());
		$migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/vendor/bar/src/migrations', false);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

		$this->runCommand($command, ['--package' => 'bar']);
	}


	public function testVendorPackageIsRespectedWhenMigrating()
	{
		$command = new MigrateCommand($migrator = m::mock(Migrator::class), __DIR__.'/vendor');
		$command->setLaravel(new ApplicationDatabaseMigrationStub());
		$migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/vendor/foo/bar/src/migrations', false);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

		$this->runCommand($command, ['--package' => 'foo/bar']);
	}


	public function testTheCommandMayBePretended()
	{
		$command = new MigrateCommand($migrator = m::mock(Migrator::class), __DIR__.'/vendor');
		$app = new ApplicationDatabaseMigrationStub(['path' => __DIR__]);
		$command->setLaravel($app);
		$migrator->shouldReceive('setConnection')->once()->with(null);
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/database/migrations', true);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

		$this->runCommand($command, ['--pretend' => true]);
	}


	public function testTheDatabaseMayBeSet()
	{
		$command = new MigrateCommand($migrator = m::mock(Migrator::class), __DIR__.'/vendor');
		$app = new ApplicationDatabaseMigrationStub(['path' => __DIR__]);
		$command->setLaravel($app);
		$migrator->shouldReceive('setConnection')->once()->with('foo');
		$migrator->shouldReceive('run')->once()->with(__DIR__.'/database/migrations', false);
		$migrator->shouldReceive('getNotes')->andReturn([]);
		$migrator->shouldReceive('repositoryExists')->once()->andReturn(true);

		$this->runCommand($command, ['--database' => 'foo']);
	}


	protected function runCommand($command, $input = [])
	{
		return $command->run(new Symfony\Component\Console\Input\ArrayInput($input), new Symfony\Component\Console\Output\NullOutput);
	}

}

class ApplicationDatabaseMigrationStub implements ArrayAccess {
	public $content = [];
	public $env = 'development';
	public function __construct(array $data = []) { $this->content = $data; }
	public function offsetExists($offset) { return isset($this->content[$offset]); }
	public function offsetGet($offset) { return $this->content[$offset]; }
	public function offsetSet($offset, $value) { $this->content[$offset] = $value; }
	public function offsetUnset($offset) { unset($this->content[$offset]); }
	public function environment() { return $this->env; }
}
