<?php namespace Illuminate\Encryption;

use Symfony\Component\Security\Core\Util\StringUtils;
use Symfony\Component\Security\Core\Util\SecureRandom;

class Encrypter {

	/**
	 * The encryption key.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * The algorithm used for encryption.
	 *
	 * @var string
	 */
	protected $cipher;

	/**
	 * The mode used for encryption.
	 *
	 * @var string
	 */
	protected $mode = MCRYPT_MODE_CBC;

	/**
	 * The block size of the cipher.
	 *
	 * @var int
	 */
	protected $block = 16;

    /**
     * Create a new encrypter instance.
     *
     * @param  string $key
     * @param string $cipher
     *
     * @throws \Exception
     */
	public function __construct($key, $cipher = 'AES-256-CBC')
	{
        $key = (string) $key;
        if (static::supported($key, $cipher)) {
            $this->key = $key;
            $this->cipher = $cipher;
        } else {
            throw new \RuntimeException('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }
	}

    /**
     * @param $key
     * @param $cipher
     * @return bool
     */
    private static function supported($key, $cipher): bool
    {
        $length = mb_strlen($key, '8bit');
        return ($cipher === 'AES-128-CBC' && $length === 16) ||
            ($cipher === 'AES-256-CBC' && $length === 32);
    }

    /**
     * Encrypt the given value.
     *
     * @param  string $value
     * @param bool $serialize
     * @return string
     * @throws \Exception
     */
	public function encrypt($value, $serialize = true)
	{
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            $this->cipher, $this->key, 0, $iv
        );

        if ($value === false) {
            throw new \RuntimeException('Could not encrypt the data.');
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = $this->hash($iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (! \is_string($json)) {
            throw new \RuntimeException('Could not encrypt the data.');
        }

        return base64_encode($json);
	}

    /**
     * Encrypt a string without serialization.
     *
     * @param  string $value
     * @return string
     * @throws \Exception
     */
    public function encryptString($value): string
    {
        return $this->encrypt($value, false);
    }

	/**
	 * Pad and use mcrypt on the given value and input vector.
	 *
	 * @param  string  $value
	 * @param  string  $iv
	 * @return string
	 */
	protected function padAndMcrypt($value, $iv)
	{
		$value = $this->addPadding(serialize($value));

		return mcrypt_encrypt($this->cipher, $this->key, $value, $this->mode, $iv);
	}

    /**
     * Decrypt the given value.
     *
     * @param  string $payload
     * @return string
     *
     * @throws \Exception
     * @throws \Illuminate\Encryption\DecryptException
     */
	public function decrypt($payload)
	{
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        // Here we will decrypt the value. If we are able to successfully decrypt it
        // we will then unserialize it and return it out to the caller. If we are
        // unable to decrypt this value we will throw out an exception message.
        $decrypted = \openssl_decrypt(
            $payload['value'], $this->cipher, $this->key, 0, $iv
        );

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
	}

	/**
	 * Run the mcrypt decryption routine for the value.
	 *
	 * @param  string  $value
	 * @param  string  $iv
	 * @return string
	 *
	 * @throws \Exception
	 */
	protected function mcryptDecrypt($value, $iv)
	{
		try
		{
			return mcrypt_decrypt($this->cipher, $this->key, $value, $this->mode, $iv);
		}
		catch (\Exception $e)
		{
			throw new DecryptException($e->getMessage());
		}
	}

	/**
	 * Get the JSON array from the given payload.
	 *
	 * @param  string  $payload
	 * @return array
	 *
	 * @throws \Illuminate\Encryption\DecryptException
	 */
	protected function getJsonPayload($payload)
	{
		$payload = json_decode(base64_decode($payload), true);

		// If the payload is not valid JSON or does not have the proper keys set we will
		// assume it is invalid and bail out of the routine since we will not be able
		// to decrypt the given value. We'll also check the MAC for this encryption.
		if ( ! $payload || $this->invalidPayload($payload))
		{
			throw new DecryptException('Invalid data.');
		}

		if ( ! $this->validMac($payload))
		{
			throw new DecryptException('MAC is invalid.');
		}

		return $payload;
	}

	/**
	 * Determine if the MAC for the given payload is valid.
	 *
	 * @param  array  $payload
	 * @return bool
	 *
	 * @throws \RuntimeException
	 */
	protected function validMac(array $payload)
	{
		if ( ! function_exists('openssl_random_pseudo_bytes'))
		{
			throw new \RuntimeException('OpenSSL extension is required.');
		}

		$bytes = (new SecureRandom)->nextBytes(16);

		$calcMac = hash_hmac('sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true);

		return StringUtils::equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calcMac);
	}

	/**
	 * Create a MAC for the given value.
	 *
	 * @param  string  $iv
	 * @param  string  $value
	 * @return string
	 */
	protected function hash($iv, $value)
	{
		return hash_hmac('sha256', $iv.$value, $this->key);
	}

	/**
	 * Add PKCS7 padding to a given value.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function addPadding($value)
	{
		$pad = $this->block - (strlen($value) % $this->block);

		return $value.str_repeat(chr($pad), $pad);
	}

	/**
	 * Remove the padding from the given value.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function stripPadding($value)
	{
		$pad = ord($value[($len = strlen($value)) - 1]);

		return $this->paddingIsValid($pad, $value) ? substr($value, 0, $len - $pad) : $value;
	}

	/**
	 * Determine if the given padding for a value is valid.
	 *
	 * @param  string  $pad
	 * @param  string  $value
	 * @return bool
	 */
	protected function paddingIsValid($pad, $value)
	{
		$beforePad = strlen($value) - $pad;

		return substr($value, $beforePad) == str_repeat(substr($value, -1), $pad);
	}

	/**
	 * Verify that the encryption payload is valid.
	 *
	 * @param  array|mixed  $data
	 * @return bool
	 */
	protected function invalidPayload($data)
	{
		return ! is_array($data) || ! isset($data['iv']) || ! isset($data['value']) || ! isset($data['mac']);
	}

	/**
	 * Get the IV size for the cipher.
	 *
	 * @return int
	 */
	protected function getIvSize()
	{
		return mcrypt_get_iv_size($this->cipher, $this->mode);
	}

	/**
	 * Get the random data source available for the OS.
	 *
	 * @return int
	 */
	protected function getRandomizer()
	{
		if (defined('MCRYPT_DEV_URANDOM')) return MCRYPT_DEV_URANDOM;

		if (defined('MCRYPT_DEV_RANDOM')) return MCRYPT_DEV_RANDOM;

		mt_srand();

		return MCRYPT_RAND;
	}

	/**
	 * Set the encryption key.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function setKey($key)
	{
		$this->key = (string) $key;
	}

	/**
	 * Set the encryption cipher.
	 *
	 * @param  string  $cipher
	 * @return void
	 */
	public function setCipher($cipher)
	{
		$this->cipher = $cipher;

		$this->updateBlockSize();
	}

	/**
	 * Set the encryption mode.
	 *
	 * @param  string  $mode
	 * @return void
	 */
	public function setMode($mode)
	{
		$this->mode = $mode;

		$this->updateBlockSize();
	}

	/**
	 * Update the block size for the current cipher and mode.
	 *
	 * @return void
	 */
	protected function updateBlockSize()
	{
		$this->block = mcrypt_get_iv_size($this->cipher, $this->mode);
	}

}
