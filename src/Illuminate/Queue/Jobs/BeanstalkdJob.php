<?php namespace Illuminate\Queue\Jobs;

use Illuminate\Container\Container;
use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

class BeanstalkdJob extends Job {

	/**
	 * The Pheanstalk instance.
	 *
	 * @var Pheanstalk
	 */
	protected $pheanstalk;

	/**
	 * The Pheanstalk job instance.
	 *
	 * @var PheanstalkJob
	 */
	protected $job;

    /**
     * Create a new job instance.
     *
     * @param Container     $container
     * @param  Pheanstalk   $pheanstalk
     * @param PheanstalkJob $job
     * @param string        $queue
     */
	public function __construct(Container $container,
                                Pheanstalk $pheanstalk,
                                PheanstalkJob $job,
                                string $queue)
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
		$priority = \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_PRIORITY;

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
	 * @return Container
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * Get the underlying Pheanstalk instance.
	 *
	 * @return Pheanstalk
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
