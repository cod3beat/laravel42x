<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use L4\Tests\BackwardCompatibleTestCase;
use Mockery as m;

class DatabaseEloquentRelationTest extends BackwardCompatibleTestCase
{

    protected function tearDown(): void
    {
        m::close();
    }


    public function testSetRelationFail()
    {
        $parent = new EloquentRelationResetModelStub;
        $relation = new EloquentRelationResetModelStub;
        $parent->setRelation('test', $relation);
		$parent->setRelation('foo','bar');
		$this->assertTrue(!array_key_exists('foo', $parent->toArray()));
	}


	public function testTouchMethodUpdatesRelatedTimestamps()
	{
		$builder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
		$parent = m::mock(Model::class);
		$parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
		$builder->shouldReceive('getModel')->andReturn($related = m::mock('StdClass'));
		$builder->shouldReceive('where');
		$relation = new HasOne($builder, $parent, 'foreign_key', 'id');
		$related->shouldReceive('getTable')->andReturn('table');
		$related->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
		$related->shouldReceive('freshTimestampString')->andReturn(Carbon::now());
		$builder->shouldReceive('update')->once()->with(array('updated_at' => Carbon::now()));

		$relation->touch();
	}

	/**
	 * Testing to ensure loop does not occur during relational queries in global scopes
	 *
	 * Executing parent model's global scopes could result in an infinite loop when the
	 * parent model's global scope utilizes a relation in a query like has or whereHas
	 */
	public function testDonNotRunParentModelGlobalScopes()
	{
		/** @var Mockery\MockInterface $parent */
		$eloquentBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
		$queryBuilder = m::mock(Builder::class);
		$parent = m::mock('EloquentRelationResetModelStub')->makePartial();
		$grammar = m::mock(Grammar::class);

		$eloquentBuilder->shouldReceive('getModel')->andReturn($related = m::mock('StdClass'));
		$eloquentBuilder->shouldReceive('getQuery')->andReturn($queryBuilder);
		$queryBuilder->shouldReceive('getGrammar')->andReturn($grammar);
		$grammar->shouldReceive('wrap');
		$parent->shouldReceive('newQueryWithoutScopes')->andReturn($eloquentBuilder);

		//Test Condition
		$parent->shouldReceive('applyGlobalScopes')->andReturn($eloquentBuilder)->never();

		$relation = new EloquentRelationStub($eloquentBuilder, $parent);
		$relation->wrap('test');
	}

}

class EloquentRelationResetModelStub extends Illuminate\Database\Eloquent\Model {
	//Override method call which would normally go through __call()
	public function getQuery()
	{
		return $this->newQuery()->getQuery();
	}
}


class EloquentRelationResetStub extends Illuminate\Database\Eloquent\Builder {
	public function __construct() { $this->query = new EloquentRelationQueryStub; }
	public function getModel() { return new EloquentRelationResetModelStub; }
}


class EloquentRelationQueryStub extends Illuminate\Database\Query\Builder {
	public function __construct() {}
}

class EloquentRelationStub extends Relation
{
    public function addConstraints()
    {
    }

    public function addEagerConstraints(array $models)
    {
    }

    public function initRelation(array $models, $relation)
    {
    }

    public function match(array $models, Collection $results, $relation)
    {
    }

    public function getResults()
    {
    }
}
