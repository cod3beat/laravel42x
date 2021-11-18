<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseEloquentBuilderTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testFindMethod()
    {
        $builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
		$builder->shouldReceive('first')->with(['column'])->andReturn('baz');

		$result = $builder->find('bar', ['column']);
		$this->assertEquals('baz', $result);
	}


	public function testFindOrNewMethodModelFound()
	{
		$model = $this->getMockModel();
		$model->shouldReceive('findOrNew')->once()->andReturn('baz');

		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
		$builder->setModel($model);
		$builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
		$builder->shouldReceive('first')->with(['column'])->andReturn('baz');

		$expected = $model->findOrNew('bar', ['column']);
		$result = $builder->find('bar', ['column']);
		$this->assertEquals($expected, $result);
	}


	public function testFindOrNewMethodModelNotFound()
	{
		$model = $this->getMockModel();
		$model->shouldReceive('findOrNew')->once()->andReturn(m::mock(Model::class));

		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
		$builder->setModel($model);
		$builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
		$builder->shouldReceive('first')->with(['column'])->andReturn(null);

		$result = $model->findOrNew('bar', ['column']);
		$findResult = $builder->find('bar', ['column']);
		$this->assertNull($findResult);
		$this->assertInstanceOf(Model::class, $result);
	}

    public function testFindOrFailMethodThrowsModelNotFoundException()
    {
        $this->expectException(Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
        $builder->shouldReceive('first')->with(['column'])->andReturn(null);
        $result = $builder->findOrFail('bar', ['column']);
    }

    public function testFirstOrFailMethodThrowsModelNotFoundException()
    {
        $this->expectException(Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->shouldReceive('first')->with(['column'])->andReturn(null);
        $result = $builder->firstOrFail(['column']);
    }


	public function testFindWithMany()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', [$this->getMockQueryBuilder()]);
		$builder->getQuery()->shouldReceive('whereIn')->once()->with('foo_table.foo', [1, 2]);
		$builder->setModel($this->getMockModel());
		$builder->shouldReceive('get')->with(['column'])->andReturn('baz');

		$result = $builder->find([1, 2], ['column']);
		$this->assertEquals('baz', $result);
	}


	public function testFirstMethod()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get,take]', [$this->getMockQueryBuilder()]);
		$builder->shouldReceive('take')->with(1)->andReturn($builder);
		$builder->shouldReceive('get')->with(['*'])->andReturn(new Collection(['bar']));

		$result = $builder->first();
		$this->assertEquals('bar', $result);
	}


	public function testGetMethodLoadsModelsAndHydratesEagerRelations()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getModels,eagerLoadRelations]', [$this->getMockQueryBuilder()]
        );
		$builder->shouldReceive('getModels')->with(['foo'])->andReturn(['bar']);
		$builder->shouldReceive('eagerLoadRelations')->with(['bar'])->andReturn(['bar', 'baz']);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('newCollection')->with(['bar', 'baz'])->andReturn(new Collection(
            ['bar', 'baz']
        ));

		$results = $builder->get(['foo']);
		$this->assertEquals(['bar', 'baz'], $results->all());
	}


	public function testGetMethodDoesntHydrateEagerRelationsWhenNoResultsAreReturned()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getModels,eagerLoadRelations]', [$this->getMockQueryBuilder()]
        );
		$builder->shouldReceive('getModels')->with(['foo'])->andReturn([]);
		$builder->shouldReceive('eagerLoadRelations')->never();
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('newCollection')->with([])->andReturn(new Collection([]));

		$results = $builder->get(['foo']);
		$this->assertEquals([], $results->all());
	}


	public function testPluckMethodWithModelFound()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
		$mockModel = new StdClass;
		$mockModel->name = 'foo';
		$builder->shouldReceive('first')->with(['name'])->andReturn($mockModel);

		$this->assertEquals('foo', $builder->pluck('name'));
	}


	public function testPluckMethodWithModelNotFound()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[first]', [$this->getMockQueryBuilder()]);
		$builder->shouldReceive('first')->with(['name'])->andReturn(null);

		$this->assertNull($builder->pluck('name'));
	}


	public function testChunkExecuteCallbackOverPaginatedRequest()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[forPage,get]', [$this->getMockQueryBuilder()]);
		$builder->shouldReceive('forPage')->once()->with(1, 2)->andReturn($builder);
		$builder->shouldReceive('forPage')->once()->with(2, 2)->andReturn($builder);
		$builder->shouldReceive('forPage')->once()->with(3, 2)->andReturn($builder);
		$builder->shouldReceive('get')->times(3)->andReturn(['foo1', 'foo2'], ['foo3'], []);

		$callbackExecutionAssertor = m::mock('StdClass');
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo1')->once();
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo2')->once();
		$callbackExecutionAssertor->shouldReceive('doSomething')->with('foo3')->once();

		$builder->chunk(2, function($results) use($callbackExecutionAssertor) {
			foreach ($results as $result) {
				$callbackExecutionAssertor->doSomething($result);
			}
		});
	}


	public function testListsReturnsTheMutatedAttributesOfAModel()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('lists')->with('name', '')->andReturn(['bar', 'baz']);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(true);
		$builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new EloquentBuilderTestListsStub(
            ['name' => 'bar']
        ));
		$builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new EloquentBuilderTestListsStub(
            ['name' => 'baz']
        ));

		$this->assertEquals(['foo_bar', 'foo_baz'], $builder->lists('name'));
	}


	public function testListsWithoutModelGetterJustReturnTheAttributesFoundInDatabase()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('lists')->with('name', '')->andReturn(['bar', 'baz']);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(false);

		$this->assertEquals(['bar', 'baz'], $builder->lists('name'));
	}


	public function testMacrosAreCalledOnBuilder()
	{
		unset($_SERVER['__test.builder']);
		$builder = new Illuminate\Database\Eloquent\Builder(new Illuminate\Database\Query\Builder(
			m::mock(ConnectionInterface::class),
			m::mock(Grammar::class),
			m::mock(Processor::class)
		));
		$builder->macro('fooBar', function($builder)
		{
			$_SERVER['__test.builder'] = $builder;

			return $builder;
		});
		$result = $builder->fooBar();

		$this->assertEquals($builder, $result);
		$this->assertEquals($builder, $_SERVER['__test.builder']);
		unset($_SERVER['__test.builder']);
	}


	public function testPaginateMethod()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', [$this->getMockQueryBuilder()]);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getPerPage')->once()->andReturn(15);
		$builder->getQuery()->shouldReceive('getPaginationCount')->once()->andReturn(10);
		$conn = m::mock('stdClass');
		$paginator = m::mock('stdClass');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(1);
		$conn->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$builder->getQuery()->shouldReceive('getConnection')->once()->andReturn($conn);
		$builder->getQuery()->shouldReceive('forPage')->once()->with(1, 15);
		$builder->shouldReceive('get')->with(['*'])->andReturn(new Collection(['results']));
		$paginator->shouldReceive('make')->once()->with(['results'], 10, 15)->andReturn(['results']);

		$this->assertEquals(['results'], $builder->paginate());
	}


	public function testPaginateMethodWithGroupedQuery()
	{
		$query = $this->getMock(\Illuminate\Database\Query\Builder::class, ['from', 'getConnection'], [
			m::mock(ConnectionInterface::class),
			m::mock(Grammar::class),
			m::mock(Processor::class),
        ]);
		$query->expects($this->once())->method('from')->willReturn('foo_table');
		$builder = $this->getMock(Builder::class, ['get'], [$query]);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getPerPage')->once()->andReturn(2);
		$conn = m::mock('stdClass');
		$paginator = m::mock('stdClass');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(2);
		$conn->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$query->expects($this->once())->method('getConnection')->willReturn($conn);
		$builder->expects($this->once())->method('get')->with($this->equalTo(['*']))->willReturn(
            new Collection(['foo', 'bar', 'baz'])
        );
		$paginator->shouldReceive('make')->once()->with(['baz'], 3, 2)->andReturn(['results']);

		$this->assertEquals(['results'], $builder->groupBy('foo')->paginate());
	}


	public function testQuickPaginateMethod()
	{
		$query = $this->getMock(\Illuminate\Database\Query\Builder::class, ['from', 'getConnection', 'skip', 'take'], [
			m::mock(ConnectionInterface::class),
			m::mock(Grammar::class),
			m::mock(Processor::class),
        ]);
		$query->expects($this->once())->method('from')->willReturn('foo_table');
		$builder = $this->getMock(Builder::class, ['get'], [$query]);
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('getPerPage')->once()->andReturn(15);
		$conn = m::mock('stdClass');
		$paginator = m::mock('stdClass');
		$paginator->shouldReceive('getCurrentPage')->once()->andReturn(1);
		$conn->shouldReceive('getPaginator')->once()->andReturn($paginator);
		$query->expects($this->once())->method('getConnection')->willReturn($conn);
		$query->expects($this->once())->method('skip')->with(0)->willReturn($query);
		$query->expects($this->once())->method('take')->with(16)->willReturn($query);
		$builder->expects($this->once())->method('get')->with($this->equalTo(['*']))->willReturn(
            new Collection(['results'])
        );
		$paginator->shouldReceive('make')->once()->with(['results'], 15)->andReturn(['results']);

		$this->assertEquals(['results'], $builder->simplePaginate());
	}


	public function testGetModelsProperlyHydratesModels()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[get]', [$this->getMockQueryBuilder()]);
		$records[] = ['name' => 'taylor', 'age' => 26];
		$records[] = ['name' => 'dayle', 'age' => 28];
		$builder->getQuery()->shouldReceive('get')->once()->with(['foo'])->andReturn($records);
		$model = m::mock('Illuminate\Database\Eloquent\Model[getTable,getConnectionName,newInstance]');
		$model->shouldReceive('getTable')->once()->andReturn('foo_table');
		$builder->setModel($model);
		$model->shouldReceive('getConnectionName')->once()->andReturn('foo_connection');
		$model->shouldReceive('newInstance')->andReturnUsing(function() { return new EloquentBuilderTestModelStub; });
		$models = $builder->getModels(['foo']);

		$this->assertEquals('taylor', $models[0]->name);
		$this->assertEquals($models[0]->getAttributes(), $models[0]->getOriginal());
		$this->assertEquals('dayle', $models[1]->name);
		$this->assertEquals($models[1]->getAttributes(), $models[1]->getOriginal());
		$this->assertEquals('foo_connection', $models[0]->getConnectionName());
		$this->assertEquals('foo_connection', $models[1]->getConnectionName());
	}


	public function testEagerLoadRelationsLoadTopLevelRelationships()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[loadRelation]', [$this->getMockQueryBuilder()]);
		$nop1 = function() {};
		$nop2 = function() {};
		$builder->setEagerLoads(['foo' => $nop1, 'foo.bar' => $nop2]);
		$builder->shouldAllowMockingProtectedMethods()->shouldReceive('loadRelation')->with(['models'], 'foo', $nop1)->andReturn(
            ['foo']
        );

		$results = $builder->eagerLoadRelations(['models']);
		$this->assertEquals(['foo'], $results);
	}


	public function testRelationshipEagerLoadProcess()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder[getRelation]', [$this->getMockQueryBuilder()]);
		$builder->setEagerLoads(['orders' => function($query) { $_SERVER['__eloquent.constrain'] = $query; }]);
		$relation = m::mock('stdClass');
		$relation->shouldReceive('addEagerConstraints')->once()->with(['models']);
		$relation->shouldReceive('initRelation')->once()->with(['models'], 'orders')->andReturn(['models']);
		$relation->shouldReceive('getEager')->once()->andReturn(['results']);
		$relation->shouldReceive('match')->once()->with(['models'], ['results'], 'orders')->andReturn(['models.matched']
        );
		$builder->shouldReceive('getRelation')->once()->with('orders')->andReturn($relation);
		$results = $builder->eagerLoadRelations(['models']);

		$this->assertEquals(['models.matched'], $results);
		$this->assertEquals($relation, $_SERVER['__eloquent.constrain']);
		unset($_SERVER['__eloquent.constrain']);
	}


	public function testGetRelationProperlySetsNestedRelationships()
	{
		$builder = $this->getBuilder();
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('orders')->once()->andReturn($relation = m::mock('stdClass'));
		$relationQuery = m::mock('stdClass');
		$relation->shouldReceive('getQuery')->andReturn($relationQuery);
		$relationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);
		$builder->setEagerLoads(['orders' => null, 'orders.lines' => null, 'orders.lines.details' => null]);

		$relation = $builder->getRelation('orders');
	}


	public function testGetRelationProperlySetsNestedRelationshipsWithSimilarNames()
	{
		$builder = $this->getBuilder();
		$builder->setModel($this->getMockModel());
		$builder->getModel()->shouldReceive('orders')->once()->andReturn($relation = m::mock('stdClass'));
		$builder->getModel()->shouldReceive('ordersGroups')->once()->andReturn($groupsRelation = m::mock('stdClass'));

		$relationQuery = m::mock('stdClass');
		$relation->shouldReceive('getQuery')->andReturn($relationQuery);

		$groupRelationQuery = m::mock('stdClass');
		$groupsRelation->shouldReceive('getQuery')->andReturn($groupRelationQuery);
		$groupRelationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);

		$builder->setEagerLoads(
            ['orders' => null, 'ordersGroups' => null, 'ordersGroups.lines' => null, 'ordersGroups.lines.details' => null]
        );

		$relation = $builder->getRelation('orders');
		$relation = $builder->getRelation('ordersGroups');
	}


	public function testEagerLoadParsingSetsProperRelationships()
	{
		$builder = $this->getBuilder();
		$builder->with(['orders', 'orders.lines']);
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with('orders', 'orders.lines');
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with(['orders.lines']);
		$eagers = $builder->getEagerLoads();

		$this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertInstanceOf('Closure', $eagers['orders.lines']);

		$builder = $this->getBuilder();
		$builder->with(['orders' => function() { return 'foo'; }]);
		$eagers = $builder->getEagerLoads();

		$this->assertEquals('foo', $eagers['orders']());

		$builder = $this->getBuilder();
		$builder->with(['orders.lines' => function() { return 'foo'; }]);
		$eagers = $builder->getEagerLoads();

		$this->assertInstanceOf('Closure', $eagers['orders']);
		$this->assertNull($eagers['orders']());
		$this->assertEquals('foo', $eagers['orders.lines']());
	}


	public function testQueryPassThru()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('foobar')->once()->andReturn('foo');

		$this->assertInstanceOf(Builder::class, $builder->foobar());

		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('insert')->once()->with(['bar'])->andReturn('foo');

		$this->assertEquals('foo', $builder->insert(['bar']));
	}


	public function testQueryScopes()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('from');
		$builder->getQuery()->shouldReceive('where')->once()->with('foo', 'bar');
		$builder->setModel($model = new EloquentBuilderTestScopeStub);
		$result = $builder->approved();

		$this->assertEquals($builder, $result);
	}


	public function testNestedWhere()
	{
		$nestedQuery = m::mock(Builder::class);
		$nestedRawQuery = $this->getMockQueryBuilder();
		$nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
		$model = $this->getMockModel()->makePartial();
		$model->shouldReceive('newQueryWithoutScopes')->once()->andReturn($nestedQuery);
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('from');
		$builder->setModel($model);
		$builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'and');
		$nestedQuery->shouldReceive('foo')->once();

		$result = $builder->where(function($query) { $query->foo(); });
		$this->assertEquals($builder, $result);
	}


	public function testRealNestedWhereWithScopes()
	{
		$model = new EloquentBuilderTestNestedStub;
		$this->mockConnectionForModel($model, 'SQLite');
		$query = $model->newQuery()->where('foo', '=', 'bar')->where(function($query) { $query->where('baz', '>', 9000); });
		$this->assertEquals('select * from "table" where "table"."deleted_at" is null and "foo" = ? and ("baz" > ?)', $query->toSql());
		$this->assertEquals(['bar', 9000], $query->getBindings());
	}


	public function testSimpleWhere()
	{
		$builder = $this->getBuilder();
		$builder->getQuery()->shouldReceive('where')->once()->with('foo', '=', 'bar');
		$result = $builder->where('foo', '=', 'bar');
		$this->assertEquals($result, $builder);
	}


	public function testDeleteOverride()
	{
		$builder = $this->getBuilder();
		$builder->onDelete(function($builder)
		{
			return ['foo' => $builder];
		});
		$this->assertEquals(['foo' => $builder], $builder->delete());
	}


	public function testHasNestedWithConstraints()
	{
		$model = new EloquentBuilderTestModelParentStub;

		$builder = $model->whereHas('foo', function ($q) {
			$q->whereHas('bar', function ($q) {
				$q->where('baz', 'bam');
			});
		})->toSql();

		$result = $model->whereHas('foo.bar', function ($q) {
			$q->where('baz', 'bam');
		})->toSql();

		$this->assertEquals($builder, $result);
	}


	public function testHasNested()
	{
		$model = new EloquentBuilderTestModelParentStub;

		$builder = $model->whereHas('foo', function ($q) {
			$q->has('bar');
		});

		$result = $model->has('foo.bar')->toSql();

		$this->assertEquals($builder->toSql(), $result);
	}


	protected function mockConnectionForModel($model, $database)
	{
		$grammarClass = 'Illuminate\Database\Query\Grammars\\'.$database.'Grammar';
		$processorClass = 'Illuminate\Database\Query\Processors\\'.$database.'Processor';
		$grammar = new $grammarClass;
		$processor = new $processorClass;
		$connection = m::mock(ConnectionInterface::class, ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]
        );
		$resolver = m::mock(ConnectionResolverInterface::class, ['connection' => $connection]);
		$class = get_class($model);
		$class::setConnectionResolver($resolver);
	}


	protected function getBuilder()
	{
		return new Builder($this->getMockQueryBuilder());
	}


	protected function getMockModel()
	{
		$model = m::mock(Model::class);
		$model->shouldReceive('getKeyName')->andReturn('foo');
		$model->shouldReceive('getTable')->andReturn('foo_table');
		$model->shouldReceive('getQualifiedKeyName')->andReturn('foo_table.foo');
		return $model;
	}


	protected function getMockQueryBuilder()
	{
		$query = m::mock(\Illuminate\Database\Query\Builder::class);
		$query->shouldReceive('from')->with('foo_table');
		return $query;
	}

}

class EloquentBuilderTestModelStub extends Illuminate\Database\Eloquent\Model {}

class EloquentBuilderTestScopeStub extends Illuminate\Database\Eloquent\Model {
	public function scopeApproved($query)
	{
		$query->where('foo', 'bar');
	}
}

class EloquentBuilderTestWithTrashedStub extends Illuminate\Database\Eloquent\Model {
	use Illuminate\Database\Eloquent\SoftDeletingTrait;
	protected $table = 'table';
	public function getKeyName() { return 'foo'; }
}

class EloquentBuilderTestNestedStub extends Illuminate\Database\Eloquent\Model {
	protected $table = 'table';
	use Illuminate\Database\Eloquent\SoftDeletingTrait;
}

class EloquentBuilderTestListsStub {
	protected $attributes;
	public function __construct($attributes)
	{
		$this->attributes = $attributes;
	}
	public function __get($key)
	{
		return 'foo_' . $this->attributes[$key];
	}
}

class EloquentBuilderTestModelParentStub extends Illuminate\Database\Eloquent\Model {
	public function foo()
	{
		return $this->belongsTo('EloquentBuilderTestModelCloseRelatedStub');
	}
}

class EloquentBuilderTestModelCloseRelatedStub extends Illuminate\Database\Eloquent\Model {
	public function bar()
	{
		return $this->hasMany('EloquentBuilderTestModelFarRelatedStub');
	}
}

class EloquentBuilderTestModelFarRelatedStub extends Illuminate\Database\Eloquent\Model {}
