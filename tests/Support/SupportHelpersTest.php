<?php

use Illuminate\Support\Str;
use L4\Tests\BackwardCompatibleTestCase;

class SupportHelpersTest extends BackwardCompatibleTestCase {

	public function testArrayBuild()
	{
		$this->assertEquals(
            ['foo' => 'bar'], array_build(['foo' => 'bar'], function($key, $value)
		{
			return [$key, $value];
		}));
	}


	public function testArrayDot()
	{
		$array = array_dot(['name' => 'taylor', 'languages' => ['php' => true]]);
		$this->assertEquals($array, ['name' => 'taylor', 'languages.php' => true]);
	}


	public function testArrayGet()
	{
		$array = ['names' => ['developer' => 'taylor']];
		$this->assertEquals('taylor', array_get($array, 'names.developer'));
		$this->assertEquals('dayle', array_get($array, 'names.otherDeveloper', 'dayle'));
		$this->assertEquals('dayle', array_get($array, 'names.otherDeveloper', function() { return 'dayle'; }));
	}


	public function testArrayHas()
	{
		$array = ['names' => ['developer' => 'taylor']];
		$this->assertTrue(array_has($array, 'names'));
		$this->assertTrue(array_has($array, 'names.developer'));
		$this->assertFalse(array_has($array, 'foo'));
		$this->assertFalse(array_has($array, 'foo.bar'));
	}


	public function testArraySet()
	{
		$array = [];
		array_set($array, 'names.developer', 'taylor');
		$this->assertEquals('taylor', $array['names']['developer']);
	}


	public function testArrayForget()
	{
		$array = ['names' => ['developer' => 'taylor', 'otherDeveloper' => 'dayle']];
		array_forget($array, 'names.developer');
		$this->assertFalse(isset($array['names']['developer']));
		$this->assertTrue(isset($array['names']['otherDeveloper']));

		$array = ['names' => ['developer' => 'taylor', 'otherDeveloper' => 'dayle', 'thirdDeveloper' => 'Lucas']];
		array_forget($array, ['names.developer', 'names.otherDeveloper']);
		$this->assertFalse(isset($array['names']['developer']));
		$this->assertFalse(isset($array['names']['otherDeveloper']));
		$this->assertTrue(isset($array['names']['thirdDeveloper']));

		$array = ['names' => ['developer' => 'taylor', 'otherDeveloper' => 'dayle'], 'otherNames' => ['developer' => 'Lucas', 'otherDeveloper' => 'Graham']];
		array_forget($array, ['names.developer', 'otherNames.otherDeveloper']);
		$expected = ['names' => ['otherDeveloper' => 'dayle'], 'otherNames' => ['developer' => 'Lucas']];
		$this->assertEquals($expected, $array);
	}


	public function testArrayPluckWithArrayAndObjectValues()
	{
		$array = [(object) ['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']];
		$this->assertEquals(['taylor', 'dayle'], array_pluck($array, 'name'));
		$this->assertEquals(['taylor' => 'foo', 'dayle' => 'bar'], array_pluck($array, 'email', 'name'));
	}


	public function testArrayExcept()
	{
		$array = ['name' => 'taylor', 'age' => 26];
		$this->assertEquals(['age' => 26], array_except($array, ['name']));
	}


	public function testArrayOnly()
	{
		$array = ['name' => 'taylor', 'age' => 26];
		$this->assertEquals(['name' => 'taylor'], array_only($array, ['name']));
		$this->assertSame([], array_only($array, ['nonExistingKey']));
	}


	public function testArrayDivide()
	{
		$array = ['name' => 'taylor'];
		list($keys, $values) = array_divide($array);
		$this->assertEquals(['name'], $keys);
		$this->assertEquals(['taylor'], $values);
	}


	public function testArrayFirst()
	{
		$array = ['name' => 'taylor', 'otherDeveloper' => 'dayle'];
		$this->assertEquals('dayle', array_first($array, function($key, $value) { return $value == 'dayle'; }));
	}

	public function testArrayLast()
	{
		$array = [100, 250, 290, 320, 500, 560, 670];
		$this->assertEquals(670, array_last($array, function($key, $value) { return $value > 320; }));
	}


	public function testArrayFetch()
	{
		$data = [
			'post-1' => [
				'comments' => [
					'tags' => [
						'#foo', '#bar',
                    ],
                ],
            ],
			'post-2' => [
				'comments' => [
					'tags' => [
						'#baz',
                    ],
                ],
            ],
        ];

		$this->assertEquals([
			0 => [
				'tags' => [
					'#foo', '#bar',
                ],
            ],
			1 => [
				'tags' => [
					'#baz',
                ],
            ],
        ], array_fetch($data, 'comments'));

		$this->assertEquals([['#foo', '#bar'], ['#baz']], array_fetch($data, 'comments.tags'));
		$this->assertEquals([], array_fetch($data, 'foo'));
		$this->assertEquals([], array_fetch($data, 'foo.bar'));
	}


	public function testArrayFlatten()
	{
		$this->assertEquals(['#foo', '#bar', '#baz'], array_flatten([['#foo', '#bar'], ['#baz']]));
	}


	public function testStrIs()
	{
		$this->assertTrue(str_is('*.dev', 'localhost.dev'));
		$this->assertTrue(str_is('a', 'a'));
		$this->assertTrue(str_is('/', '/'));
		$this->assertTrue(str_is('*dev*', 'localhost.dev'));
		$this->assertTrue(str_is('foo?bar', 'foo?bar'));
		$this->assertFalse(str_is('*something', 'foobar'));
		$this->assertFalse(str_is('foo', 'bar'));
		$this->assertFalse(str_is('foo.*', 'foobar'));
		$this->assertFalse(str_is('foo.ar', 'foobar'));
		$this->assertFalse(str_is('foo?bar', 'foobar'));
		$this->assertFalse(str_is('foo?bar', 'fobar'));
	}


	public function testStartsWith()
	{
		$this->assertTrue(starts_with('jason', 'jas'));
		$this->assertTrue(starts_with('jason', ['jas']));
		$this->assertFalse(starts_with('jason', 'day'));
		$this->assertFalse(starts_with('jason', ['day']));
	}


	public function testEndsWith()
	{
		$this->assertTrue(ends_with('jason', 'on'));
		$this->assertTrue(ends_with('jason', ['on']));
		$this->assertFalse(ends_with('jason', 'no'));
		$this->assertFalse(ends_with('jason', ['no']));
	}


	public function testStrContains()
	{
		$this->assertTrue(Str::contains('taylor', 'ylo'));
		$this->assertTrue(Str::contains('taylor', ['ylo']));
		$this->assertFalse(Str::contains('taylor', 'xxx'));
		$this->assertFalse(Str::contains('taylor', ['xxx']));
	}


	public function testSnakeCase()
	{
		$this->assertEquals('foo_bar', snake_case('fooBar'));
		$this->assertEquals('foo_bar', snake_case('fooBar')); // test cache
	}


	public function testCamelCase()
	{
		$this->assertEquals('fooBar', camel_case('FooBar'));
		$this->assertEquals('fooBar', camel_case('foo_bar'));
		$this->assertEquals('fooBar', camel_case('foo_bar')); // test cache
		$this->assertEquals('fooBarBaz', camel_case('Foo-barBaz'));
		$this->assertEquals('fooBarBaz', camel_case('foo-bar_baz'));
	}


	public function testStudlyCase()
	{
		$this->assertEquals('FooBar', studly_case('fooBar'));
		$this->assertEquals('FooBar', studly_case('foo_bar'));
		$this->assertEquals('FooBar', studly_case('foo_bar')); // test cache
		$this->assertEquals('FooBarBaz', studly_case('foo-barBaz'));
		$this->assertEquals('FooBarBaz', studly_case('foo-bar_baz'));
	}


	public function testValue()
	{
		$this->assertEquals('foo', value('foo'));
		$this->assertEquals('foo', value(function() { return 'foo'; }));
	}


	public function testObjectGet()
	{
		$class = new StdClass;
		$class->name = new StdClass;
		$class->name->first = 'Taylor';

		$this->assertEquals('Taylor', object_get($class, 'name.first'));
	}


	public function testDataGet()
	{
		$object = (object) ['users' => ['name' => ['Taylor', 'Otwell']]];
		$array = [(object) ['users' => [(object) ['name' => 'Taylor']]]];

		$this->assertEquals('Taylor', data_get($object, 'users.name.0'));
		$this->assertEquals('Taylor', data_get($array, '0.users.0.name'));
		$this->assertNull(data_get($array, '0.users.3'));
		$this->assertEquals('Not found', data_get($array, '0.users.3', 'Not found'));
		$this->assertEquals('Not found', data_get($array, '0.users.3', function (){ return 'Not found'; }));
	}


	public function testArraySort()
	{
		$array = [
			['name' => 'baz'],
			['name' => 'foo'],
			['name' => 'bar'],
        ];

		$this->assertEquals(
            [
			['name' => 'bar'],
			['name' => 'baz'],
			['name' => 'foo']
            ],
		array_values(array_sort($array, function($v) { return $v['name']; })));
	}


	public function testClassUsesRecursiveShouldReturnTraitsOnParentClasses()
	{
		$this->assertEquals([
			'SupportTestTraitOne' => 'SupportTestTraitOne',
			'SupportTestTraitTwo' => 'SupportTestTraitTwo',
		],
		class_uses_recursive('SupportTestClassTwo'));
	}


	public function testArrayAdd()
	{
		$this->assertEquals(['surname' => 'Mövsümov'], array_add([], 'surname', 'Mövsümov'));
		$this->assertEquals(['developer' => ['name' => 'Ferid']], array_add([], 'developer.name', 'Ferid'));
	}


	public function testArrayPull()
	{
		$developer = ['firstname' => 'Ferid', 'surname' => 'Mövsümov'];
		$this->assertEquals('Mövsümov', array_pull($developer, 'surname'));
		$this->assertEquals(['firstname' => 'Ferid'], $developer);
	}

}

trait SupportTestTraitOne {}

trait SupportTestTraitTwo {
	use SupportTestTraitOne;
}

class SupportTestClassOne {
	use SupportTestTraitTwo;
}

class SupportTestClassTwo extends SupportTestClassOne {}
