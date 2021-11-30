<?php namespace Illuminate\Queue;

use Illuminate\Queue\Jobs\BeanstalkdJob;
use Illuminate\Queue\Jobs\Job;
use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Exception\DeadlineSoonException;
use Pheanstalk\JobId;

class BeanstalkdQueue extends Queue implements QueueInterface {

	/**
	 * The Pheanstalk instance.
	 *
	 * @var \Pheanstalk\Pheanstalk
	 */
	protected $pheanstalk;

	/**
	 * The name of the default tube.
	 *
	 * @var string
	 */
	protected $default;

	/**
	 * The "time to run" for all pushed jobs.
	 *
	 * @var int
	 */
	protected $timeToRun;

	/**
	 * Create a new Beanstalkd queue instance.
	 *
	 * @param  \Pheanstalk\Pheanstalk  $pheanstalk
	 * @param  string  $default
	 * @param  int  $timeToRun
	 * @return void
	 */
	public function __construct(\Pheanstalk\Pheanstalk $pheanstalk, $default, $timeToRun)
	{
		$this->default = $default;
		$this->timeToRun = $timeToRun;
		$this->pheanstalk = $pheanstalk;
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param  string  $job
	 * @param  mixed   $data
	 * @param  string  $queue
	 * @return mixed
	 */
	public function push($job, $data = '', $queue = null)
	{
		return $this->pushRaw($this->createPayload($job, $data), $queue);
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param  string  $payload
	 * @param  string  $queue
	 * @param  array   $options
	 * @return mixed
	 */
	public function pushRaw($payload, $queue = null, array $options = array())
	{
		return $this->pheanstalk->useTube($this->getQueue($queue))->put(
			$payload, \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_PRIORITY, \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_DELAY, $this->timeToRun
		);
	}

	/**
	 * Push a new job onto the queue after a delay.
	 *
	 * @param  \DateTime|int  $delay
	 * @param  string  $job
	 * @param  mixed   $data
	 * @param  string  $queue
	 * @return mixed
	 */
	public function later($delay, $job, $data = '', $queue = null)
	{
		$payload = $this->createPayload($job, $data);

		$pheanstalk = $this->pheanstalk->useTube($this->getQueue($queue));

		return $pheanstalk->put($payload, \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_PRIORITY, $this->getSeconds($delay), $this->timeToRun);
	}

    /**
     * Pop the next job off of the queue.
     *
     * @param string $queue
     *
     * @return Job|null
     * @throws DeadlineSoonException
     */
	public function pop($queue = null)
	{
		$queue = $this->getQueue($queue);

		$job = $this->pheanstalk->watchOnly($queue)->reserveWithTimeout(0);

		if ($job instanceof \Pheanstalk\Job)
		{
			return new BeanstalkdJob($this->container, $this->pheanstalk, $job, $queue);
		}
	}

    /**
     * Delete a message from the Beanstalk queue.
     *
     * @param string $queue
     * @param string $id
     *
     * @return void
     */
	public function deleteMessage(string $queue, string $id): void
    {
		$this->pheanstalk->useTube($this->getQueue($queue))->delete(new JobId($id));
	}

	/**
	 * Get the queue or return the default.
	 *
	 * @param  string|null  $queue
	 * @return string
	 */
	public function getQueue(?string $queue = null): string
    {
		return $queue ?: $this->default;
	}

	/**
	 * Get the underlying Pheanstalk instance.
	 *
	 * @return \Pheanstalk\Pheanstalk
	 */
	public function getPheanstalk(): \Pheanstalk\Pheanstalk
    {
		return $this->pheanstalk;
	}

}
