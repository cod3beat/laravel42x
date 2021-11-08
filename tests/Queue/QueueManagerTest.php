<?php

use Illuminate\Encryption\Encrypter;
use Illuminate\Queue\QueueManager;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class QueueManagerTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testDefaultConnectionCanBeResolved()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
				'queue.connections.sync' => ['driver' => 'sync'],
            ],
			'encrypter' => $encrypter = m::mock(Encrypter::class),
        ];

		$manager = new QueueManager($app);
		$connector = m::mock('StdClass');
		$queue = m::mock('StdClass');
		$connector->shouldReceive('connect')->once()->with(['driver' => 'sync'])->andReturn($queue);
		$manager->addConnector('sync', function() use ($connector) { return $connector; });
		$queue->shouldReceive('setContainer')->once()->with($app);
		$queue->shouldReceive('setEncrypter')->once()->with($encrypter);

		$this->assertSame($queue, $manager->connection('sync'));
	}


	public function testOtherConnectionCanBeResolved()
	{
		$app = [
			'config' => [
				'queue.default' => 'sync',
				'queue.connections.foo' => ['driver' => 'bar'],
            ],
			'encrypter' => $encrypter = m::mock(Encrypter::class),
        ];

		$manager = new QueueManager($app);
		$connector = m::mock('StdClass');
		$queue = m::mock('StdClass');
		$connector->shouldReceive('connect')->once()->with(['driver' => 'bar'])->andReturn($queue);
		$manager->addConnector('bar', function() use ($connector) { return $connector; });
		$queue->shouldReceive('setContainer')->once()->with($app);
		$queue->shouldReceive('setEncrypter')->once()->with($encrypter);

		$this->assertSame($queue, $manager->connection('foo'));
	}

}
