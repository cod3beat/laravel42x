<?php

use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseMigrationMakeCommandTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testBasicCreateGivesCreatorProperArguments()
    {
        $command = new DatabaseMigrationMakeCommandTestStub(
            $creator = m::mock('Illuminate\Database\Migrations\MigrationCreator'), __DIR__ . '/vendor'
        );
        $app = array('path' => __DIR__);
        $command->setLaravel($app);
		$creator->shouldReceive('create')->once()->with('create_foo', __DIR__.'/database/migrations', null, false);

		$this->runCommand($command, array('name' => 'create_foo'));
	}


	public function testBasicCreateGivesCreatorProperArgumentsWhenTableIsSet()
	{
		$command = new DatabaseMigrationMakeCommandTestStub($creator = m::mock('Illuminate\Database\Migrations\MigrationCreator'), __DIR__.'/vendor');
		$app = array('path' => __DIR__);
		$command->setLaravel($app);
		$creator->shouldReceive('create')->once()->with('create_foo', __DIR__.'/database/migrations', 'users', true);

		$this->runCommand($command, array('name' => 'create_foo', '--create' => 'users'));
	}


	public function testPackagePathsMayBeUsed()
	{
		$command = new DatabaseMigrationMakeCommandTestStub($creator = m::mock('Illuminate\Database\Migrations\MigrationCreator'), __DIR__.'/vendor');
		$app = array('path' => __DIR__);
		$command->setLaravel($app);
		$creator->shouldReceive('create')->once()->with('create_foo', __DIR__.'/vendor/bar/src/migrations', null, false);

		$this->runCommand($command, array('name' => 'create_foo', '--package' => 'bar'));
	}


	public function testPackageFallsBackToVendorDirWhenNotExplicit()
	{
		$command = new DatabaseMigrationMakeCommandTestStub($creator = m::mock('Illuminate\Database\Migrations\MigrationCreator'), __DIR__.'/vendor');
		$creator->shouldReceive('create')->once()->with('create_foo', __DIR__.'/vendor/foo/bar/src/migrations', null, false);

		$this->runCommand($command, array('name' => 'create_foo', '--package' => 'foo/bar'));
	}


	protected function runCommand($command, $input = array())
	{
		return $command->run(new Symfony\Component\Console\Input\ArrayInput($input), new Symfony\Component\Console\Output\NullOutput);
	}

}



class DatabaseMigrationMakeCommandTestStub extends MigrateMakeCommand
{
	public function call($command, array $arguments = array())
	{
		//
	}
}
