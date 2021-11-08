<?php /** @noinspection PhpParamsInspection */

use Illuminate\Validation\Factory;
use L4\Tests\BackwardCompatibleTestCase;

class ValidationFactoryTest extends BackwardCompatibleTestCase {

	public function testMakeMethodCreatesValidValidator()
	{
		$translator = $this->prophesize(Symfony\Contracts\Translation\TranslatorInterface::class);
		$factory = new Factory($translator->reveal());
		$validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);
		$this->assertEquals($translator->reveal(), $validator->getTranslator());
		$this->assertEquals(['foo' => 'bar'], $validator->getData());
		$this->assertEquals(['baz' => ['boom']], $validator->getRules());

		$presence = $this->prophesize(Illuminate\Validation\PresenceVerifierInterface::class);
		$noop1 = function() {};
		$noop2 = function() {};
		$noop3 = function() {};
		$factory->extend('foo', $noop1);
		$factory->extendImplicit('implicit', $noop2);
		$factory->replacer('replacer', $noop3);
		$factory->setPresenceVerifier($presence->reveal());
		$validator = $factory->make([], []);
		$this->assertEquals(['foo' => $noop1, 'implicit' => $noop2], $validator->getExtensions());
		$this->assertEquals(['replacer' => $noop3], $validator->getReplacers());
		$this->assertEquals($presence->reveal(), $validator->getPresenceVerifier());

		$presence = $this->prophesize(Illuminate\Validation\PresenceVerifierInterface::class);
		$factory->extend('foo', $noop1, 'foo!');
		$factory->extendImplicit('implicit', $noop2, 'implicit!');
		$factory->setPresenceVerifier($presence->reveal());
		$validator = $factory->make([], []);
		$this->assertEquals(['foo' => $noop1, 'implicit' => $noop2], $validator->getExtensions());
		$this->assertEquals(['foo' => 'foo!', 'implicit' => 'implicit!'], $validator->getFallbackMessages());
		$this->assertEquals($presence->reveal(), $validator->getPresenceVerifier());
	}


	public function testCustomResolverIsCalled()
	{
		unset($_SERVER['__validator.factory']);
		$translator = $this->prophesize(Symfony\Contracts\Translation\TranslatorInterface::class);
		$factory = new Factory($translator->reveal());
		$factory->resolver(function($translator, $data, $rules)
		{
			$_SERVER['__validator.factory'] = true;
			return new Illuminate\Validation\Validator($translator, $data, $rules);
		});
		$validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);

		$this->assertTrue($_SERVER['__validator.factory']);
		$this->assertEquals($translator->reveal(), $validator->getTranslator());
		$this->assertEquals(['foo' => 'bar'], $validator->getData());
		$this->assertEquals(['baz' => ['boom']], $validator->getRules());
		unset($_SERVER['__validator.factory']);
	}

}
