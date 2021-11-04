<?php

use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class FoundationArtisanTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testArtisanIsCalledWithProperArguments()
    {
        $artisan = $this->getMock(
            \Illuminate\Foundation\Artisan::class,
            array('getArtisan'),
            array($app = new Illuminate\Foundation\Application)
        );
        $artisan->expects($this->once())->method('getArtisan')->will(
            $this->returnValue($console = m::mock('Illuminate\Console\Application[find]'))
        );
        $console->shouldReceive('find')->once()->with('foo')->andReturn($command = m::mock('StdClass'));
		$command->shouldReceive('run')->once()->with(m::type(\Symfony\Component\Console\Input\ArrayInput::class), m::type(
            \Symfony\Component\Console\Output\NullOutput::class
        ))->andReturnUsing(function($input, $output)
		{
			return $input;
		});

		$input = $artisan->call('foo', array('--bar' => 'baz'));
		$this->assertEquals('baz', $input->getParameterOption('--bar'));
	}

}
