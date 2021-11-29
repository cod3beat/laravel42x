<?php namespace Illuminate\Queue\Connectors;

use Illuminate\Queue\BeanstalkdQueue;
use Illuminate\Queue\QueueInterface;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Pheanstalk;

class BeanstalkdConnector implements ConnectorInterface {

	/**
	 * Establish a queue connection.
	 *
	 * @param  array  $config
	 * @return QueueInterface
	 */
	public function connect(array $config)
	{
        $pheanstalk = Pheanstalk::create($config['host'], array_get($config, 'port', PheanstalkInterface::DEFAULT_PORT));

		return new BeanstalkdQueue(
			$pheanstalk, $config['queue'], array_get($config, 'ttr', PheanstalkInterface::DEFAULT_TTR)
		);
	}

}
