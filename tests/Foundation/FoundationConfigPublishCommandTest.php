<?php

use Illuminate\Foundation\ConfigPublisher;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class FoundationConfigPublishCommandTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testCommandCallsPublisherWithProperPackageName()
    {
        $command = new Illuminate\Foundation\Console\ConfigPublishCommand(
            $pub = m::mock(ConfigPublisher::class)
        );
        $pub->shouldReceive('alreadyPublished')->andReturn(false);
        $pub->shouldReceive('publishPackage')->once()->with('foo');
		$command->run(new Symfony\Component\Console\Input\ArrayInput(['package' => 'foo']), new Symfony\Component\Console\Output\NullOutput);
	}

}
