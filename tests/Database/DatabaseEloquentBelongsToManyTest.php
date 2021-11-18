<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseEloquentBelongsToManyTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testModelsAreProperlyHydrated()
    {
        $model1 = new EloquentBelongsToManyModelStub;
        $model1->fill(['name' => 'taylor', 'pivot_user_id' => 1, 'pivot_role_id' => 2]);
        $model2 = new EloquentBelongsToManyModelStub;
		$model2->fill(['name' => 'dayle', 'pivot_user_id' => 3, 'pivot_role_id' => 4]);
		$models = [$model1, $model2];

		$baseBuilder = m::mock(\Illuminate\Database\Query\Builder::class);

		$relation = $this->getRelation();
		$relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
		$relation->getQuery()->shouldReceive('addSelect')->once()->with(
            ['roles.*', 'user_role.user_id as pivot_user_id', 'user_role.role_id as pivot_role_id']
        )->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
		$relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
		$results = $relation->get();

		$this->assertInstanceOf(Collection::class, $results);

		// Make sure the foreign keys were set on the pivot models...
		$this->assertEquals('user_id', $results[0]->pivot->getForeignKey());
		$this->assertEquals('role_id', $results[0]->pivot->getOtherKey());

		$this->assertEquals('taylor', $results[0]->name);
		$this->assertEquals(1, $results[0]->pivot->user_id);
		$this->assertEquals(2, $results[0]->pivot->role_id);
		$this->assertEquals('foo.connection', $results[0]->pivot->getConnectionName());
		$this->assertEquals('dayle', $results[1]->name);
		$this->assertEquals(3, $results[1]->pivot->user_id);
		$this->assertEquals(4, $results[1]->pivot->role_id);
		$this->assertEquals('foo.connection', $results[1]->pivot->getConnectionName());
		$this->assertEquals('user_role', $results[0]->pivot->getTable());
		$this->assertTrue($results[0]->pivot->exists);
	}


	public function testTimestampsCanBeRetrievedProperly()
	{
		$model1 = new EloquentBelongsToManyModelStub;
		$model1->fill(['name' => 'taylor', 'pivot_user_id' => 1, 'pivot_role_id' => 2]);
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->fill(['name' => 'dayle', 'pivot_user_id' => 3, 'pivot_role_id' => 4]);
		$models = [$model1, $model2];

		$baseBuilder = m::mock(\Illuminate\Database\Query\Builder::class);

		$relation = $this->getRelation()->withTimestamps();
		$relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
		$relation->getQuery()->shouldReceive('addSelect')->once()->with([
			'roles.*',
			'user_role.user_id as pivot_user_id',
			'user_role.role_id as pivot_role_id',
			'user_role.created_at as pivot_created_at',
			'user_role.updated_at as pivot_updated_at',
        ])->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
		$relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
		$results = $relation->get();
	}


	public function testModelsAreProperlyMatchedToParents()
	{
		$relation = $this->getRelation();

		$result1 = new EloquentBelongsToManyModelPivotStub;
		$result1->pivot->user_id = 1;
		$result2 = new EloquentBelongsToManyModelPivotStub;
		$result2->pivot->user_id = 2;
		$result3 = new EloquentBelongsToManyModelPivotStub;
		$result3->pivot->user_id = 2;

		$model1 = new EloquentBelongsToManyModelStub;
		$model1->id = 1;
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->id = 2;
		$model3 = new EloquentBelongsToManyModelStub;
		$model3->id = 3;

		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$models = $relation->match([$model1, $model2, $model3], new Collection([$result1, $result2, $result3]), 'foo');

		$this->assertEquals(1, $models[0]->foo[0]->pivot->user_id);
		$this->assertCount(1, $models[0]->foo);

		$this->assertEquals(2, $models[1]->foo[0]->pivot->user_id);
		$this->assertEquals(2, $models[1]->foo[1]->pivot->user_id);
        $this->assertCount(2, $models[1]->foo);
        $this->assertEmpty($models[2]->foo);
	}


	public function testRelationIsProperlyInitialized()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array = []) { return new Collection($array); });
		$model = m::mock(Model::class);
		$model->shouldReceive('setRelation')->once()->with('foo', m::type(Collection::class));
		$models = $relation->initRelation([$model], 'foo');

		$this->assertEquals([$model], $models);
	}


	public function testEagerConstraintsAreProperlyAdded()
	{
		$relation = $this->getRelation();
		$relation->getQuery()->shouldReceive('whereIn')->once()->with('user_role.user_id', [1, 2]);
		$model1 = new EloquentBelongsToManyModelStub;
		$model1->id = 1;
		$model2 = new EloquentBelongsToManyModelStub;
		$model2->id = 2;
		$relation->addEagerConstraints([$model1, $model2]);
	}


	public function testAttachInsertsPivotTableRecord()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar']])->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, ['foo' => 'bar']);
	}


	public function testAttachMultipleInsertsPivotTableRecord()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
			[
				['user_id' => 1, 'role_id' => 2, 'foo' => 'bar'],
				['user_id' => 1, 'role_id' => 3, 'baz' => 'boom', 'foo' => 'bar'],
            ]
		)->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach([2, 3 => ['baz' => 'boom']], ['foo' => 'bar']);
	}


	public function testAttachInsertsPivotTableRecordWithTimestampsWhenNecessary()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$relation->withTimestamps();
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
            [['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time', 'updated_at' => 'time']]
        )->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, ['foo' => 'bar']);
	}


	public function testAttachInsertsPivotTableRecordWithACreatedAtTimestamp()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$relation->withPivot('created_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
            [['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time']]
        )->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, ['foo' => 'bar']);
	}


	public function testAttachInsertsPivotTableRecordWithAnUpdatedAtTimestamp()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$relation->withPivot('updated_at');
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(
            [['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'updated_at' => 'time']]
        )->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, ['foo' => 'bar']);
	}


	public function testDetachRemovesPivotTableRecord()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', [1, 2, 3]);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach([1, 2, 3]));
	}


	public function testDetachWithSingleIDRemovesPivotTableRecord()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('role_id', [1]);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach([1]));
	}


	public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touchIfTouching'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$query->shouldReceive('whereIn')->never();
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach());
	}


	public function testCreateMethodCreatesNewModelAndInsertsAttachmentRecord()
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach'], $this->getRelationArguments());
		$relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'))->with(
            ['attributes']
        );
		$model->shouldReceive('save')->once();
		$model->shouldReceive('getKey')->andReturn('foo');
		$relation->expects($this->once())->method('attach')->with('foo', ['joining']);

		$this->assertEquals($model, $relation->create(['attributes'], ['joining']));
	}


	/**
	 * @dataProvider syncMethodListProvider
	 */
	public function testSyncMethodSyncsIntermediateTableWithGivenArray($list)
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach', 'detach'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo([]), $this->equalTo(false));
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->getRelated()->shouldReceive('touches')->andReturn(false);
		$relation->getParent()->shouldReceive('touches')->andReturn(false);

		$this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync($list));
	}


	public function syncMethodListProvider()
	{
		return [
			[[2, 3, 4]],
			[['2', '3', '4']],
        ];
	}


	public function testSyncMethodSyncsIntermediateTableWithGivenArrayAndAttributes()
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(
            ['baz' => 'qux']
        ), $this->equalTo(false))->willReturn(
            true
        );
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertEquals(
            ['attached' => [4], 'detached' => [1], 'updated' => [3]], $relation->sync(
            [2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]
        ));
	}


	public function testSyncMethodDoesntReturnValuesThatWereNotUpdated()
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
		$relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(
            ['baz' => 'qux']
        ), $this->equalTo(false))->willReturn(
            false
        );
		$relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertEquals(
            ['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync(
            [2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]
        ));
	}


	public function testTouchMethodSyncsTimestamps()
	{
		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
		$relation->getRelated()->shouldReceive('freshTimestamp')->andReturn(100);
		$relation->getRelated()->shouldReceive('getQualifiedKeyName')->andReturn('table.id');
		$relation->getQuery()->shouldReceive('select')->once()->with('table.id')->andReturn($relation->getQuery());
		$relation->getQuery()->shouldReceive('lists')->once()->with('id')->andReturn([1, 2, 3]);
		$relation->getRelated()->shouldReceive('newQuery')->once()->andReturn($query = m::mock('StdClass'));
		$query->shouldReceive('whereIn')->once()->with('id', [1, 2, 3])->andReturn($query);
		$query->shouldReceive('update')->once()->with(['updated_at' => 100]);

		$relation->touch();
	}


	public function testTouchIfTouching()
	{
		$relation = $this->getMock(BelongsToMany::class, ['touch', 'touchingParent'], $this->getRelationArguments());
		$relation->expects($this->once())->method('touchingParent')->willReturn(true);
		$relation->getParent()->shouldReceive('touch')->once();
		$relation->getParent()->shouldReceive('touches')->once()->with('relation_name')->andReturn(true);
		$relation->expects($this->once())->method('touch');

		$relation->touchIfTouching();
	}


	public function testSyncMethodConvertsCollectionToArrayOfKeys()
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);

		$collection = m::mock(Collection::class);
		$collection->shouldReceive('modelKeys')->once()->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->willReturn(
            [1 => [], 2 => [], 3 => []]
        );
		$relation->sync($collection);
	}


	public function testWherePivotParamsUsedForNewQueries()
	{
		$relation = $this->getMock(BelongsToMany::class, ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());

		// we expect to call $relation->wherePivot()
		$relation->getQuery()->shouldReceive('where')->once()->andReturn($relation);

		// Our sync() call will produce a new query
		$mockQueryBuilder = m::mock('stdClass');
		$query            = m::mock('stdClass');
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder);
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);

		// BelongsToMany::newPivotStatement() sets this
		$query->shouldReceive('from')->once()->with('user_role')->andReturn($query);

		// BelongsToMany::newPivotQuery() sets this
		$query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);

		// This is our test! The wherePivot() params also need to be called
		$query->shouldReceive('where')->once()->with('foo', '=', 'bar')->andReturn($query);

		// This is so $relation->sync() works
		$query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
		$relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->willReturn(
            [1 => [], 2 => [], 3 => []]
        );


		$relation = $relation->wherePivot('foo', '=', 'bar'); // these params are to be stored
		$relation->sync([1,2,3]); // triggers the whole process above
	}


	public function getRelation()
	{
		list($builder, $parent) = $this->getRelationArguments();

		return new BelongsToMany($builder, $parent, 'user_role', 'user_id', 'role_id', 'relation_name');
	}


	public function getRelationArguments()
	{
		$parent = m::mock(Model::class);
		$parent->shouldReceive('getKey')->andReturn(1);
		$parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
		$parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

		$builder = m::mock(Builder::class);
		$related = m::mock(Model::class);
		$builder->shouldReceive('getModel')->andReturn($related);

		$related->shouldReceive('getTable')->andReturn('roles');
		$related->shouldReceive('getKeyName')->andReturn('id');
		$related->shouldReceive('newPivot')->andReturnUsing(function()
		{
			$reflector = new ReflectionClass(Pivot::class);
			return $reflector->newInstanceArgs(func_get_args());
		});

		$builder->shouldReceive('join')->once()->with('user_role', 'roles.id', '=', 'user_role.role_id');
		$builder->shouldReceive('where')->once()->with('user_role.user_id', '=', 1);

		return [$builder, $parent, 'user_role', 'user_id', 'role_id', 'relation_name'];
	}

}

class EloquentBelongsToManyModelStub extends Illuminate\Database\Eloquent\Model {
	protected $guarded = [];
}

class EloquentBelongsToManyModelPivotStub extends Illuminate\Database\Eloquent\Model {
	public $pivot;
	public function __construct()
	{
		$this->pivot = new EloquentBelongsToManyPivotStub;
	}
}

class EloquentBelongsToManyPivotStub {
	public $user_id;
}
