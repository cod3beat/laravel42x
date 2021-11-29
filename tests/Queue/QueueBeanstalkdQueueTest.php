<?php

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\BeanstalkdJob;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Prophecy\Prophecy\ObjectProphecy;

class QueueBeanstalkdQueueTest extends BackwardCompatibleTestCase
{
    /**
     * @var Pheanstalk|ObjectProphecy
     */
    private $pheanstalk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pheanstalk = $this->prophesize(Pheanstalk::class);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testPushProperlyPushesJobOntoBeanstalkd(): void
    {
        $this->pheanstalk->useTube('stack')->willReturn($this->pheanstalk)->shouldBeCalledOnce();
        $this->pheanstalk->useTube('default')->willReturn($this->pheanstalk)->shouldBeCalledOnce();
        $this->pheanstalk->put(
            json_encode(['job' => 'foo', 'data' => ['data']]),
            PheanstalkInterface::DEFAULT_PRIORITY,
            0,
            PheanstalkInterface::DEFAULT_TTR
        )->shouldBeCalledTimes(2);

        $queue = new Illuminate\Queue\BeanstalkdQueue(
            $this->pheanstalk->reveal(),
            'default',
            60
        );

        $queue->push('foo', ['data'], 'stack');
        $queue->push('foo', ['data']);
    }


	public function testDelayedPushProperlyPushesJobOntoBeanstalkd(): void
    {
        $this->pheanstalk->useTube('stack')->willReturn($this->pheanstalk)->shouldBeCalledOnce();
        $this->pheanstalk->useTube('default')->willReturn($this->pheanstalk)->shouldBeCalledOnce();
        $this->pheanstalk->put(
            json_encode(['job' => 'foo', 'data' => ['data']]),
            PheanstalkInterface::DEFAULT_PRIORITY,
            5,
            PheanstalkInterface::DEFAULT_TTR
        )->shouldBeCalledTimes(2);

        $queue = new Illuminate\Queue\BeanstalkdQueue(
            $this->pheanstalk->reveal(),
            'default',
            60
        );

        $queue->later(5, 'foo', ['data'], 'stack');
        $queue->later(5, 'foo', ['data']);
    }


	public function testPopProperlyPopsJobOffOfBeanstalkd(): void
    {
        $this->pheanstalk->watchOnly('default')->willReturn($this->pheanstalk->reveal())->shouldBeCalledOnce();

        $job = $this->prophesize(Job::class);
        $this->pheanstalk->reserveWithTimeout(0)->willReturn($job)->shouldBeCalledOnce();

        $container = $this->prophesize(Container::class);

        $queue = new Illuminate\Queue\BeanstalkdQueue(
            $this->pheanstalk->reveal(),
            'default',
            60
        );
        $queue->setContainer($container->reveal());

        $result = $queue->pop();

        $this->assertInstanceOf(BeanstalkdJob::class, $result);
    }


	public function testDeleteProperlyRemoveJobsOffBeanstalkd(): void
    {
        $queue = new Illuminate\Queue\BeanstalkdQueue(m::mock(Pheanstalk::class), 'default', 60);
        $pheanstalk = $queue->getPheanstalk();
        $pheanstalk->shouldReceive('useTube')->once()->with('default')->andReturn($pheanstalk);
        $pheanstalk->shouldReceive('delete')->once()->with(1);

        $queue->deleteMessage('default', 1);
    }

}
