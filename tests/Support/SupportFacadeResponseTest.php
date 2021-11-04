<?php

use Illuminate\Support\Facades\Response;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class SupportFacadeResponseTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testArrayableSendAsJson()
    {
        $data = m::mock(\Illuminate\Support\Contracts\ArrayableInterface::class);
        $data->shouldReceive('toArray')->andReturn(array('foo' => 'bar'));

		$response = Response::json($data);
		$this->assertEquals('{"foo":"bar"}', $response->getContent());
	}

}
