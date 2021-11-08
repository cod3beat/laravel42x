<?php

use Illuminate\Container\Container;
use Illuminate\Queue\RedisQueue;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class QueueRedisJobTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testFireProperlyCallsTheJobHandler()
    {
        $job = $this->getJob();
        $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler = m::mock('StdClass'));
        $handler->shouldReceive('fire')->once()->with($job, ['data']);

		$job->fire();
	}


	public function testDeleteRemovesTheJobFromRedis()
	{
		$job = $this->getJob();
		$job->getRedisQueue()->shouldReceive('deleteReserved')->once()->with('default', $job->getRedisJob());

		$job->delete();
	}


	public function testReleaseProperlyReleasesJobOntoRedis()
	{
		$job = $this->getJob();
		$job->getRedisQueue()->shouldReceive('deleteReserved')->once()->with('default', $job->getRedisJob());
		$job->getRedisQueue()->shouldReceive('release')->once()->with('default', $job->getRedisJob(), 1, 2);

		$job->release(1);
	}


	protected function getJob()
	{
		return new Illuminate\Queue\Jobs\RedisJob(
			m::mock(Container::class),
			m::mock(RedisQueue::class),
			json_encode(['job' => 'foo', 'data' => ['data'], 'attempts' => 1]),
			'default'
		);
	}

}
