<?php

use L4\Tests\BackwardCompatibleTestCase;

class BcryptHasherTest extends BackwardCompatibleTestCase {

	public function testBasicHashing()
	{
		$hasher = new Illuminate\Hashing\BcryptHasher;
		$value = $hasher->make('password');
		$this->assertNotSame('password', $value);
		$this->assertTrue($hasher->check('password', $value));
		$this->assertNotTrue($hasher->needsRehash($value));
		$this->assertTrue($hasher->needsRehash($value, ['rounds' => 1]));
	}

}
