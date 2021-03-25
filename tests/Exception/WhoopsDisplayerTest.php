<?php

use Illuminate\Exception\WhoopsDisplayer;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Whoops\Run;

class WhoopsDisplayerTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testStatusAndHeadersAreSetInResponse()
    {
        $mockWhoops = m::mock($originalWhoops = new Run());
        $mockWhoops->handleException(new Exception());
        $mockWhoops->shouldReceive('handleException')->andReturn('response content');
        $displayer = new WhoopsDisplayer($originalWhoops, false);
        $headers = array('X-My-Test-Header' => 'HeaderValue');
        $exception = new HttpException(401, 'Unauthorized', null, $headers);
        $response = $displayer->display($exception);

        $this->assertTrue(
            $response->headers->has('X-My-Test-Header'),
            "response headers should include headers provided to the exception"
        );
        $this->assertEquals(
            'HeaderValue',
            $response->headers->get('X-My-Test-Header'),
            "response header values should match those provided to the exception"
        );
        $this->assertEquals(
            401,
            $response->getStatusCode(),
            "response status should match the status provided to the exception"
        );
	}

}
