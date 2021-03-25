<?php

use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class QueueSyncQueueTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testPushShouldFireJobInstantly()
    {
        $sync = $this->getMock('Illuminate\Queue\SyncQueue', array('resolveJob'));
        $job = m::mock('StdClass');
        $sync->expects($this->once())->method('resolveJob')->with(
            $this->equalTo('Foo'),
            $this->equalTo('{"foo":"foobar"}')
        )->will($this->returnValue($job));
		$job->shouldReceive('fire')->once();

		$sync->push('Foo', array('foo' => 'foobar'));
	}

}
