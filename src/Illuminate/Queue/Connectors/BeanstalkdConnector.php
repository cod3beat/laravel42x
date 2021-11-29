<?php namespace Illuminate\Queue\Connectors;

use Illuminate\Queue\BeanstalkdQueue;

class BeanstalkdConnector implements ConnectorInterface {

	/**
	 * Establish a queue connection.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Queue\QueueInterface
	 */
	public function connect(array $config)
	{
		$pheanstalk = new \Pheanstalk\Pheanstalk($config['host'], array_get($config, 'port', \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_PORT));

		return new BeanstalkdQueue(
			$pheanstalk, $config['queue'], array_get($config, 'ttr', \Pheanstalk\Contract\PheanstalkInterface::DEFAULT_TTR)
		);
	}

}
