<?php namespace Illuminate\Queue\Jobs;

use Illuminate\Container\Container;

class BeanstalkdJob extends Job {

	/**
	 * The Pheanstalk instance.
	 *
	 * @var \Pheanstalk\Pheanstalk
	 */
	protected $pheanstalk;

	/**
	 * The Pheanstalk job instance.
	 *
	 * @var \Pheanstalk\Job
	 */
	protected $job;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Container\Container $container
     * @param  \Pheanstalk\Pheanstalk $pheanstalk
     * @param \Pheanstalk\Job $job
     * @param  string $queue
     */
	public function __construct(Container $container,
                                \Pheanstalk\Pheanstalk $pheanstalk,
                                \Pheanstalk\Job $job,
                                $queue)
	{
		$this->job = $job;
		$this->queue = $queue;
		$this->container = $container;
		$this->pheanstalk = $pheanstalk;
	}

	/**
	 * Fire the job.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->resolveAndFire(json_decode($this->getRawBody(), true));
	}

	/**
	 * Get the raw body string for the job.
	 *
	 * @return string
	 */
	public function getRawBody()
	{
		return $this->job->getData();
	}

	/**
	 * Delete the job from the queue.
	 *
	 * @return void
	 */
	public function delete()
	{
		parent::delete();

		$this->pheanstalk->delete($this->job);
	}

	/**
	 * Release the job back into the queue.
	 *
	 * @param  int   $delay
	 * @return void
	 */
	public function release($delay = 0)
	{
		$priority = \Pheanstalk\PheanstalkInterface::DEFAULT_PRIORITY;

		$this->pheanstalk->release($this->job, $priority, $delay);
	}

	/**
	 * Bury the job in the queue.
	 *
	 * @return void
	 */
	public function bury()
	{
		$this->pheanstalk->bury($this->job);
	}

	/**
	 * Get the number of times the job has been attempted.
	 *
	 * @return int
	 */
	public function attempts()
	{
		$stats = $this->pheanstalk->statsJob($this->job);

		return (int) $stats->reserves;
	}

	/**
	 * Get the job identifier.
	 *
	 * @return string
	 */
	public function getJobId()
	{
		return $this->job->getId();
	}

	/**
	 * Get the IoC container instance.
	 *
	 * @return \Illuminate\Container\Container
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * Get the underlying Pheanstalk instance.
	 *
	 * @return \Pheanstalk\Pheanstalk
	 */
	public function getPheanstalk()
	{
		return $this->pheanstalk;
	}

	/**
	 * Get the underlying Pheanstalk job.
	 *
	 * @return Job
	 */
	public function getPheanstalkJob()
	{
		return $this->job;
	}

}
