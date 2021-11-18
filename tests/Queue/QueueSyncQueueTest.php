<?php

use Illuminate\Queue\SyncQueue;
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
        $sync = $this->getMock(SyncQueue::class, ['resolveJob']);
        $job = m::mock('StdClass');
        $sync->expects($this->once())->method('resolveJob')->with(
            $this->equalTo('Foo'),
            $this->equalTo('{"foo":"foobar"}')
        )->willReturn($job);
		$job->shouldReceive('fire')->once();

		$sync->push('Foo', ['foo' => 'foobar']);
	}

}
