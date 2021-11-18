<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Log\Writer;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogWriterTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testFileHandlerCanBeAdded()
    {
        $writer = new Writer($monolog = m::mock(Logger::class));
        $monolog->shouldReceive('pushHandler')->once()->with(m::type(StreamHandler::class));
        $writer->useFiles(__DIR__);
	}


	public function testRotatingFileHandlerCanBeAdded()
	{
		$writer = new Writer($monolog = m::mock(Logger::class));
		$monolog->shouldReceive('pushHandler')->once()->with(m::type(RotatingFileHandler::class));
		$writer->useDailyFiles(__DIR__, 5);
	}


	public function testErrorLogHandlerCanBeAdded()
	{
		$writer = new Writer($monolog = m::mock(Logger::class));
		$monolog->shouldReceive('pushHandler')->once()->with(m::type(ErrorLogHandler::class));
		$writer->useErrorLog();
	}


	public function testMagicMethodsPassErrorAdditionsToMonolog()
	{
		$writer = new Writer($monolog = m::mock(Logger::class));
		$monolog->shouldReceive('addError')->once()->with('foo')->andReturn('bar');

		$this->assertEquals('bar', $writer->error('foo'));
	}


	public function testWriterFiresEventsDispatcher()
	{
		$writer = new Writer($monolog = m::mock(Logger::class), $events = new Illuminate\Events\Dispatcher);
		$monolog->shouldReceive('addError')->once()->with('foo');

		$events->listen('illuminate.log', function($level, $message, array $context = [])
		{
			$_SERVER['__log.level']   = $level;
			$_SERVER['__log.message'] = $message;
			$_SERVER['__log.context'] = $context;
		});

		$writer->error('foo');
		$this->assertTrue(isset($_SERVER['__log.level']));
		$this->assertEquals('error', $_SERVER['__log.level']);
		unset($_SERVER['__log.level']);
		$this->assertTrue(isset($_SERVER['__log.message']));
		$this->assertEquals('foo', $_SERVER['__log.message']);
		unset($_SERVER['__log.message']);
		$this->assertTrue(isset($_SERVER['__log.context']));
		$this->assertEquals([], $_SERVER['__log.context']);
		unset($_SERVER['__log.context']);
	}


    public function testListenShortcutFailsWithNoDispatcher()
    {
        $this->expectException(RuntimeException::class);
        $writer = new Writer($monolog = m::mock(Logger::class));
        $writer->listen(
            function () {
            }
        );
    }


	public function testListenShortcut()
	{
		$writer = new Writer($monolog = m::mock(Logger::class), $events = m::mock(
            Dispatcher::class
        ));

		$callback = function() { return 'success'; };
		$events->shouldReceive('listen')->with('illuminate.log', $callback)->once();

		$writer->listen($callback);
	}

}
