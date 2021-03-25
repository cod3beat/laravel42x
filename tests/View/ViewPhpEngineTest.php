<?php

use Illuminate\View\Engines\PhpEngine;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class ViewPhpEngineTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testViewsMayBeProperlyRendered()
    {
        $engine = new PhpEngine;
        $this->assertEquals("Hello World\n", $engine->get(__DIR__ . '/fixtures/basic.php'));
    }

}
