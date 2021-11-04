<?php

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\BeanstalkdJob;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class QueueBeanstalkdQueueTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testPushProperlyPushesJobOntoBeanstalkd()
    {
        $queue = new Illuminate\Queue\BeanstalkdQueue(m::mock(Pheanstalk::class), 'default', 60);
        $pheanstalk = $queue->getPheanstalk();
        $pheanstalk->shouldReceive('useTube')->once()->with('stack')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('useTube')->once()->with('default')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('put')->twice()->with(
            json_encode(array('job' => 'foo', 'data' => array('data'))),
            1024,
            0,
            60
        );

        $queue->push('foo', array('data'), 'stack');
        $queue->push('foo', array('data'));
    }


	public function testDelayedPushProperlyPushesJobOntoBeanstalkd()
    {
        $queue = new Illuminate\Queue\BeanstalkdQueue(m::mock(Pheanstalk::class), 'default', 60);
        $pheanstalk = $queue->getPheanstalk();
        $pheanstalk->shouldReceive('useTube')->once()->with('stack')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('useTube')->once()->with('default')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('put')->twice()->with(
            json_encode(array('job' => 'foo', 'data' => array('data'))),
            PheanstalkInterface::DEFAULT_PRIORITY,
            5,
            PheanstalkInterface::DEFAULT_TTR
        );

        $queue->later(5, 'foo', array('data'), 'stack');
        $queue->later(5, 'foo', array('data'));
    }


	public function testPopProperlyPopsJobOffOfBeanstalkd()
    {
        $queue = new Illuminate\Queue\BeanstalkdQueue(m::mock(Pheanstalk::class), 'default', 60);
        $queue->setContainer(m::mock(Container::class));
        $pheanstalk = $queue->getPheanstalk();
        $pheanstalk->shouldReceive('watchOnly')->once()->with('default')->andReturn($pheanstalk);
        $job = m::mock(Job::class);
        $pheanstalk->shouldReceive('reserve')->once()->andReturn($job);

        $result = $queue->pop();

        $this->assertInstanceOf(BeanstalkdJob::class, $result);
    }


	public function testDeleteProperlyRemoveJobsOffBeanstalkd()
    {
        $queue = new Illuminate\Queue\BeanstalkdQueue(m::mock(Pheanstalk::class), 'default', 60);
        $pheanstalk = $queue->getPheanstalk();
        $pheanstalk->shouldReceive('useTube')->once()->with('default')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('delete')->once()->with(1);

        $queue->deleteMessage('default', 1);
    }

}
