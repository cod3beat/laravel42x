<?php

use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\Console\Command\Command;

class ConsoleApplicationTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testAddSetsLaravelInstance()
    {
        $app = $this->getMock(\Illuminate\Console\Application::class, array('addToParent'));
        $app->setLaravel('foo');
        $command = m::mock(\Illuminate\Console\Command::class);
		$command->shouldReceive('setLaravel')->once()->with('foo');
		$app->expects($this->once())->method('addToParent')->with($this->equalTo($command))->willReturn($command);
		$result = $app->add($command);

		$this->assertEquals($command, $result);
	}


	public function testLaravelNotSetOnSymfonyCommands()
	{
		$app = $this->getMock(\Illuminate\Console\Application::class, array('addToParent'));
		$app->setLaravel('foo');
		$command = m::mock(Command::class);
		$command->shouldReceive('setLaravel')->never();
		$app->expects($this->once())->method('addToParent')->with($this->equalTo($command))->willReturn($command);
		$result = $app->add($command);

		$this->assertEquals($command, $result);
	}


	public function testResolveAddsCommandViaApplicationResolution()
	{
		$app = $this->getMock(\Illuminate\Console\Application::class, array('addToParent'));
		$command = m::mock(Command::class);
		$app->setLaravel(array('foo' => $command));
		$app->expects($this->once())->method('addToParent')->with($this->equalTo($command))->willReturn($command);
		$result = $app->resolve('foo');

		$this->assertEquals($command, $result);
	}


	public function testResolveCommandsCallsResolveForAllCommandsItsGiven()
	{
		$app = m::mock('Illuminate\Console\Application[resolve]');
		$app->shouldReceive('resolve')->twice()->with('foo');
		$app->resolveCommands('foo', 'foo');
	}


	public function testResolveCommandsCallsResolveForAllCommandsItsGivenViaArray()
	{
		$app = m::mock('Illuminate\Console\Application[resolve]');
		$app->shouldReceive('resolve')->twice()->with('foo');
		$app->resolveCommands(array('foo', 'foo'));
	}

}
