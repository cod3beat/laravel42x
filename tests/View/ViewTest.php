<?php

use Illuminate\Support\Contracts\ArrayableInterface;
use Illuminate\Support\Contracts\RenderableInterface;
use Illuminate\Support\MessageBag;
use Illuminate\View\Engines\EngineInterface;
use Illuminate\View\Factory;
use Illuminate\View\View;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class ViewTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testDataCanBeSetOnView()
    {
        $view = new View(
            m::mock(Factory::class),
            m::mock(EngineInterface::class),
            'view',
            'path',
            array()
        );
        $view->with('foo', 'bar');
        $view->with(array('baz' => 'boom'));
		$this->assertEquals(array('foo' => 'bar', 'baz' => 'boom'), $view->getData());


		$view = new View(m::mock(Factory::class), m::mock(
            EngineInterface::class
        ), 'view', 'path', array());
		$view->withFoo('bar')->withBaz('boom');
		$this->assertEquals(array('foo' => 'bar', 'baz' => 'boom'), $view->getData());
	}


	public function testRenderProperlyRendersView()
	{
		$view = $this->getView();
		$view->getFactory()->shouldReceive('incrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('callComposer')->once()->ordered()->with($view);
		$view->getFactory()->shouldReceive('getShared')->once()->andReturn(array('shared' => 'foo'));
		$view->getEngine()->shouldReceive('get')->once()->with('path', array('foo' => 'bar', 'shared' => 'foo'))->andReturn('contents');
		$view->getFactory()->shouldReceive('decrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('flushSectionsIfDoneRendering')->once();

		$me = $this;
		$callback = function(View $rendered, $contents) use ($me, $view)
		{
			$me->assertEquals($view, $rendered);
			$me->assertEquals('contents', $contents);
		};

		$this->assertEquals('contents', $view->render($callback));
	}


	public function testRenderSectionsReturnsEnvironmentSections()
	{
		$view = m::mock('Illuminate\View\View[render]', array(
			m::mock(Factory::class),
			m::mock(EngineInterface::class),
			'view',
			'path',
			array(),
		));

		$view->shouldReceive('render')->with(m::type('Closure'))->once()->andReturn($sections = array('foo' => 'bar'));

		$this->assertEquals($sections, $view->renderSections());
	}


	public function testSectionsAreNotFlushedWhenNotDoneRendering()
	{
		$view = $this->getView();
		$view->getFactory()->shouldReceive('incrementRender')->twice();
		$view->getFactory()->shouldReceive('callComposer')->twice()->with($view);
		$view->getFactory()->shouldReceive('getShared')->twice()->andReturn(array('shared' => 'foo'));
		$view->getEngine()->shouldReceive('get')->twice()->with('path', array('foo' => 'bar', 'shared' => 'foo'))->andReturn('contents');
		$view->getFactory()->shouldReceive('decrementRender')->twice();
		$view->getFactory()->shouldReceive('flushSectionsIfDoneRendering')->twice();

		$this->assertEquals('contents', $view->render());
		$this->assertEquals('contents', (string) $view);
	}


	public function testViewNestBindsASubView()
	{
		$view = $this->getView();
		$view->getFactory()->shouldReceive('make')->once()->with('foo', array('data'));
		$result = $view->nest('key', 'foo', array('data'));

		$this->assertInstanceOf(View::class, $result);
	}


	public function testViewAcceptsArrayableImplementations()
	{
		$arrayable = m::mock(ArrayableInterface::class);
		$arrayable->shouldReceive('toArray')->once()->andReturn(array('foo' => 'bar', 'baz' => array('qux', 'corge')));

		$view = new View(
			m::mock(Factory::class),
			m::mock(EngineInterface::class),
			'view',
			'path',
			$arrayable
		);

		$this->assertEquals('bar', $view->foo);
		$this->assertEquals(array('qux', 'corge'), $view->baz);
	}


	public function testViewGettersSetters()
	{
		$view = $this->getView();
		$this->assertEquals($view->getName(), 'view');
		$this->assertEquals($view->getPath(), 'path');
		$data = $view->getData();
		$this->assertEquals($data['foo'], 'bar');
		$view->setPath('newPath');
		$this->assertEquals($view->getPath(), 'newPath');
	}


	public function testViewArrayAccess()
	{
		$view = $this->getView();
		$this->assertInstanceOf('ArrayAccess', $view);
		$this->assertTrue($view->offsetExists('foo'));
		$this->assertEquals($view->offsetGet('foo'), 'bar');
		$view->offsetSet('foo','baz');
		$this->assertEquals($view->offsetGet('foo'), 'baz');
		$view->offsetUnset('foo');
		$this->assertFalse($view->offsetExists('foo'));
	}


	public function testViewMagicMethods()
	{
		$view = $this->getView();
		$this->assertTrue(isset($view->foo));
		$this->assertEquals($view->foo, 'bar');
		$view->foo = 'baz';
		$this->assertEquals($view->foo, 'baz');
		$this->assertEquals($view['foo'], $view->foo);
		unset($view->foo);
		$this->assertFalse(isset($view->foo));
		$this->assertFalse($view->offsetExists('foo'));
	}


	public function testViewBadMethod()
	{
		$this->expectException('BadMethodCallException');
		$view = $this->getView();
		$view->badMethodCall();
	}


	public function testViewGatherDataWithRenderable()
	{
		$view = $this->getView();
		$view->getFactory()->shouldReceive('incrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('callComposer')->once()->ordered()->with($view);
		$view->getFactory()->shouldReceive('getShared')->once()->andReturn(array('shared' => 'foo'));
		$view->getEngine()->shouldReceive('get')->once()->andReturn('contents');
		$view->getFactory()->shouldReceive('decrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('flushSectionsIfDoneRendering')->once();

		$view->renderable = m::mock(RenderableInterface::class);
		$view->renderable->shouldReceive('render')->once()->andReturn('text');
		$this->assertEquals('contents', $view->render());
	}


	public function testViewRenderSections()
	{
		$view = $this->getView();
		$view->getFactory()->shouldReceive('incrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('callComposer')->once()->ordered()->with($view);
		$view->getFactory()->shouldReceive('getShared')->once()->andReturn(array('shared' => 'foo'));
		$view->getEngine()->shouldReceive('get')->once()->andReturn('contents');
		$view->getFactory()->shouldReceive('decrementRender')->once()->ordered();
		$view->getFactory()->shouldReceive('flushSectionsIfDoneRendering')->once();

		$view->getFactory()->shouldReceive('getSections')->once()->andReturn(array('foo','bar'));
		$sections = $view->renderSections();
		$this->assertEquals($sections[0], 'foo');
		$this->assertEquals($sections[1], 'bar');
	}


	public function testWithErrors()
    {
        $view = $this->getView();
        $errors = array('foo' => 'bar', 'qu' => 'ux');
        $this->assertSame($view, $view->withErrors($errors));
        $this->assertInstanceOf(MessageBag::class, $view->errors);
        $foo = $view->errors->get('foo');
        $this->assertEquals($foo[0], 'bar');
        $qu = $view->errors->get('qu');
        $this->assertEquals($qu[0], 'ux');
        $data = array('foo' => 'baz');
        $this->assertSame($view, $view->withErrors(new MessageBag($data)));
        $foo = $view->errors->get('foo');
        $this->assertEquals($foo[0], 'baz');
    }


	protected function getView()
	{
		return new View(
			m::mock(Factory::class),
			m::mock(EngineInterface::class),
			'view',
			'path',
			array('foo' => 'bar')
		);
	}

}
