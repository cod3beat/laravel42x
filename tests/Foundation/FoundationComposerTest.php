<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Composer;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\Process\Process;

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
        $process = $this->getMock(Process::class, ['run'], ['composer.phar dump-autoload', __DIR__ . '/composer.phar']);
		$composer->expects($this->once())->method('getProcess')->willReturn($process);
		$process->expects($this->once())->method('run');

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
        $process = $this->getMock(Process::class, ['run'], ['composer dump-autoload', __DIR__ . '/composer.phar']);
        $composer->expects($this->once())->method('getProcess')->willReturn($process);
        $process->expects($this->once())->method('run');

		$composer->dumpAutoloads();
	}

}
