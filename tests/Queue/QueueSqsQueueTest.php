<?php

use Aws\Sqs\SqsClient;
use Guzzle\Service\Resource\Model;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class QueueSqsQueueTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }

    protected function setUp(): void
    {
        $this->markTestSkipped();

        // Use Mockery to mock the SqsClient
        $this->sqs = m::mock('Aws\Sqs\SqsClient');

        $this->account = '1234567891011';
        $this->queueName = 'emails';
        $this->baseUrl = 'https://sqs.someregion.amazonaws.com';

		// This is how the modified getQueue builds the queueUrl
		$this->queueUrl = $this->baseUrl . '/' . $this->account . '/' . $this->queueName;

		$this->mockedJob = 'foo';
		$this->mockedData = ['data'];
		$this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
		$this->mockedDelay = 10;
		$this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
		$this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';

		$this->mockedSendMessageResponseModel = new Model([
            'Body' => $this->mockedPayload,
						      			'MD5OfBody' => md5($this->mockedPayload),
						      			'ReceiptHandle' => $this->mockedReceiptHandle,
						      			'MessageId' => $this->mockedMessageId,
						      			'Attributes' => ['ApproximateReceiveCount' => 1]
        ]);

		$this->mockedReceiveMessageResponseModel = new Model([
            'Messages' => [
                0 => [
												'Body' => $this->mockedPayload,
						     						'MD5OfBody' => md5($this->mockedPayload),
						      						'ReceiptHandle' => $this->mockedReceiptHandle,
						     						'MessageId' => $this->mockedMessageId
                ]
            ]
        ]);
	}


	public function testPopProperlyPopsJobOffOfSqs()
	{
		$queue = $this->getMock(SqsQueue::class, ['getQueue'], [$this->sqs, $this->queueName, $this->account]);
		$queue->setContainer(m::mock(Container::class));
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->willReturn($this->queueUrl);
		$this->sqs->shouldReceive('receiveMessage')->once()->with(
            ['QueueUrl' => $this->queueUrl, 'AttributeNames' => ['ApproximateReceiveCount']]
        )->andReturn($this->mockedReceiveMessageResponseModel);
		$result = $queue->pop($this->queueName);
		$this->assertInstanceOf(SqsJob::class, $result);
	}


	public function testDelayedPushWithDateTimeProperlyPushesJobOntoSqs()
	{
		$now = Carbon::now();
		$queue = $this->getMock(SqsQueue::class, ['createPayload', 'getSeconds', 'getQueue'], [$this->sqs, $this->queueName, $this->account]
        );
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->willReturn(
            $this->mockedPayload
        );
		$queue->expects($this->once())->method('getSeconds')->with($now)->willReturn(5);
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->willReturn($this->queueUrl);
		$this->sqs->shouldReceive('sendMessage')->once()->with(
            ['QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload, 'DelaySeconds' => 5]
        )->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->later($now->addSeconds(5), $this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}


	public function testDelayedPushProperlyPushesJobOntoSqs()
	{
		$queue = $this->getMock(SqsQueue::class, ['createPayload', 'getSeconds', 'getQueue'], [$this->sqs, $this->queueName, $this->account]
        );
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->willReturn(
            $this->mockedPayload
        );
		$queue->expects($this->once())->method('getSeconds')->with($this->mockedDelay)->willReturn($this->mockedDelay);
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->willReturn($this->queueUrl);
		$this->sqs->shouldReceive('sendMessage')->once()->with(
            ['QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay]
        )->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}


	public function testPushProperlyPushesJobOntoSqs()
	{
		$queue = $this->getMock(SqsQueue::class, ['createPayload', 'getQueue'], [$this->sqs, $this->queueName, $this->account]
        );
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->willReturn(
            $this->mockedPayload
        );
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->willReturn($this->queueUrl);
		$this->sqs->shouldReceive('sendMessage')->once()->with(
            ['QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload]
        )->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}

}
