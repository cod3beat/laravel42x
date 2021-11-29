<?php

use Illuminate\Queue\BeanstalkdQueue;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use L4\Tests\BackwardCompatibleTestCase;

class QueueBeanstalkdFactoryTest extends BackwardCompatibleTestCase
{
    /**
     * @test
     */
    public function creatingConnector(): void
    {
        $connetor = new BeanstalkdConnector();
        $queue = $connetor->connect([
            'host'  => 'localhost',
            'port'  => 11300,
            'queue' => 'secondary'
        ]);

        self::assertInstanceOf(BeanstalkdQueue::class, $queue);
        self::assertEquals('secondary', $queue->getQueue());
    }

    /**
     * @test
     */
    public function creatingDefaultConnector(): void
    {
        $connetor = new BeanstalkdConnector();
        $queue = $connetor->connect([
            'host'  => 'localhost',
            'queue' => 'default'
        ]);

        self::assertInstanceOf(BeanstalkdQueue::class, $queue);
        self::assertEquals('default', $queue->getQueue());
    }
}