<?php

use Illuminate\Queue\Listener;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\Process\Process;

class QueueListenerTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testRunProcessCallsProcess()
    {
        $process = m::mock(Process::class)->makePartial();
        $process->shouldReceive('run')->once();
        $listener = m::mock(Listener::class)->makePartial();
		$listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(false);

		$listener->runProcess($process, 1);
	}


	public function testListenerStopsWhenMemoryIsExceeded()
	{
		$process = m::mock(Process::class)->makePartial();
		$process->shouldReceive('run')->once();
		$listener = m::mock(Listener::class)->makePartial();
		$listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(true);
		$listener->shouldReceive('stop')->once();

		$listener->runProcess($process, 1);
	}


	public function testMakeProcessCorrectlyFormatsCommandLine()
	{
		$listener = new Illuminate\Queue\Listener(__DIR__);
		$process = $listener->makeProcess('connection', 'queue', 1, 2, 3);

		$this->assertInstanceOf(Process::class, $process);
		$this->assertEquals(__DIR__, $process->getWorkingDirectory());
		$this->assertEquals(3, $process->getTimeout());
		$this->assertEquals('"'.PHP_BINARY.'" artisan queue:work connection --queue="queue" --delay=1 --memory=2 --sleep=3 --tries=0', $process->getCommandLine());
	}

}
