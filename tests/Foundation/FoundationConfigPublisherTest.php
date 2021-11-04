<?php

use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class FoundationConfigPublisherTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testPackageConfigPublishing()
    {
        $pub = new Illuminate\Foundation\ConfigPublisher($files = m::mock(\Illuminate\Filesystem\Filesystem::class), __DIR__);
        $pub->setPackagePath(__DIR__ . '/vendor');
        $files->shouldReceive('isDirectory')->once()->with(__DIR__ . '/vendor/foo/bar/src/config')->andReturn(true);
		$files->shouldReceive('isDirectory')->once()->with(__DIR__.'/packages/foo/bar')->andReturn(true);
		$files->shouldReceive('copyDirectory')->once()->with(__DIR__.'/vendor/foo/bar/src/config', __DIR__.'/packages/foo/bar')->andReturn(true);

		$this->assertTrue($pub->publishPackage('foo/bar'));

		$pub = new Illuminate\Foundation\ConfigPublisher($files2 = m::mock(\Illuminate\Filesystem\Filesystem::class), __DIR__);
		$files2->shouldReceive('isDirectory')->once()->with(__DIR__.'/custom-packages/foo/bar/src/config')->andReturn(true);
		$files2->shouldReceive('isDirectory')->once()->with(__DIR__.'/packages/foo/bar')->andReturn(true);
		$files2->shouldReceive('copyDirectory')->once()->with(__DIR__.'/custom-packages/foo/bar/src/config', __DIR__.'/packages/foo/bar')->andReturn(true);

		$this->assertTrue($pub->publishPackage('foo/bar', __DIR__.'/custom-packages'));
	}

}
