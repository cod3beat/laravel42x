<?php

use Illuminate\Queue\BeanstalkdQueue;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use L4\Tests\BackwardCompatibleTestCase;

class QueueBeanstalkdFactoryTest extends BackwardCompatibleTestCase
{
    /**
     * @test
     */
    public function testCreatingConnector(): void
    {
        $connetor = new BeanstalkdConnector();
        $queue = $connetor->connect([
            'host'  => 'localhost',
            'port'  => 11300
        ]);

        self::assertInstanceOf(BeanstalkdQueue::class, $queue);
    }
}