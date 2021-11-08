<?php

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\QueueManager;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class QueueWorkerTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testJobIsPoppedOffQueueAndProcessed()
    {
        $worker = $this->getMock(
            \Illuminate\Queue\Worker::class,
            ['process'],
            [$manager = m::mock(QueueManager::class)]
        );
        $manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
        $manager->shouldReceive('getName')->andReturn('connection');
		$job = m::mock(Job::class);
		$connection->shouldReceive('pop')->once()->with('queue')->andReturn($job);
		$worker->expects($this->once())->method('process')->with($this->equalTo('connection'), $this->equalTo($job), $this->equalTo(0), $this->equalTo(0));

		$worker->pop('connection', 'queue');
	}


	public function testJobIsPoppedOffFirstQueueInListAndProcessed()
	{
		$worker = $this->getMock(\Illuminate\Queue\Worker::class, ['process'], [
            $manager = m::mock(
            QueueManager::class
        )
        ]);
		$manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
		$manager->shouldReceive('getName')->andReturn('connection');
		$job = m::mock(Job::class);
		$connection->shouldReceive('pop')->once()->with('queue1')->andReturn(null);
		$connection->shouldReceive('pop')->once()->with('queue2')->andReturn($job);
		$worker->expects($this->once())->method('process')->with($this->equalTo('connection'), $this->equalTo($job), $this->equalTo(0), $this->equalTo(0));

		$worker->pop('connection', 'queue1,queue2');
	}


	public function testWorkerSleepsIfNoJobIsPresentAndSleepIsEnabled()
	{
		$worker = $this->getMock(\Illuminate\Queue\Worker::class, ['process', 'sleep'], [
            $manager = m::mock(
            QueueManager::class
        )
        ]);
		$manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
		$connection->shouldReceive('pop')->once()->with('queue')->andReturn(null);
		$worker->expects($this->never())->method('process');
		$worker->expects($this->once())->method('sleep')->with($this->equalTo(3));

		$worker->pop('connection', 'queue', 0, 3);
	}


	public function testWorkerLogsJobToFailedQueueIfMaxTriesHasBeenExceeded()
	{
		$worker = new Illuminate\Queue\Worker(m::mock(QueueManager::class), $failer = m::mock(
            FailedJobProviderInterface::class
        ));
		$job = m::mock(Job::class);
		$job->shouldReceive('attempts')->once()->andReturn(10);
		$job->shouldReceive('getQueue')->once()->andReturn('queue');
		$job->shouldReceive('getRawBody')->once()->andReturn('body');
		$job->shouldReceive('delete')->once();
		$failer->shouldReceive('log')->once()->with('connection', 'queue', 'body');

		$worker->process('connection', $job, 3, 0);
	}


	public function testProcessFiresJobAndAutoDeletesIfTrue()
	{
		$worker = new Illuminate\Queue\Worker(m::mock(QueueManager::class));
		$job = m::mock(Job::class);
		$job->shouldReceive('fire')->once();
		$job->shouldReceive('autoDelete')->once()->andReturn(true);
		$job->shouldReceive('delete')->once();

		$worker->process('connection', $job, 0, 0);
	}


	public function testProcessFiresJobAndDoesntCallDeleteIfJobDoesntAutoDelete()
	{
		$worker = new Illuminate\Queue\Worker(m::mock(QueueManager::class));
		$job = m::mock(Job::class);
		$job->shouldReceive('fire')->once();
		$job->shouldReceive('autoDelete')->once()->andReturn(false);
		$job->shouldReceive('delete')->never();

		$worker->process('connection', $job, 0, 0);
	}


    public function testJobIsReleasedWhenExceptionIsThrown()
    {
        $this->expectException(RuntimeException::class);
        $worker = new Illuminate\Queue\Worker(m::mock(QueueManager::class));
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->once()->andReturnUsing(
            function () {
                throw new RuntimeException;
            }
        );
        $job->shouldReceive('isDeleted')->once()->andReturn(false);
        $job->shouldReceive('release')->once()->with(5);

        $worker->process('connection', $job, 0, 5);
    }


    public function testJobIsNotReleasedWhenExceptionIsThrownButJobIsDeleted()
    {
        $this->expectException(RuntimeException::class);
        $worker = new Illuminate\Queue\Worker(m::mock(QueueManager::class));
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->once()->andReturnUsing(
            function () {
                throw new RuntimeException;
            }
        );
        $job->shouldReceive('isDeleted')->once()->andReturn(true);
        $job->shouldReceive('release')->never();

        $worker->process('connection', $job, 0, 5);
    }

}
