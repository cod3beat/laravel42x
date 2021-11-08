<?php

use Illuminate\Pagination\BootstrapPresenter;
use Illuminate\Pagination\Paginator;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class PaginationBootstrapPresenterTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testPresenterCanBeCreated()
    {
        $presenter = $this->getPresenter();
    }


	public function testSimpleRangeIsReturnedWhenCantBuildSlier()
	{
		$presenter = $this->getMock(BootstrapPresenter::class, ['getPageRange', 'getPrevious', 'getNext'], [$paginator = $this->getPaginator()]
        );
		$presenter->expects($this->once())->method('getPageRange')->with($this->equalTo(1), $this->equalTo(2))->willReturn(
            'bar'
        );
		$presenter->expects($this->once())->method('getPrevious')->willReturn('foo');
		$presenter->expects($this->once())->method('getNext')->willReturn('baz');

		$this->assertEquals('foobarbaz', $presenter->render());
	}


	public function testGetPageRange()
	{
		$presenter = $this->getPresenter();
		$presenter->setCurrentPage(1);
		$content = $presenter->getPageRange(1, 2);

		$this->assertEquals('<li class="active"><span>1</span></li><li><a href="http://foo.com?page=2">2</a></li>', $content);
	}


	public function testBeginningSliderIsCreatedWhenCloseToStart()
	{
		$presenter = $this->getMock(BootstrapPresenter::class, ['getPageRange', 'getPrevious', 'getNext', 'getStart', 'getFinish'], [$paginator = $this->getPaginator()]
        );
		$presenter->setLastPage(14);
		$presenter->expects($this->once())->method('getFinish')->willReturn('finish');
		$presenter->expects($this->once())->method('getPrevious')->willReturn('previous');
		$presenter->expects($this->once())->method('getNext')->willReturn('next');
		$presenter->expects($this->once())->method('getPageRange')->with($this->equalTo(1), $this->equalTo(8))->willReturn(
            'range'
        );

		$this->assertEquals('previousrangefinishnext', $presenter->render());
	}


	public function testEndingSliderIsCreatedWhenCloseToStart()
	{
		$presenter = $this->getMock(BootstrapPresenter::class, ['getPageRange', 'getPrevious', 'getNext', 'getStart', 'getFinish'], [$paginator = $this->getPaginator()]
        );
		$presenter->setLastPage(14);
		$presenter->setCurrentPage(13);
		$presenter->expects($this->once())->method('getStart')->willReturn('start');
		$presenter->expects($this->once())->method('getPrevious')->willReturn('previous');
		$presenter->expects($this->once())->method('getNext')->willReturn('next');
		$presenter->expects($this->once())->method('getPageRange')->with($this->equalTo(6), $this->equalTo(14))->willReturn(
            'range'
        );

		$this->assertEquals('previousstartrangenext', $presenter->render());
	}


	public function testSliderIsCreatedWhenCloseToStart()
	{
		$presenter = $this->getMock(BootstrapPresenter::class, ['getPageRange', 'getPrevious', 'getNext', 'getStart', 'getFinish'], [$paginator = $this->getPaginator()]
        );
		$presenter->setLastPage(30);
		$presenter->setCurrentPage(15);
		$presenter->expects($this->once())->method('getStart')->willReturn('start');
		$presenter->expects($this->once())->method('getFinish')->willReturn('finish');
		$presenter->expects($this->once())->method('getPrevious')->willReturn('previous');
		$presenter->expects($this->once())->method('getNext')->willReturn('next');
		$presenter->expects($this->once())->method('getPageRange')->with($this->equalTo(12), $this->equalTo(18))->willReturn(
            'range'
        );

		$this->assertEquals('previousstartrangefinishnext', $presenter->render());
	}


	public function testPreviousLinkCanBeRendered()
	{
		$output = $this->getPresenter()->getPrevious();

		$this->assertEquals('<li class="disabled"><span>&laquo;</span></li>', $output);

		$presenter = $this->getPresenter();
		$presenter->setCurrentPage(2);
		$output = $presenter->getPrevious();

		$this->assertEquals('<li><a href="http://foo.com?page=1" rel="prev">&laquo;</a></li>', $output);
	}


	public function testNextLinkCanBeRendered()
	{
		$presenter = $this->getPresenter();
		$presenter->setCurrentPage(2);
		$output = $presenter->getNext();

		$this->assertEquals('<li class="disabled"><span>&raquo;</span></li>', $output);

		$presenter = $this->getPresenter();
		$presenter->setCurrentPage(1);
		$output = $presenter->getNext();

		$this->assertEquals('<li><a href="http://foo.com?page=2" rel="next">&raquo;</a></li>', $output);
	}


	public function testGetStart()
	{
		$presenter = $this->getPresenter();
		$output = $presenter->getStart();

		$this->assertEquals('<li class="active"><span>1</span></li><li><a href="http://foo.com?page=2">2</a></li><li class="disabled"><span>...</span></li>', $output);
	}


	public function testGetFinish()
	{
		$presenter = $this->getPresenter();
		$output = $presenter->getFinish();

		$this->assertEquals('<li class="disabled"><span>...</span></li><li class="active"><span>1</span></li><li><a href="http://foo.com?page=2">2</a></li>', $output);
	}


	public function testGetAdjacentRange()
	{
		$presenter = $this->getMock(BootstrapPresenter::class, ['getPageRange'], [$paginator = $this->getPaginator()]);
		$presenter->expects($this->once())->method('getPageRange')->with($this->equalTo(1), $this->equalTo(7))->willReturn(
            'foo'
        );
		$presenter->setCurrentPage(4);

		$this->assertEquals('foo', $presenter->getAdjacentRange());
	}



	protected function getPresenter()
	{
		return new BootstrapPresenter($this->getPaginator());
	}


	protected function getPaginator()
	{
		$paginator = m::mock(Paginator::class);
		$paginator->shouldReceive('getLastPage')->once()->andReturn(2);
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(1);
		$paginator->shouldReceive('getUrl')->andReturnUsing(function($page) { return 'http://foo.com?page='.$page; });
		return $paginator;
	}

}
