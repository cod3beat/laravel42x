<?php

use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class FoundationEnvironmentDetectorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testEnvironmentDetection()
    {
        $env = m::mock(\Illuminate\Foundation\EnvironmentDetector::class)->makePartial();
        $env->shouldReceive('isMachine')->once()->with('localhost')->andReturn(false);
        $result = $env->detect(
            array(
			'local'   => array('localhost')
		));
		$this->assertEquals('production', $result);


		$env = m::mock(\Illuminate\Foundation\EnvironmentDetector::class)->makePartial();
		$env->shouldReceive('isMachine')->once()->with('localhost')->andReturn(true);
		$result = $env->detect(array(
			'local'   => array('localhost')
		));
		$this->assertEquals('local', $result);
	}


	public function testClosureCanBeUsedForCustomEnvironmentDetection()
	{
		$env = new Illuminate\Foundation\EnvironmentDetector;

		$result = $env->detect(function() { return 'foobar'; });
		$this->assertEquals('foobar', $result);
	}


	public function testConsoleEnvironmentDetection()
	{
		$env = new Illuminate\Foundation\EnvironmentDetector;

		$result = $env->detect(array(
			'local'   => array('foobar')
		), array('--env=local'));
		$this->assertEquals('local', $result);
	}

}
