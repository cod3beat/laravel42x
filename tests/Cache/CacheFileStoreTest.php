<?php

use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;
use L4\Tests\BackwardCompatibleTestCase;

class CacheFileStoreTest extends BackwardCompatibleTestCase {

	public function testNullIsReturnedIfFileDoesntExist()
	{
		$files = $this->mockFilesystem();
		$files->expects($this->once())->method('exists')->willReturn(false);
		$store = new FileStore($files, __DIR__);
		$value = $store->get('foo');
		$this->assertNull($value);
	}


	public function testPutCreatesMissingDirectories()
	{
		$files = $this->mockFilesystem();
		$md5 = md5('foo');
		$full_dir = __DIR__.'/'.substr($md5, 0, 2).'/'.substr($md5, 2, 2);
		$files->expects($this->once())->method('makeDirectory')->with($this->equalTo($full_dir), $this->equalTo(0777), $this->equalTo(true));
		$files->expects($this->once())->method('put')->with($this->equalTo($full_dir.'/'.$md5));
		$store = new FileStore($files, __DIR__);
		$store->put('foo', '0000000000', 0);
	}


	public function testExpiredItemsReturnNull()
	{
		$files = $this->mockFilesystem();
		$files->expects($this->once())->method('exists')->willReturn(true);
		$contents = '0000000000';
		$files->expects($this->once())->method('get')->willReturn($contents);
		$store = $this->getMock(FileStore::class, array('forget'), array($files, __DIR__));
		$store->expects($this->once())->method('forget');
		$value = $store->get('foo');
		$this->assertNull($value);
	}


	public function testValidItemReturnsContents()
	{
		$files = $this->mockFilesystem();
		$files->expects($this->once())->method('exists')->willReturn(true);
		$contents = '9999999999'.serialize('Hello World');
		$files->expects($this->once())->method('get')->willReturn($contents);
		$store = new FileStore($files, __DIR__);
		$this->assertEquals('Hello World', $store->get('foo'));
	}


	public function testStoreItemProperlyStoresValues()
	{
		$files = $this->mockFilesystem();
		$store = $this->getMock(FileStore::class, array('expiration'), array($files, __DIR__));
		$store->expects($this->once())->method('expiration')->with($this->equalTo(10))->willReturn(1111111111);
		$contents = '1111111111'.serialize('Hello World');
		$md5 = md5('foo');
		$cache_dir = substr($md5, 0, 2).'/'.substr($md5, 2, 2);
		$files->expects($this->once())->method('put')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$md5), $this->equalTo($contents));
		$store->put('foo', 'Hello World', 10);
	}


	public function testForeversAreStoredWithHighTimestamp()
	{
		$files = $this->mockFilesystem();
		$contents = '9999999999'.serialize('Hello World');
		$md5 = md5('foo');
		$cache_dir = substr($md5, 0, 2).'/'.substr($md5, 2, 2);
		$files->expects($this->once())->method('put')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$md5), $this->equalTo($contents));
		$store = new FileStore($files, __DIR__);
		$store->forever('foo', 'Hello World', 10);
	}


	public function testRemoveDeletesFileDoesntExist()
	{
		$files = $this->mockFilesystem();
		$md5 = md5('foobull');
		$cache_dir = substr($md5, 0, 2).'/'.substr($md5, 2, 2);
		$files->expects($this->once())->method('exists')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$md5))->willReturn(
            false
        );
		$store = new FileStore($files, __DIR__);
		$store->forget('foobull');
	}


	public function testRemoveDeletesFile()
	{
		$files = $this->mockFilesystem();
		$md5 = md5('foobar');
		$cache_dir = substr($md5, 0, 2).'/'.substr($md5, 2, 2);
		$store = new FileStore($files, __DIR__);
		$store->put('foobar', 'Hello Baby', 10);
		$files->expects($this->once())->method('exists')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$md5))->willReturn(
            true
        );
		$files->expects($this->once())->method('delete')->with($this->equalTo(__DIR__.'/'.$cache_dir.'/'.$md5));
		$store->forget('foobar');
	}


	public function testFlushCleansDirectory()
	{
		$files = $this->mockFilesystem();
		$files->expects($this->once())->method('isDirectory')->with($this->equalTo(__DIR__))->willReturn(true);
		$files->expects($this->once())->method('directories')->with($this->equalTo(__DIR__))->willReturn(array('foo'));
		$files->expects($this->once())->method('deleteDirectory')->with($this->equalTo('foo'));

		$store = new FileStore($files, __DIR__);
		$store->flush();
	}


	public function testFlushIgnoreNonExistingDirectory()
	{
		$files = $this->mockFilesystem();
		$files->expects($this->once())->method('isDirectory')->with($this->equalTo(__DIR__ . '--wrong'))->willReturn(
            false
        );

		$store = new FileStore($files, __DIR__ . '--wrong');
		$store->flush();
	}


	protected function mockFilesystem()
	{
		return $this->getMock(Filesystem::class);
	}

}
