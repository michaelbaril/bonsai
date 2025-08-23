<?php

namespace Baril\Bonsai\Concerns;

use Baril\Bonsai\Relations\BelongsToManyThroughClosures;
use Baril\Bonsai\Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasClosures
{
    /**
     * @var string
     */
    protected $_closureTable;

    /**
     * Return the name of the closure table, optionally aliased.
     *
     * @param  string|null  $as
     * @return string
     */
    public function getClosureTable()
    {
        return $this->_closureTable
            = $this->_closureTable
            ?? $this->ancestors()->getTable();
    }

    /**
     * Instanciate a new query builder on the closure table,
     * optionally aliased.
     *
     * @param  string|null  $as
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newClosureQuery($as = null)
    {
        return $this
            ->newClosure(new static(), [], false)
            ->newQuery()
            ->when($as, function ($query, $as) {
                $query->as($as);
            });
    }

    /**
     * Create a new closure model instance.
     *
     * @see \Illuminate\Database\Eloquent\Model::newPivot()
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  array<string, mixed>  $attributes
     * @param  bool  $exists
     * @param  string|null  $table
     * @return \Baril\Bonsai\Closure
     */
    public function newClosure(Model $parent, array $attributes, $exists, $table = null)
    {
        return $this->newPivot($parent, $attributes, $table ?? $this->getClosureTable(), $exists, Closure::class);
    }

    /**
     * Define a belongs-to-many-through-closures relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $relationName
     * @return \Baril\Bonsai\Relations\BelongsToManyThroughClosures<TRelatedModel, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    protected function belongsToManyThroughClosures(
        $related,
        $table,
        $foreignPivotKey = 'descendant_id',
        $relatedPivotKey = 'ancestor_id',
        $relationName = 'ancestors'
    ) {
        $instance = $this->newRelatedInstance($related);

        return (new BelongsToManyThroughClosures(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $this->getKeyName(),
            $instance->getKeyName(),
            $relationName
        ));
    }

    /**
     * Define a one-to-many relationship to the closure table.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Baril\Bonsai\Closure, $this>
     */
    protected function hasManyClosuresWith($class, $table, $foreignPivotKey = 'descendant_id', $relatedPivotKey = 'ancestor_id')
    {
        $instance = $this->newRelatedInstance($class);
        $closure = $instance->newClosure($this, [], false, $table)
            ->setPivotKeys($foreignPivotKey, $relatedPivotKey)
            ->setRelatedModel($instance);

        return $this->newHasMany(
            $closure->newQuery(),
            $this,
            $closure->qualifyColumn($foreignPivotKey),
            $this->getKeyName()
        );
    }

    /**
     * Set the given relationship on the model.
     *
     * @see \Illuminate\Database\Eloquent\Model::setRelation()
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function setRelation($relationName, $value)
    {
        if (
            $this->isRelation($relationName)
            && ($relation = $this->$relationName()) instanceof BelongsToManyThroughClosures
            && ($closedRelationName = $relation->getClosedRelation())
        ) {
            $this->setClosedRelation($closedRelationName, $value);
        }

        return parent::setRelation($relationName, $value);
    }

    /**
     * On $this, and on each of the related models that were loaded by
     * a "through-closures" relation (eg. "ancestors"), load the corresponding
     * "closed" relation (eg. "parent").
     *
     * @param  string  $relationName
     * @param  \Illuminate\Database\Eloquent\Collection  $related
     * @return $this
     */
    public function setClosedRelation($relationName, $related)
    {
        $models = $related->merge([$this])->all();

        /**
         * @var \Illuminate\Database\Eloquent\Relations\Relation
         */
        $relation = Relation::noConstraints(function () use ($relationName) {
            return $this->$relationName();
        });

        // Prevents an unneeded query in case we try to access the relation on a leaf/root:
        $modelsWhereRelationShouldBeLoaded = $related->filter(function ($model) {
            return $model->closure->_remaining_depth !== 0;
        })->push($this)->all();
        $relation->initRelation($modelsWhereRelationShouldBeLoaded, $relationName);

        // Set relation for all related models and parent model:
        $relation->match(
            $models,
            $related,
            $relationName
        );

        return $this;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $relation
     * @param  int|null  $depth
     * @param  callable|null  $constraints
     * @return void
     */
    protected function scopeWithManyThroughClosures(Builder $query, $relation, $depth = null, $constraints = null)
    {
        $query->with([$relation => function ($query) use ($depth, $constraints) {
            if ($depth !== null) {
                $query->upToDepth($depth)->orderByDepth();
            }
            if ($constraints !== null) {
                $constraints($query);
            }
        }]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $related
     * @param  string  $relation
     * @param  string  $scope
     * @param  int|null  $maxDepth
     * @param  bool  $withSelf
     * @return void
     */
    protected function scopeWhereHasClosuresWith(Builder $query, $related, $relation, $scope, $maxDepth = null, $withSelf = false)
    {
        $relatedId = ($related instanceof Model) ? $related->getKey() : $related;

        $query->whereHas($relation, function ($query) use ($relatedId, $scope, $maxDepth, $withSelf) {
            $query->$scope($relatedId)
                ->when($maxDepth, function ($query, $maxDepth) {
                    $query->whereDepth('<=', $maxDepth);
                })
                ->when(!$withSelf, function ($query) {
                    $query->withoutSelf();
                });
        });
    }
}
