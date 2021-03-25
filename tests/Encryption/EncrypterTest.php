<?php

use Illuminate\Encryption\Encrypter;
use L4\Tests\BackwardCompatibleTestCase;

class EncrypterTest extends BackwardCompatibleTestCase
{

    public function testEncryption()
    {
        $e = $this->getEncrypter();
        $this->assertNotEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $e->encrypt('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $encrypted = $e->encrypt('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $e->decrypt($encrypted));
    }


	public function testEncryptionWithCustomCipher()
	{
		$e = $this->getEncrypter();
		$this->assertNotEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $e->encrypt('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
		$encrypted = $e->encrypt('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
		$this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $e->decrypt($encrypted));
	}

    public function testExceptionThrownWhenPayloadIsInvalid()
    {
        $this->expectException(Illuminate\Encryption\DecryptException::class);
        $this->expectExceptionMessage("The payload is invalid.");
        $e = $this->getEncrypter();
        $payload = $e->encrypt('foo');
        $payload = str_shuffle($payload);
        $e->decrypt($payload);
    }


	protected function getEncrypter()
	{
		return new Encrypter(str_repeat('a', 32));
	}

}
