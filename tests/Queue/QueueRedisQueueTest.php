<?php

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Redis\Database;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Predis\Client;

class QueueRedisQueueTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testPushProperlyPushesJobOntoRedis()
    {
        $queue = $this->getMock(
            RedisQueue::class,
            array('getRandomId'),
            array($redis = m::mock(Database::class), 'default')
        );
        $queue->expects($this->once())->method('getRandomId')->willReturn('foo');
        $redis->shouldReceive('connection')->once()->andReturn($redis);
		$redis->shouldReceive('rpush')->once()->with('queues:default', json_encode(array('job' => 'foo', 'data' => array('data'), 'id' => 'foo', 'attempts' => 1)));

		$id = $queue->push('foo', array('data'));
		$this->assertEquals('foo', $id);
	}


	public function testDelayedPushProperlyPushesJobOntoRedis()
	{
		$queue = $this->getMock(RedisQueue::class, array('getSeconds', 'getTime', 'getRandomId'), array($redis = m::mock(
            Database::class
        ), 'default'));
		$queue->expects($this->once())->method('getRandomId')->willReturn('foo');
		$queue->expects($this->once())->method('getSeconds')->with(1)->willReturn(1);
		$queue->expects($this->once())->method('getTime')->willReturn(1);

		$redis->shouldReceive('connection')->once()->andReturn($redis);
		$redis->shouldReceive('zadd')->once()->with(
			'queues:default:delayed',
			2,
			json_encode(array('job' => 'foo', 'data' => array('data'), 'id' => 'foo', 'attempts' => 1))
		);

		$id = $queue->later(1, 'foo', array('data'));
		$this->assertEquals('foo', $id);
	}


	public function testDelayedPushWithDateTimeProperlyPushesJobOntoRedis()
	{
		$date = Carbon::now();
		$queue = $this->getMock(RedisQueue::class, array('getSeconds', 'getTime', 'getRandomId'), array($redis = m::mock(
            Database::class
        ), 'default'));
		$queue->expects($this->once())->method('getRandomId')->willReturn('foo');
		$queue->expects($this->once())->method('getSeconds')->with($date)->willReturn(1);
		$queue->expects($this->once())->method('getTime')->willReturn(1);

		$redis->shouldReceive('connection')->once()->andReturn($redis);
		$redis->shouldReceive('zadd')->once()->with(
			'queues:default:delayed',
			2,
			json_encode(array('job' => 'foo', 'data' => array('data'), 'id' => 'foo', 'attempts' => 1))
		);

		$queue->later($date, 'foo', array('data'));
	}


	public function testPopProperlyPopsJobOffOfRedis()
	{
		$queue = $this->getMock(RedisQueue::class, array('getTime', 'migrateAllExpiredJobs'), array($redis = m::mock(
            Database::class
        ), 'default'));
		$queue->setContainer(m::mock(Container::class));
		$queue->expects($this->once())->method('getTime')->willReturn(1);
		$queue->expects($this->once())->method('migrateAllExpiredJobs')->with($this->equalTo('queues:default'));

		$redis->shouldReceive('connection')->andReturn($redis);
		$redis->shouldReceive('lpop')->once()->with('queues:default')->andReturn('foo');
		$redis->shouldReceive('zadd')->once()->with('queues:default:reserved', 61, 'foo');

		$result = $queue->pop();

		$this->assertInstanceOf(RedisJob::class, $result);
	}


	public function testReleaseMethod()
	{
		$queue = $this->getMock(RedisQueue::class, array('getTime'), array($redis = m::mock(
            Database::class
        ), 'default'));
		$queue->expects($this->once())->method('getTime')->willReturn(1);
		$redis->shouldReceive('connection')->once()->andReturn($redis);
		$redis->shouldReceive('zadd')->once()->with('queues:default:delayed', 2, json_encode(array('attempts' => 2)));

		$queue->release('default', json_encode(array('attempts' => 1)), 1, 2);
	}


	public function testMigrateExpiredJobs()
	{
		$queue = $this->getMock(RedisQueue::class, array('getTime'), array($redis = m::mock(
            Database::class
        ), 'default'));
		$queue->expects($this->once())->method('getTime')->willReturn(1);
		$transaction = m::mock('StdClass');
		$redis->shouldReceive('connection')->once()->andReturn($redis);
		$redis->shouldReceive('transaction')->with(m::any(), m::type('Closure'))->andReturnUsing(function($options, $callback) use ($transaction)
		{
			$callback($transaction);
		});
		$transaction->shouldReceive('zrangebyscore')->once()->with('from', '-inf', 1)->andReturn(array('foo', 'bar'));
		$transaction->shouldReceive('multi')->once();
		$transaction->shouldReceive('zremrangebyscore')->once()->with('from', '-inf', 1);
		$transaction->shouldReceive('rpush')->once()->with('to', 'foo', 'bar');

		$queue->migrateExpiredJobs('from', 'to');
	}


	public function testNotExpireJobsWhenExpireNull()
	{
		$queue = $this->getMock(RedisQueue::class, array('getTime', 'migrateAllExpiredJobs'), array($redis = m::mock(
            Database::class
        ), 'default', null));
		$redis->shouldReceive('connection')->andReturn($predis = m::mock(Client::class));
		$queue->setContainer(m::mock(Container::class));
		$queue->setExpire(null);
		$queue->expects($this->once())->method('getTime')->willReturn(1);
		$queue->expects($this->never())->method('migrateAllExpiredJobs');
		$predis->shouldReceive('lpop')->once()->with('queues:default')->andReturn('foo');
		$predis->shouldReceive('zadd')->once()->with('queues:default:reserved', 1, 'foo');

		$result = $queue->pop();
	}


	public function testExpireJobsWhenExpireSet()
	{
		$queue = $this->getMock(RedisQueue::class, array('getTime', 'migrateAllExpiredJobs'), array($redis = m::mock(
            Database::class
        ), 'default', null));
		$redis->shouldReceive('connection')->andReturn($predis = m::mock(Client::class));
		$queue->setContainer(m::mock(Container::class));
		$queue->setExpire(30);
		$queue->expects($this->once())->method('getTime')->willReturn(1);
		$queue->expects($this->once())->method('migrateAllExpiredJobs')->with($this->equalTo('queues:default'));
		$predis->shouldReceive('lpop')->once()->with('queues:default')->andReturn('foo');
		$predis->shouldReceive('zadd')->once()->with('queues:default:reserved', 31, 'foo');

		$result = $queue->pop();
	}

}
