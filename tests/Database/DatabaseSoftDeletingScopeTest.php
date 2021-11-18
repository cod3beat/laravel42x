<?php

use Illuminate\Database\Eloquent\Builder;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseSoftDeletingScopeTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testApplyingScopeToABuilder()
    {
        $scope = m::mock('Illuminate\Database\Eloquent\SoftDeletingScope[extend]');
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('getModel')->once()->andReturn($model = m::mock('StdClass'));
		$model->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('table.deleted_at');
		$builder->shouldReceive('whereNull')->once()->with('table.deleted_at');
		$scope->shouldReceive('extend')->once();

		$scope->apply($builder);
	}


	public function testScopeCanRemoveDeletedAtConstraints()
	{
		$scope = new Illuminate\Database\Eloquent\SoftDeletingScope;
		$builder = m::mock(Builder::class);
		$builder->shouldReceive('getModel')->andReturn($model = m::mock('StdClass'));
		$model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
		$builder->shouldReceive('getQuery')->andReturn($query = m::mock('StdClass'));
		$query->wheres = [['type' => 'Null', 'column' => 'foo'], ['type' => 'Null', 'column' => 'table.deleted_at']];
		$scope->remove($builder);

		$this->assertEquals($query->wheres, [['type' => 'Null', 'column' => 'foo']]);
	}


	public function testForceDeleteExtension()
	{
		$builder = m::mock(Builder::class);
		$builder->makePartial();
		$scope = new Illuminate\Database\Eloquent\SoftDeletingScope;
		$scope->extend($builder);
		$callback = $builder->getMacro('forceDelete');
		$givenBuilder = m::mock(Builder::class);
		$givenBuilder->shouldReceive('getQuery')->andReturn($query = m::mock('StdClass'));
		$query->shouldReceive('delete')->once();

		$callback($givenBuilder);
	}


	public function testRestoreExtension()
	{
		$builder = m::mock(Builder::class);
		$builder->makePartial();
		$scope = new Illuminate\Database\Eloquent\SoftDeletingScope;
		$scope->extend($builder);
		$callback = $builder->getMacro('restore');
		$givenBuilder = m::mock(Builder::class);
		$givenBuilder->shouldReceive('withTrashed')->once();
		$givenBuilder->shouldReceive('getModel')->once()->andReturn($model = m::mock('StdClass'));
		$model->shouldReceive('getDeletedAtColumn')->once()->andReturn('deleted_at');
		$givenBuilder->shouldReceive('update')->once()->with(['deleted_at' => null]);

		$callback($givenBuilder);
	}


	public function testWithTrashedExtension()
	{
		$builder = m::mock(Builder::class);
		$builder->makePartial();
		$scope = m::mock('Illuminate\Database\Eloquent\SoftDeletingScope[remove]');
		$scope->extend($builder);
		$callback = $builder->getMacro('withTrashed');
		$givenBuilder = m::mock(Builder::class);
		$scope->shouldReceive('remove')->once()->with($givenBuilder);
		$result = $callback($givenBuilder);

		$this->assertEquals($givenBuilder, $result);
	}


	public function testOnlyTrashedExtension()
	{
		$builder = m::mock(Builder::class);
		$builder->makePartial();
		$scope = m::mock('Illuminate\Database\Eloquent\SoftDeletingScope[remove]');
		$scope->extend($builder);
		$callback = $builder->getMacro('onlyTrashed');
		$givenBuilder = m::mock(Builder::class);
		$scope->shouldReceive('remove')->once()->with($givenBuilder);
		$givenBuilder->shouldReceive('getQuery')->andReturn($query = m::mock('StdClass'));
		$givenBuilder->shouldReceive('getModel')->andReturn($model = m::mock('StdClass'));
		$model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
		$query->shouldReceive('whereNotNull')->once()->with('table.deleted_at');
		$result = $callback($givenBuilder);

		$this->assertEquals($givenBuilder, $result);
	}

}


class DatabaseSoftDeletingScopeBuilderStub {
	public $extensions = [];
	public $onDelete;
	public function extend($name, $callback)
	{
		$this->extensions[$name] = $callback;
	}
	public function onDelete($callback)
	{
		$this->onDelete = $callback;
	}
}
