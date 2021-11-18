<?php namespace Illuminate\Foundation;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Composer {

	/**
	 * The filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The working path to regenerate from.
	 *
	 * @var string
	 */
	protected $workingPath;

	/**
	 * Create a new Composer manager instance.
	 *
	 * @param  \Illuminate\Filesystem\Filesystem  $files
	 * @param  string  $workingPath
	 * @return void
	 */
	public function __construct(Filesystem $files, $workingPath = null)
	{
		$this->files = $files;
		$this->workingPath = $workingPath;
	}

    /**
     * Regenerate the Composer autoloader files.
     *
     * @param string $extra
     *
     * @return Process
     */
	public function dumpAutoloads(string $extra = ''): Process
    {
        $command = trim($this->findComposer().' dump-autoload '.$extra);

		$process = $this->getProcess(explode(' ', $command));

		$process->run();

        return $process;
	}

	/**
	 * Regenerate the optimized Composer autoloader files.
	 *
	 * @return Process
	 */
	public function dumpOptimized(): Process
    {
		return $this->dumpAutoloads('--optimize');
	}

	/**
	 * Get the composer command for the environment.
	 *
	 * @return string
	 */
	protected function findComposer()
	{
		if ($this->files->exists($this->workingPath.'/composer.phar'))
		{
			return '"'.PHP_BINARY.'" composer.phar';
		}

		return 'composer';
	}

	/**
	 * Get a new Symfony process instance.
	 * @param string[] $commands
	 * @return \Symfony\Component\Process\Process
	 */
	protected function getProcess(array $commands = []): Process
    {
		return (new Process($commands, $this->workingPath))->setTimeout(null);
	}

	/**
	 * Set the working path used by the class.
	 *
	 * @param  string  $path
	 * @return $this
	 */
	public function setWorkingPath($path)
	{
		$this->workingPath = realpath($path);

		return $this;
	}

}
