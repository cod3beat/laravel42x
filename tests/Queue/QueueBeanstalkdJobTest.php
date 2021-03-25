<?php

use Illuminate\Container\Container;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class QueueBeanstalkdJobTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testFireProperlyCallsTheJobHandler()
    {
        $job = $this->getJob();
        $job->getPheanstalkJob()->shouldReceive('getData')->once()->andReturn(
            json_encode(array('job' => 'foo', 'data' => array('data')))
        );
        $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler = m::mock('StdClass'));
		$handler->shouldReceive('fire')->once()->with($job, array('data'));

		$job->fire();
	}


	public function testDeleteRemovesTheJobFromBeanstalkd()
	{
		$job = $this->getJob();
		$job->getPheanstalk()->shouldReceive('delete')->once()->with($job->getPheanstalkJob());

		$job->delete();
	}


	public function testReleaseProperlyReleasesJobOntoBeanstalkd()
    {
        $job = $this->getJob();
        $job->getPheanstalk()->shouldReceive('release')->once()->with(
            $job->getPheanstalkJob(),
            PheanstalkInterface::DEFAULT_PRIORITY,
            0
        );

        $job->release();
    }


	public function testBuryProperlyBuryTheJobFromBeanstalkd()
	{
		$job = $this->getJob();
		$job->getPheanstalk()->shouldReceive('bury')->once()->with($job->getPheanstalkJob());

		$job->bury();
	}


	protected function getJob()
	{
		return new Illuminate\Queue\Jobs\BeanstalkdJob(
            m::mock(Container::class),
            m::mock(Pheanstalk::class),
            m::mock(Job::class),
            'default'
		);
	}

}
