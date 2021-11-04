<?php

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Seeder;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseSeederTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testCallResolveTheClassAndCallsRun()
    {
        $seeder = new Seeder;
        $seeder->setContainer($container = m::mock(Container::class));
        $output = m::mock(OutputInterface::class);
		$output->shouldReceive('writeln')->once()->andReturn('foo');
		$command = m::mock(Command::class);
		$command->shouldReceive('getOutput')->once()->andReturn($output);
		$seeder->setCommand($command);
		$container->shouldReceive('make')->once()->with('ClassName')->andReturn($child = m::mock('StdClass'));
		$child->shouldReceive('setContainer')->once()->with($container)->andReturn($child);
		$child->shouldReceive('setCommand')->once()->with($command)->andReturn($child);
		$child->shouldReceive('run')->once();

		$seeder->call('ClassName');
	}


	public function testSetContainer()
	{
		$seeder = new Seeder;
		$container = m::mock(Container::class);
		$this->assertEquals($seeder->setContainer($container), $seeder);
	}


	public function testSetCommand()
	{
		$seeder = new Seeder;
		$command = m::mock(Command::class);
		$this->assertEquals($seeder->setCommand($command), $seeder);
	}

}
