<?php

use Illuminate\Validation\Validator;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidationValidatorTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testSometimesWorksOnNestedArrays()
    {
        $trans = $this->getRealTranslator();
        $v = new Validator(
            $trans,
            ['foo' => ['bar' => ['baz' => '']]],
            ['foo.bar.baz' => 'sometimes|required']
        );
        $this->assertFalse($v->passes());
		$this->assertEquals(['foo.bar.baz' => ['Required' => []]], $v->failed());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => ['bar' => ['baz' => 'nonEmpty']]], ['foo.bar.baz' => 'sometimes|required']);
		$this->assertTrue($v->passes());
	}


	public function testSometimesWorksOnArrays()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => ['bar', 'baz', 'moo']], ['foo' => 'sometimes|required|between:5,10']);
		$this->assertFalse($v->passes());
		$this->assertNotEmpty($v->failed());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => ['bar', 'baz', 'moo', 'pew', 'boom']], ['foo' => 'sometimes|required|between:5,10']
        );
		$this->assertTrue($v->passes());
	}


	public function testHasFailedValidationRules()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'bar', 'baz' => 'boom'], ['foo' => 'Same:baz']);
		$this->assertFalse($v->passes());
		$this->assertEquals(['foo' => ['Same' => ['baz']]], $v->failed());
	}


	public function testHasNotFailedValidationRules()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, ['foo' => 'taylor'], ['name' => 'Confirmed']);
		$this->assertTrue($v->passes());
		$this->assertEmpty($v->failed());
	}


	public function testSometimesCanSkipRequiredRules()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, [], ['name' => 'sometimes|required']);
		$this->assertTrue($v->passes());
		$this->assertEmpty($v->failed());
	}


	public function testInValidatableRulesReturnsValid()
	{
		$trans = $this->getTranslator();
		$trans->shouldReceive('trans')->never();
		$v = new Validator($trans, ['foo' => 'taylor'], ['name' => 'Confirmed']);
		$this->assertTrue($v->passes());
	}


	public function testProperLanguageLineIsSet()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => 'required!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('required!', $v->messages()->first('name'));
	}


	public function testCustomReplacersAreCalled()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => 'foo bar'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$v->addReplacer('required', function($message, $attribute, $rule, $parameters) { return str_replace('bar', 'taylor', $message); });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo taylor', $v->messages()->first('name'));
	}


	public function testClassBasedCustomReplacers()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.foo' => 'foo!'], 'en', 'messages');
		$v = new Validator($trans, [], ['name' => 'required']);
		$v->setContainer($container = m::mock(\Illuminate\Container\Container::class));
		$v->addReplacer('required', 'Foo@bar');
		$container->shouldReceive('make')->once()->with('Foo')->andReturn($foo = m::mock('StdClass'));
		$foo->shouldReceive('bar')->once()->andReturn('replaced!');
		$v->passes();
		$v->messages()->setFormat(':message');
		$this->assertEquals('replaced!', $v->messages()->first('name'));
	}


	public function testAttributeNamesAreReplaced()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => ':attribute is required!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('name is required!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => ':attribute is required!', 'validation.attributes.name' => 'Name'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('Name is required!', $v->messages()->first('name'));

		//set customAttributes by setter
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => ':attribute is required!'], 'en', 'messages');
		$customAttributes = ['name' => 'Name'];
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$v->addCustomAttributes($customAttributes);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('Name is required!', $v->messages()->first('name'));


		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => ':attribute is required!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$v->setAttributeNames(['name' => 'Name']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('Name is required!', $v->messages()->first('name'));
	}


	public function testDisplayableValuesAreReplaced()
	{
		//required_if:foo,bar
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required_if' => 'The :attribute field is required when :other is :value.'], 'en', 'messages');
		$trans->addResource('array', ['validation.values.color.1' => 'red'], 'en', 'messages');
		$v = new Validator($trans, ['color' => '1', 'bar' => ''], ['bar' => 'RequiredIf:color,1']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('The bar field is required when color is red.', $v->messages()->first('bar'));

		//in:foo,bar,...
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.in' => ':attribute must be included in :values.'], 'en', 'messages');
		$trans->addResource('array', ['validation.values.type.5' => 'Short'], 'en', 'messages');
		$trans->addResource('array', ['validation.values.type.300' => 'Long'], 'en', 'messages');
		$v = new Validator($trans, ['type' => '4'], ['type' => 'in:5,300']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('type must be included in Short, Long.', $v->messages()->first('type'));

		// test addCustomValues
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.in' => ':attribute must be included in :values.'], 'en', 'messages');
		$customValues = [
				 'type' =>
					[
					 '5'   => 'Short',
					 '300' => 'Long',
                    ]
        ];
		$v = new Validator($trans, ['type' => '4'], ['type' => 'in:5,300']);
		$v->addCustomValues($customValues);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('type must be included in Short, Long.', $v->messages()->first('type'));

		// set custom values by setter
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.in' => ':attribute must be included in :values.'], 'en', 'messages');
		$customValues = [
				 'type' =>
					[
					 '5'   => 'Short',
					 '300' => 'Long',
                    ]
        ];
		$v = new Validator($trans, ['type' => '4'], ['type' => 'in:5,300']);
		$v->setValueNames($customValues);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('type must be included in Short, Long.', $v->messages()->first('type'));
	}


	public function testCustomValidationLinesAreRespected()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required' => 'required!', 'validation.custom.name.required' => 'really required!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('really required!', $v->messages()->first('name'));
	}


	public function testInlineValidationMessagesAreRespected()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required'], ['name.required' => 'require it please!']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('require it please!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => ''], ['name' => 'Required'], ['required' => 'require it please!']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('require it please!', $v->messages()->first('name'));
	}


	public function testValidateRequired()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, [], ['name' => 'Required']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['name' => ''], ['name' => 'Required']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['name' => 'foo'], ['name' => 'Required']);
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, ['name' => $file], ['name' => 'Required']);
		$this->assertFalse($v->passes());

		$file = new File(__FILE__, false);
		$v = new Validator($trans, ['name' => $file], ['name' => 'Required']);
		$this->assertTrue($v->passes());
	}


	public function testValidateRequiredWith()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'Taylor'], ['last' => 'required_with:first']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['first' => 'Taylor', 'last' => ''], ['last' => 'required_with:first']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['first' => ''], ['last' => 'required_with:first']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, [], ['last' => 'required_with:first']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['first' => 'Taylor', 'last' => 'Otwell'], ['last' => 'required_with:first']);
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, ['file' => $file, 'foo' => ''], ['foo' => 'required_with:file']);
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_with:file']);
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File('', false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_with:file']);
		$this->assertFalse($v->passes());
	}


	public function testRequiredWithAll()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'foo'], ['last' => 'required_with_all:first,foo']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['first' => 'foo'], ['last' => 'required_with_all:first']);
		$this->assertFalse($v->passes());
	}


	public function testValidateRequiredWithout()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'Taylor'], ['last' => 'required_without:first']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['first' => 'Taylor', 'last' => ''], ['last' => 'required_without:first']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['first' => ''], ['last' => 'required_without:first']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, [], ['last' => 'required_without:first']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['first' => 'Taylor', 'last' => 'Otwell'], ['last' => 'required_without:first']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['last' => 'Otwell'], ['last' => 'required_without:first']);
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$v = new Validator($trans, ['file' => $file], ['foo' => 'required_without:file']);
		$this->assertFalse($v->passes());

		$foo = new File('', false);
		$v = new Validator($trans, ['foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertFalse($v->passes());

		$foo = new File(__FILE__, false);
		$v = new Validator($trans, ['foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertTrue($v->passes());

		$file = new File(__FILE__, false);
		$foo  = new File('', false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$foo  = new File(__FILE__, false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertTrue($v->passes());

		$file = new File('', false);
		$foo  = new File('', false);
		$v = new Validator($trans, ['file' => $file, 'foo' => $foo], ['foo' => 'required_without:file']);
		$this->assertFalse($v->passes());
	}


	public function testRequiredWithoutMultiple()
	{
		$trans = $this->getRealTranslator();

		$rules = [
			'f1' => 'required_without:f2,f3',
			'f2' => 'required_without:f1,f3',
			'f3' => 'required_without:f1,f2',
        ];

		$v = new Validator($trans, [], $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['f1' => 'foo'], $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['f2' => 'foo'], $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['f3' => 'foo'], $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['f1' => 'foo', 'f2' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f1' => 'foo', 'f3' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f2' => 'foo', 'f3' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f1' => 'foo', 'f2' => 'bar', 'f3' => 'baz'], $rules);
		$this->assertTrue($v->passes());
	}


	public function testRequiredWithoutAll()
	{
		$trans = $this->getRealTranslator();

		$rules = [
			'f1' => 'required_without_all:f2,f3',
			'f2' => 'required_without_all:f1,f3',
			'f3' => 'required_without_all:f1,f2',
        ];

		$v = new Validator($trans, [], $rules);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['f1' => 'foo'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f2' => 'foo'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f3' => 'foo'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f1' => 'foo', 'f2' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f1' => 'foo', 'f3' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f2' => 'foo', 'f3' => 'bar'], $rules);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['f1' => 'foo', 'f2' => 'bar', 'f3' => 'baz'], $rules);
		$this->assertTrue($v->passes());
	}


	public function testRequiredIf()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'taylor'], ['last' => 'required_if:first,taylor']);
		$this->assertTrue($v->fails());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'taylor', 'last' => 'otwell'], ['last' => 'required_if:first,taylor']);
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'taylor', 'last' => 'otwell'], ['last' => 'required_if:first,taylor,dayle']
        );
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['first' => 'dayle', 'last' => 'rees'], ['last' => 'required_if:first,taylor,dayle']);
		$this->assertTrue($v->passes());

		// error message when passed multiple values (required_if:foo,bar,baz)
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.required_if' => 'The :attribute field is required when :other is :value.'], 'en', 'messages');
		$v = new Validator($trans, ['first' => 'dayle', 'last' => ''], ['last' => 'RequiredIf:first,taylor,dayle']);
		$this->assertFalse($v->passes());
		$this->assertEquals('The last field is required when first is dayle.', $v->messages()->first('last'));
	}


	public function testValidateConfirmed()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['password' => 'foo'], ['password' => 'Confirmed']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['password' => 'foo', 'password_confirmation' => 'bar'], ['password' => 'Confirmed']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['password' => 'foo', 'password_confirmation' => 'foo'], ['password' => 'Confirmed']);
		$this->assertTrue($v->passes());
	}


	public function testValidateSame()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'bar', 'baz' => 'boom'], ['foo' => 'Same:baz']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'bar'], ['foo' => 'Same:baz']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'bar', 'baz' => 'bar'], ['foo' => 'Same:baz']);
		$this->assertTrue($v->passes());
	}


	public function testValidateDifferent()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'bar', 'baz' => 'boom'], ['foo' => 'Different:baz']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'bar'], ['foo' => 'Different:baz']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'bar', 'baz' => 'bar'], ['foo' => 'Different:baz']);
		$this->assertFalse($v->passes());
	}


	public function testValidateAccepted()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'no'], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => null], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, [], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 0], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => false], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'false'], ['foo' => 'Accepted']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'yes'], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'on'], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '1'], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 1], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => true], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'true'], ['foo' => 'Accepted']);
		$this->assertTrue($v->passes());
	}


	public function testValidateString()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => 'string']);
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => ['blah' => 'test']], ['x' => 'string']);
		$this->assertFalse($v->passes());
	}


	public function testValidateBoolean()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'no'], ['foo' => 'Boolean']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'yes'], ['foo' => 'Boolean']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'false'], ['foo' => 'Boolean']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'true'], ['foo' => 'Boolean']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, [], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => false], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => true], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '1'], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 1], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '0'], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 0], ['foo' => 'Boolean']);
		$this->assertTrue($v->passes());
	}


	public function testValidateNumeric()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'asdad'], ['foo' => 'Numeric']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '1.23'], ['foo' => 'Numeric']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '-1'], ['foo' => 'Numeric']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '1'], ['foo' => 'Numeric']);
		$this->assertTrue($v->passes());
	}


	public function testValidateInteger()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'asdad'], ['foo' => 'Integer']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '1.23'], ['foo' => 'Integer']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '-1'], ['foo' => 'Integer']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '1'], ['foo' => 'Integer']);
		$this->assertTrue($v->passes());
	}


	public function testValidateDigits()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => '12345'], ['foo' => 'Digits:5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '123'], ['foo' => 'Digits:200']);
		$this->assertFalse($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => '12345'], ['foo' => 'digits_between:1,6']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'bar'], ['foo' => 'digits_between:1,10']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '123'], ['foo' => 'digits_between:4,5']);
		$this->assertFalse($v->passes());
	}


	public function testValidateSize()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'asdad'], ['foo' => 'Size:3']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'anc'], ['foo' => 'Size:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '123'], ['foo' => 'Numeric|Size:3']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '3'], ['foo' => 'Numeric|Size:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Size:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Size:4']);
		$this->assertFalse($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, [], ['photo' => 'Size:3']);
		$v->setFiles(['photo' => $file]);
		$this->assertTrue($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, [], ['photo' => 'Size:3']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());
	}


	public function testValidateBetween()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'asdad'], ['foo' => 'Between:3,4']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'anc'], ['foo' => 'Between:3,5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'ancf'], ['foo' => 'Between:3,5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'ancfs'], ['foo' => 'Between:3,5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '123'], ['foo' => 'Numeric|Between:50,100']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '3'], ['foo' => 'Numeric|Between:1,5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Between:1,5']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Between:1,2']);
		$this->assertFalse($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, [], ['photo' => 'Between:1,5']);
		$v->setFiles(['photo' => $file]);
		$this->assertTrue($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, [], ['photo' => 'Between:1,2']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());
	}


	public function testValidateMin()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => '3'], ['foo' => 'Min:3']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'anc'], ['foo' => 'Min:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '2'], ['foo' => 'Numeric|Min:3']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '5'], ['foo' => 'Numeric|Min:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3, 4]], ['foo' => 'Array|Min:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2]], ['foo' => 'Array|Min:3']);
		$this->assertFalse($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, [], ['photo' => 'Min:2']);
		$v->setFiles(['photo' => $file]);
		$this->assertTrue($v->passes());

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, [], ['photo' => 'Min:10']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());
	}


	public function testValidateMax()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'aslksd'], ['foo' => 'Max:3']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'anc'], ['foo' => 'Max:3']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => '211'], ['foo' => 'Numeric|Max:100']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => '22'], ['foo' => 'Numeric|Max:33']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Max:4']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => [1, 2, 3]], ['foo' => 'Array|Max:2']);
		$this->assertFalse($v->passes());

		$file = $this->getMock(UploadedFile::class, ['isValid', 'getSize'], [__FILE__, basename(__FILE__)]);
		$file->expects($this->at(0))->method('isValid')->will($this->returnValue(true));
		$file->expects($this->at(1))->method('getSize')->will($this->returnValue(3072));
		$v = new Validator($trans, [], ['photo' => 'Max:10']);
		$v->setFiles(['photo' => $file]);
		$this->assertTrue($v->passes());

		$file = $this->getMock(UploadedFile::class, ['isValid', 'getSize'], [__FILE__, basename(__FILE__)]);
		$file->expects($this->at(0))->method('isValid')->will($this->returnValue(true));
		$file->expects($this->at(1))->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, [], ['photo' => 'Max:2']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());

		$file = $this->getMock(UploadedFile::class, ['isValid'], [__FILE__, basename(__FILE__)]);
		$file->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$v = new Validator($trans, [], ['photo' => 'Max:10']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());
	}


	public function testProperMessagesAreReturnedForSizes()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.min.numeric' => 'numeric', 'validation.size.string' => 'string', 'validation.max.file' => 'file'], 'en', 'messages');
		$v = new Validator($trans, ['name' => '3'], ['name' => 'Numeric|Min:5']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('numeric', $v->messages()->first('name'));

		$v = new Validator($trans, ['name' => 'asasdfadsfd'], ['name' => 'Size:2']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('string', $v->messages()->first('name'));

		$file = $this->getMock(File::class, ['getSize'], [__FILE__, false]);
		$file->expects($this->any())->method('getSize')->will($this->returnValue(4072));
		$v = new Validator($trans, [], ['photo' => 'Max:3']);
		$v->setFiles(['photo' => $file]);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('file', $v->messages()->first('photo'));
	}


	public function testValidateIn()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => 'foo'], ['name' => 'In:bar,baz']);
		$this->assertFalse($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => 0], ['name' => 'In:bar,baz']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['name' => 'foo'], ['name' => 'In:foo,baz']);
		$this->assertTrue($v->passes());
	}


	public function testValidateNotIn()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => 'foo'], ['name' => 'NotIn:bar,baz']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['name' => 'foo'], ['name' => 'NotIn:foo,baz']);
		$this->assertFalse($v->passes());
	}


	public function testValidateUnique()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Unique:users']);
		$mock = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, [])->andReturn(0);
		$v->setPresenceVerifier($mock);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Unique:users,email_addr,1']);
		$mock2 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock2->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', '1', 'id', [])->andReturn(1);
		$v->setPresenceVerifier($mock2);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Unique:users,email_addr,1,id_col']);
		$mock3 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock3->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', '1', 'id_col', [])->andReturn(2);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Unique:users,email_addr,NULL,id_col,foo,bar']);
		$mock3 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock3->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', null, 'id_col', ['foo' => 'bar'])->andReturn(2);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());
	}


	public function testValidationExists()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Exists:users']);
		$mock = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, [])->andReturn(true);
		$v->setPresenceVerifier($mock);
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Exists:users,email,account_id,1,name,taylor']);
		$mock4 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock4->shouldReceive('getCount')->once()->with('users', 'email', 'foo', null, null, ['account_id' => 1, 'name' => 'taylor']
        )->andReturn(true);
		$v->setPresenceVerifier($mock4);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['email' => 'foo'], ['email' => 'Exists:users,email_addr']);
		$mock2 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock2->shouldReceive('getCount')->once()->with('users', 'email_addr', 'foo', null, null, [])->andReturn(false);
		$v->setPresenceVerifier($mock2);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['email' => ['foo']], ['email' => 'Exists:users,email_addr']);
		$mock3 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock3->shouldReceive('getMultiCount')->once()->with('users', 'email_addr', ['foo'], [])->andReturn(false);
		$v->setPresenceVerifier($mock3);
		$this->assertFalse($v->passes());
	}

	public function testValidationExistsIsNotCalledUnnecessarily()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['id' => 'foo'], ['id' => 'Integer|Exists:users,id']);
		$mock2 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock2->shouldReceive('getCount')->never();
		$v->setPresenceVerifier($mock2);
		$this->assertFalse($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['id' => '1'], ['id' => 'Integer|Exists:users,id']);
		$mock2 = m::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
		$mock2->shouldReceive('getCount')->once()->with('users', 'id', '1', null, null, [])->andReturn(true);
		$v->setPresenceVerifier($mock2);
		$this->assertTrue($v->passes());
	}


	public function testValidateIp()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['ip' => 'aslsdlks'], ['ip' => 'Ip']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['ip' => '127.0.0.1'], ['ip' => 'Ip']);
		$this->assertTrue($v->passes());
	}


	public function testValidateEmail()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => 'Email']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'foo@gmail.com'], ['x' => 'Email']);
		$this->assertTrue($v->passes());
	}


	public function testValidateUrl()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => 'Url']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'http://google.com'], ['x' => 'Url']);
		$this->assertTrue($v->passes());
	}


	public function testValidateActiveUrl()
	{
		$trans = $this->getRealTranslator();
//		$v = new Validator($trans, array('x' => 'aslsdlksa'), array('x' => 'active_url'));
//		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'http://google.com'], ['x' => 'active_url']);
		$this->assertTrue($v->passes());
	}


	public function testValidateImage()
	{
		$trans = $this->getRealTranslator();
		$uploadedFile = [__FILE__, '', null, null, true];

		$file = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$v = new Validator($trans, [], ['x' => 'Image']);
		$v->setFiles(['x' => $file]);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, [], ['x' => 'Image']);
		$file2 = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file2->expects($this->any())->method('guessExtension')->will($this->returnValue('jpeg'));
		$v->setFiles(['x' => $file2]);
		$this->assertTrue($v->passes());

		$file3 = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file3->expects($this->any())->method('guessExtension')->will($this->returnValue('gif'));
		$v->setFiles(['x' => $file3]);
		$this->assertTrue($v->passes());

		$file4 = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file4->expects($this->any())->method('guessExtension')->will($this->returnValue('bmp'));
		$v->setFiles(['x' => $file4]);
		$this->assertTrue($v->passes());

		$file5 = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file5->expects($this->any())->method('guessExtension')->will($this->returnValue('png'));
		$v->setFiles(['x' => $file5]);
		$this->assertTrue($v->passes());
	}


	public function testValidateMime()
	{
		$trans = $this->getRealTranslator();
		$uploadedFile = [__FILE__, '', null, null, true];

		$file = $this->getMock(UploadedFile::class, ['guessExtension'], $uploadedFile);
		$file->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$v = new Validator($trans, [], ['x' => 'mimes:php']);
		$v->setFiles(['x' => $file]);
		$this->assertTrue($v->passes());

		$file2 = $this->getMock(UploadedFile::class, ['guessExtension', 'isValid'], $uploadedFile);
		$file2->expects($this->any())->method('guessExtension')->will($this->returnValue('php'));
		$file2->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$v = new Validator($trans, [], ['x' => 'mimes:php']);
		$v->setFiles(['x' => $file2]);
		$this->assertFalse($v->passes());
	}


	public function testEmptyRulesSkipped()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => ['alpha', [], '']]);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => '|||required|']);
		$this->assertTrue($v->passes());
	}

	public function testAlternativeFormat()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => ['alpha', ['min', 3], ['max', 10]]]);
		$this->assertTrue($v->passes());
	}

	public function testValidateAlpha()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'aslsdlks'], ['x' => 'Alpha']);
		$this->assertTrue($v->passes());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, [
            'x' => 'aslsdlks
1
1'
        ], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'http://google.com'], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'ユニコードを基盤技術と'], ['x' => 'Alpha']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'ユニコード を基盤技術と'], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'नमस्कार'], ['x' => 'Alpha']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'आपका स्वागत है'], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'Continuación'], ['x' => 'Alpha']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'ofreció su dimisión'], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => '❤'], ['x' => 'Alpha']);
		$this->assertFalse($v->passes());

	}


	public function testValidateAlphaNum()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'asls13dlks'], ['x' => 'AlphaNum']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'http://g232oogle.com'], ['x' => 'AlphaNum']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => '१२३'], ['x' => 'AlphaNum']);//numbers in Hindi
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '٧٨٩'], ['x' => 'AlphaNum']);//eastern arabic numerals
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'नमस्कार'], ['x' => 'AlphaNum']);
		$this->assertTrue($v->passes());
	}


	public function testValidateAlphaDash()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'asls1-_3dlks'], ['x' => 'AlphaDash']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'http://-g232oogle.com'], ['x' => 'AlphaDash']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'नमस्कार-_'], ['x' => 'AlphaDash']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '٧٨٩'], ['x' => 'AlphaDash']);//eastern arabic numerals
		$this->assertTrue($v->passes());

	}


	public function testValidateTimezone()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'India'], ['foo' => 'Timezone']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'Cairo'], ['foo' => 'Timezone']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['foo' => 'UTC'], ['foo' => 'Timezone']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'Africa/Windhoek'], ['foo' => 'Timezone']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['foo' => 'GMT'], ['foo' => 'Timezone']);
		$this->assertTrue($v->passes());
	}


	public function testValidateRegex()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'asdasdf'], ['x' => 'Regex:/^([a-z])+$/i']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'aasd234fsd1'], ['x' => 'Regex:/^([a-z])+$/i']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, ['x' => 'a,b'], ['x' => 'Regex:/^a,b$/i']);
		$this->assertTrue($v->passes());
	}


	public function testValidateDateAndFormat()
	{
		date_default_timezone_set('UTC');
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => '2000-01-01'], ['x' => 'date']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '01/01/2000'], ['x' => 'date']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => 'Not a date'], ['x' => 'date']);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => '2000-01-01'], ['x' => 'date_format:Y-m-d']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '2000-01-01 17:43:59'], ['x' => 'date_format:Y-m-d H:i:s']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '01/01/2001'], ['x' => 'date_format:Y-m-d']);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => '22000-01-01'], ['x' => 'date_format:Y-m-d']);
		$this->assertTrue($v->fails());
	}


	public function testBeforeAndAfter()
	{
		date_default_timezone_set('UTC');
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => '2000-01-01'], ['x' => 'Before:2012-01-01']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '2012-01-01'], ['x' => 'After:2000-01-01']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '2012-01-01', 'ends' => '2013-01-01'], ['start' => 'After:2000-01-01', 'ends' => 'After:start']
        );
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '2012-01-01', 'ends' => '2000-01-01'], ['start' => 'After:2000-01-01', 'ends' => 'After:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '2012-01-01', 'ends' => '2013-01-01'], ['start' => 'Before:ends', 'ends' => 'After:start']
        );
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '2012-01-01', 'ends' => '2000-01-01'], ['start' => 'Before:ends', 'ends' => 'After:start']
        );
		$this->assertTrue($v->fails());
	}


	public function testBeforeAndAfterWithFormat()
	{
		date_default_timezone_set('UTC');
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => '31/12/2000'], ['x' => 'before:31/02/2012']);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => '31/12/2000'], ['x' => 'date_format:d/m/Y|before:31/12/2012']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => '31/12/2012'], ['x' => 'after:31/12/2000']);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => '31/12/2012'], ['x' => 'date_format:d/m/Y|after:31/12/2000']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2013'], ['start' => 'after:01/01/2000', 'ends' => 'after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2013'], ['start' => 'date_format:d/m/Y|after:31/12/2000', 'ends' => 'date_format:d/m/Y|after:start']
        );
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2000'], ['start' => 'after:31/12/2000', 'ends' => 'after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2000'], ['start' => 'date_format:d/m/Y|after:31/12/2000', 'ends' => 'date_format:d/m/Y|after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2013'], ['start' => 'before:ends', 'ends' => 'after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2013'], ['start' => 'date_format:d/m/Y|before:ends', 'ends' => 'date_format:d/m/Y|after:start']
        );
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2000'], ['start' => 'before:ends', 'ends' => 'after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => '31/12/2012', 'ends' => '31/12/2000'], ['start' => 'date_format:d/m/Y|before:ends', 'ends' => 'date_format:d/m/Y|after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['start' => 'invalid', 'ends' => 'invalid'], ['start' => 'date_format:d/m/Y|before:ends', 'ends' => 'date_format:d/m/Y|after:start']
        );
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => date('d/m/Y')], ['x' => 'date_format:d/m/Y|after:yesterday|before:tomorrow']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => date('d/m/Y')], ['x' => 'date_format:d/m/Y|after:tomorrow|before:yesterday']);
		$this->assertTrue($v->fails());

		$v = new Validator($trans, ['x' => date('Y-m-d')], ['x' => 'after:yesterday|before:tomorrow']);
		$this->assertTrue($v->passes());

		$v = new Validator($trans, ['x' => date('Y-m-d')], ['x' => 'after:tomorrow|before:yesterday']);
		$this->assertTrue($v->fails());
	}


	public function testSometimesAddingRules()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'foo'], ['x' => 'Required']);
		$v->sometimes('x', 'Confirmed', function($i) { return $i->x == 'foo'; });
		$this->assertEquals(['x' => ['Required', 'Confirmed']], $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'foo'], ['x' => 'Required']);
		$v->sometimes('x', 'Confirmed', function($i) { return $i->x == 'bar'; });
		$this->assertEquals(['x' => ['Required']], $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'foo'], ['x' => 'Required']);
		$v->sometimes('x', 'Foo|Bar', function($i) { return $i->x == 'foo'; });
		$this->assertEquals(['x' => ['Required', 'Foo', 'Bar']], $v->getRules());

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['x' => 'foo'], ['x' => 'Required']);
		$v->sometimes('x', ['Foo', 'Bar:Baz'], function($i) { return $i->x == 'foo'; });
		$this->assertEquals(['x' => ['Required', 'Foo', 'Bar:Baz']], $v->getRules());
	}


	public function testCustomValidators()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.foo' => 'foo!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => 'taylor'], ['name' => 'foo']);
		$v->addExtension('foo', function() { return false; });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.foo_bar' => 'foo!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => 'taylor'], ['name' => 'foo_bar']);
		$v->addExtension('FooBar', function() { return false; });
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => 'taylor'], ['name' => 'foo_bar']);
		$v->addExtension('FooBar', function() { return false; });
		$v->setFallbackMessages(['foo_bar' => 'foo!']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));

		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['name' => 'taylor'], ['name' => 'foo_bar']);
		$v->addExtensions(['FooBar' => function() { return false; }]);
		$v->setFallbackMessages(['foo_bar' => 'foo!']);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));
	}


	public function testClassBasedCustomValidators()
	{
		$trans = $this->getRealTranslator();
		$trans->addResource('array', ['validation.foo' => 'foo!'], 'en', 'messages');
		$v = new Validator($trans, ['name' => 'taylor'], ['name' => 'foo']);
		$v->setContainer($container = m::mock(\Illuminate\Container\Container::class));
		$v->addExtension('foo', 'Foo@bar');
		$container->shouldReceive('make')->once()->with('Foo')->andReturn($foo = m::mock('StdClass'));
		$foo->shouldReceive('bar')->once()->andReturn(false);
		$this->assertFalse($v->passes());
		$v->messages()->setFormat(':message');
		$this->assertEquals('foo!', $v->messages()->first('name'));
	}


	public function testCustomImplicitValidators()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, [], ['implicit_rule' => 'foo']);
		$v->addImplicitExtension('implicit_rule', function() { return true; });
		$this->assertTrue($v->passes());
	}


    public function testExceptionThrownOnIncorrectParameterCount()
    {
        $this->expectException(InvalidArgumentException::class);
        $trans = $this->getTranslator();
        $v = new Validator($trans, [], ['foo' => 'required_if:foo']);
        $v->passes();
    }


	public function testValidateEach()
	{
		$trans = $this->getRealTranslator();
		$data = ['foo' => [5, 10, 15]];

		$v = new Validator($trans, $data, ['foo' => 'Array']);
		$v->each('foo', ['field' => 'numeric|min:6|max:14']);
		$this->assertFalse($v->passes());

		$v = new Validator($trans, $data, ['foo' => 'Array']);
		$v->each('foo', ['field' => 'numeric|min:4|max:16']);
		$this->assertTrue($v->passes());
	}


	public function testValidateEachWithNonArrayWithArrayRule()
	{
		$trans = $this->getRealTranslator();
		$v = new Validator($trans, ['foo' => 'string'], ['foo' => 'Array']);
		$v->each('foo', ['min:7|max:13']);
		$this->assertFalse($v->passes());
	}


    public function testValidateEachWithNonArrayWithoutArrayRule()
    {
        $this->expectException(InvalidArgumentException::class);
        $trans = $this->getRealTranslator();
        $v = new Validator($trans, ['foo' => 'string'], ['foo' => 'numeric']);
        $v->each('foo', ['min:7|max:13']);
        $this->assertFalse($v->passes());
    }


	protected function getTranslator()
    {
        return m::mock(TranslatorInterface::class);
    }


	protected function getRealTranslator()
	{
		$trans = new Symfony\Component\Translation\Translator('en');
		$trans->addLoader('array', new Symfony\Component\Translation\Loader\ArrayLoader);
		return $trans;
	}

}
