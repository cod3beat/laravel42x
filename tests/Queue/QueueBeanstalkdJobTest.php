<?php

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\BeanstalkdJob;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class QueueBeanstalkdJobTest extends BackwardCompatibleTestCase
{
    /**
     * @var Container|ObjectProphecy
     */
    private $container;
    /**
     * @var Pheanstalk|ObjectProphecy
     */
    private $pheanstalk;
    /**
     * @var Job|ObjectProphecy
     */
    private $pheanstalkJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->prophesize(Container::class);
        $this->pheanstalk = $this->prophesize(Pheanstalk::class);
        $this->pheanstalkJob = $this->prophesize(Job::class);
    }

    protected function tearDown(): void
    {
        m::close();
    }


    public function testFireProperlyCallsTheJobHandler(): void
    {
        $this->pheanstalkJob->getData()->willReturn(json_encode(['job' => 'foo', 'data' => ['data1']]));

        $handler = $this->prophesize(BeanstalkdDummyHandler::class);
        $this->container->make('foo')->willReturn($handler->reveal());

        $job = $this->getProphesizedJob();

        $handler->fire($job, ['data1'])->shouldBeCalledOnce();

		$job->fire();
	}


	public function testDeleteRemovesTheJobFromBeanstalkd(): void
    {
		$job = $this->getJob();
		$job->getPheanstalk()->shouldReceive('delete')->once()->with($job->getPheanstalkJob());

		$job->delete();
	}


	public function testReleaseProperlyReleasesJobOntoBeanstalkd(): void
    {
        $job = $this->getJob();
        $job->getPheanstalk()->shouldReceive('release')->once()->with(
            $job->getPheanstalkJob(),
            PheanstalkInterface::DEFAULT_PRIORITY,
            0
        );

        $job->release();
    }


	public function testBuryProperlyBuryTheJobFromBeanstalkd(): void
    {
		$job = $this->getJob();
		$job->getPheanstalk()->shouldReceive('bury')->once()->with($job->getPheanstalkJob());

		$job->bury();
	}


	protected function getJob(): BeanstalkdJob
    {
		return new Illuminate\Queue\Jobs\BeanstalkdJob(
            m::mock(Container::class),
            m::mock(Pheanstalk::class),
            m::mock(Job::class),
            'default'
		);
	}

    protected function getProphesizedJob(): BeanstalkdJob
    {
        return new BeanstalkdJob(
            $this->container->reveal(),
            $this->pheanstalk->reveal(),
            $this->pheanstalkJob->reveal(),
            'default'
        );
    }

}

class BeanstalkdDummyHandler
{
    public function fire(\Illuminate\Queue\Jobs\Job $job, $data): void
    {

    }
}