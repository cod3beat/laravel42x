<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Composer;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class FoundationComposerTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testDumpAutoloadRunsTheCorrectCommand()
    {
        $composer = $this->getMock(
            Composer::class,
            ['getProcess'],
            [$files = m::mock(Filesystem::class), __DIR__]
        );
        $files->shouldReceive('exists')->once()->with(__DIR__ . '/composer.phar')->andReturn(true);
        $process = m::mock('stdClass');
		$composer->expects($this->once())->method('getProcess')->willReturn($process);
		$process->shouldReceive('setCommandLine')->once()->with('"'.PHP_BINARY.'" composer.phar dump-autoload');
		$process->shouldReceive('run')->once();

		$composer->dumpAutoloads();
	}


	public function testDumpAutoloadRunsTheCorrectCommandWhenComposerIsntPresent()
	{
		$composer = $this->getMock(Composer::class, ['getProcess'], [
            $files = m::mock(
            Filesystem::class
        ), __DIR__
        ]);
		$files->shouldReceive('exists')->once()->with(__DIR__.'/composer.phar')->andReturn(false);
		$process = m::mock('stdClass');
		$composer->expects($this->once())->method('getProcess')->willReturn($process);
		$process->shouldReceive('setCommandLine')->once()->with('composer dump-autoload');
		$process->shouldReceive('run')->once();

		$composer->dumpAutoloads();
	}

}
