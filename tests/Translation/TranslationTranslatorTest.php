<?php

use Illuminate\Translation\LoaderInterface;
use Illuminate\Translation\MessageSelector;
use Illuminate\Translation\Translator;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class TranslationTranslatorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testHasMethodReturnsFalseWhenReturnedTranslationIsNull()
    {
        $t = $this->getMock(Translator::class, ['get'], [$this->getLoader(), 'en']);
        $t->expects($this->once())->method('get')->with(
            $this->equalTo('foo'),
            $this->equalTo([]),
            $this->equalTo('bar')
        )->willReturn('foo');
        $this->assertFalse($t->has('foo', 'bar'));

		$t = $this->getMock(Translator::class, ['get'], [$this->getLoader(), 'en', 'sp']);
		$t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('bar'))->willReturn(
            'bar'
        );
		$this->assertTrue($t->has('foo', 'bar'));
	}


	public function testGetMethodProperlyLoadsAndRetrievesItem()
	{
		$t = $this->getMock(Translator::class, null, [$this->getLoader(), 'en'], '', true, true, true, false, true);
		$t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(
            ['foo' => 'foo', 'baz' => 'breeze :foo']
        );
		$this->assertEquals('breeze bar', $t->get('foo::bar.baz', ['foo' => 'bar'], 'en'));
		$this->assertEquals('foo', $t->get('foo::bar.foo'));
	}


	public function testGetMethodProperlyLoadsAndRetrievesItemWithLongestReplacementsFirst()
	{
		$t = $this->getMock(Translator::class, null, [$this->getLoader(), 'en'], '', true, true, true, false, true);
		$t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(
            ['foo' => 'foo', 'baz' => 'breeze :foo :foobar']
        );
		$this->assertEquals('breeze bar taylor', $t->get('foo::bar.baz', ['foo' => 'bar', 'foobar' => 'taylor'], 'en'));
		$this->assertEquals('foo', $t->get('foo::bar.foo'));
	}


	public function testGetMethodProperlyLoadsAndRetrievesItemForGlobalNamespace()
	{
		$t = $this->getMock(Translator::class, null, [$this->getLoader(), 'en'], '', true, true, true, false, true);
		$t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['bar' => 'breeze :foo']);
		$this->assertEquals('breeze bar', $t->get('foo.bar', ['foo' => 'bar']));
	}


	public function testChoiceMethodProperlyLoadsAndRetrievesItem()
	{
		$t = $this->getMock(Translator::class, ['get'], [$this->getLoader(), 'en']);
		$t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo(['replace']), $this->equalTo('en'))->willReturn(
            'line'
        );
		$t->setSelector($selector = m::mock(MessageSelector::class));
		$selector->shouldReceive('choose')->once()->with('line', 10, 'en')->andReturn('choiced');

		$t->choice('foo', 10, ['replace']);
	}

    // TEST FROM SYMFONY

    /**
     * @test
     * @dataProvider getChooseTests
     */
    public function choice($expected, $id, $number): void
    {
        $loader = $this->prophesize(LoaderInterface::class);
        $translator = new Translator($loader->reveal(), 'en');
        $this->assertEquals($expected, $translator->choice($id, $number));
	}

    public function testReturnMessageIfExactlyOneStandardRuleIsGiven()
    {
        $loader = $this->prophesize(LoaderInterface::class);
        $translator = new Translator($loader->reveal(), 'en');

        $this->assertEquals('There are two apples', $translator->choice('There are two apples', 2));
    }

    /**
     * @dataProvider getNonMatchingMessages
     */
    public function testThrowExceptionIfMatchingMessageCannotBeFound($id, $number)
    {
        $this->expectException(InvalidArgumentException::class);
        $loader = $this->prophesize(LoaderInterface::class);
        $translator = new Translator($loader->reveal(), 'en');

        $translator->choice($id, $number);
    }

	protected function getLoader()
	{
		return m::mock(LoaderInterface::class);
	}

    public function getNonMatchingMessages()
    {
        return [
            ['{0} There are no apples|{1} There is one apple', 2],
            ['{1} There is one apple|]1,Inf] There are %count% apples', 0],
            ['{1} There is one apple|]2,Inf] There are %count% apples', 2],
            ['{0} There are no apples|There is one apple', 2],
        ];
    }

    public function getChooseTests()
    {
        return [
            ['There are no apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0],
            ['There are no apples', '{0}     There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0],
            ['There are no apples', '{0}There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0],

            ['There is one apple', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 1],

            ['There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 10],
            ['There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf]There are %count% apples', 10],
            ['There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf]     There are %count% apples', 10],

            ['There are %count% apples', 'There is one apple|There are %count% apples', 0],
            ['There is one apple', 'There is one apple|There are %count% apples', 1],
            ['There are %count% apples', 'There is one apple|There are %count% apples', 10],

            ['There are %count% apples', 'one: There is one apple|more: There are %count% apples', 0],
            ['There is one apple', 'one: There is one apple|more: There are %count% apples', 1],
            ['There are %count% apples', 'one: There is one apple|more: There are %count% apples', 10],

            ['There are no apples', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 0],
            ['There is one apple', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 1],
            ['There are %count% apples', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 10],

            ['', '{0}|{1} There is one apple|]1,Inf] There are %count% apples', 0],
            ['', '{0} There are no apples|{1}|]1,Inf] There are %count% apples', 1],

            // Indexed only tests which are Gettext PoFile* compatible strings.
            ['There are %count% apples', 'There is one apple|There are %count% apples', 0],
            ['There is one apple', 'There is one apple|There are %count% apples', 1],
            ['There are %count% apples', 'There is one apple|There are %count% apples', 2],

            // Tests for float numbers
            ['There is almost one apple', '{0} There are no apples|]0,1[ There is almost one apple|{1} There is one apple|[1,Inf] There is more than one apple', 0.7],
            ['There is one apple', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1],
            ['There is more than one apple', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1.7],
            ['There are no apples', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0],
            ['There are no apples', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0.0],
            ['There are no apples', '{0.0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0],

            // Test texts with new-lines
            // with double-quotes and \n in id & double-quotes and actual newlines in text
            ["This is a text with a\n            new-line in it. Selector = 0.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 0],
            // with double-quotes and \n in id and single-quotes and actual newlines in text
            ["This is a text with a\n            new-line in it. Selector = 1.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 1],
            ["This is a text with a\n            new-line in it. Selector > 1.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 5],
            // with double-quotes and id split accros lines
            ['This is a text with a
            new-line in it. Selector = 1.', '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 1],
            // with single-quotes and id split accros lines
            ['This is a text with a
            new-line in it. Selector > 1.', '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 5],
            // with single-quotes and \n in text
            ['This is a text with a\nnew-line in it. Selector = 0.', '{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\nnew-line in it. Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.', 0],
            // with double-quotes and id split accros lines
            ["This is a text with a\nnew-line in it. Selector = 1.", "{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\nnew-line in it. Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.", 1],
            // esacape pipe
            ['This is a text with | in it. Selector = 0.', '{0}This is a text with || in it. Selector = 0.|{1}This is a text with || in it. Selector = 1.', 0],
            // Empty plural set (2 plural forms) from a .PO file
            ['', '|', 1],
            // Empty plural set (3 plural forms) from a .PO file
            ['', '||', 1],
        ];
    }
}
