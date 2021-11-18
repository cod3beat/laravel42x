<?php namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Composer;
use Illuminate\View\Engines\CompilerEngine;
use Symfony\Component\Console\Input\InputOption;

class OptimizeCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'optimize';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Optimize the framework for better performance";

	/**
	 * The composer instance.
	 *
	 * @var \Illuminate\Foundation\Composer
	 */
	protected $composer;

	/**
	 * Create a new optimize command instance.
	 *
	 * @param  \Illuminate\Foundation\Composer  $composer
	 * @return void
	 */
	public function __construct(Composer $composer)
	{
		parent::__construct();

		$this->composer = $composer;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->info('Generating optimized class loader');

		if ($this->option('psr'))
		{
			$this->composer->dumpAutoloads();
		}
        elseif ($this->option('apcu')) {
            $this->composer->dumpAutoloads('--optimize --apcu');
        }
		else
		{
			$this->composer->dumpOptimized();
		}

		if ($this->option('force') || ! $this->laravel['config']['app.debug'])
		{
			$this->info('Compiling views');

			$this->compileViews();
		}
		else
		{
			$this->call('clear-compiled');
		}
	}

	/**
	 * Compile all view files.
	 *
	 * @return void
	 */
	protected function compileViews()
	{
		foreach ($this->laravel['view']->getFinder()->getPaths() as $path)
		{
			foreach ($this->laravel['files']->allFiles($path) as $file)
			{
				try
				{
					$engine = $this->laravel['view']->getEngineFromPath($file);
				}
				catch (\InvalidArgumentException $e)
				{
					continue;
				}

				if ($engine instanceof CompilerEngine)
				{
					$engine->getCompiler()->compile($file);
				}
			}
		}
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
            ['force', null, InputOption::VALUE_NONE, 'Force the compiled class file to be written.'],

            ['psr', null, InputOption::VALUE_NONE, 'Do not optimize Composer dump-autoload.'],

            ['apcu', null, InputOption::VALUE_NONE, 'Optimize with APCU']
		);
	}

}
