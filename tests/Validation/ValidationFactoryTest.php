<?php /** @noinspection PhpParamsInspection */

use Illuminate\Validation\Factory;

class ValidationFactoryTest extends \L4\Tests\BackwardCompatibleTestCase {

	public function testMakeMethodCreatesValidValidator()
	{
		$translator = $this->prophesize(Symfony\Contracts\Translation\TranslatorInterface::class);
		$factory = new Factory($translator->reveal());
		$validator = $factory->make(array('foo' => 'bar'), array('baz' => 'boom'));
		$this->assertEquals($translator->reveal(), $validator->getTranslator());
		$this->assertEquals(array('foo' => 'bar'), $validator->getData());
		$this->assertEquals(array('baz' => array('boom')), $validator->getRules());

		$presence = $this->prophesize(Illuminate\Validation\PresenceVerifierInterface::class);
		$noop1 = function() {};
		$noop2 = function() {};
		$noop3 = function() {};
		$factory->extend('foo', $noop1);
		$factory->extendImplicit('implicit', $noop2);
		$factory->replacer('replacer', $noop3);
		$factory->setPresenceVerifier($presence->reveal());
		$validator = $factory->make(array(), array());
		$this->assertEquals(array('foo' => $noop1, 'implicit' => $noop2), $validator->getExtensions());
		$this->assertEquals(array('replacer' => $noop3), $validator->getReplacers());
		$this->assertEquals($presence->reveal(), $validator->getPresenceVerifier());

		$presence = $this->prophesize(Illuminate\Validation\PresenceVerifierInterface::class);
		$factory->extend('foo', $noop1, 'foo!');
		$factory->extendImplicit('implicit', $noop2, 'implicit!');
		$factory->setPresenceVerifier($presence->reveal());
		$validator = $factory->make(array(), array());
		$this->assertEquals(array('foo' => $noop1, 'implicit' => $noop2), $validator->getExtensions());
		$this->assertEquals(array('foo' => 'foo!', 'implicit' => 'implicit!'), $validator->getFallbackMessages());
		$this->assertEquals($presence->reveal(), $validator->getPresenceVerifier());
	}


	public function testCustomResolverIsCalled()
	{
		unset($_SERVER['__validator.factory']);
		$translator = m::mock('Symfony\Contracts\Translation\TranslatorInterface');
		$factory = new Factory($translator);
		$factory->resolver(function($translator, $data, $rules)
		{
			$_SERVER['__validator.factory'] = true;
			return new Illuminate\Validation\Validator($translator, $data, $rules);
		});
		$validator = $factory->make(array('foo' => 'bar'), array('baz' => 'boom'));

		$this->assertTrue($_SERVER['__validator.factory']);
		$this->assertEquals($translator, $validator->getTranslator());
		$this->assertEquals(array('foo' => 'bar'), $validator->getData());
		$this->assertEquals(array('baz' => array('boom')), $validator->getRules());
		unset($_SERVER['__validator.factory']);
	}

}
