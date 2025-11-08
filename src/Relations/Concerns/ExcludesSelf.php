<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\Relation
 */
trait ExcludesSelf
{
    /**
     * @var bool
     */
    protected $excludeSelf = false;

    /**
     * @return static
     */
    public function withoutSelf()
    {
        $this->excludeSelf = true;

        if (static::$constraints) {
            // Exclude parent model from results:
            $this->getRelationQuery()->withGlobalScope(
                'excludeSelfFromResults',
                function ($query) {
                    return $query->whereKeyNot($this->parent->getKey());
                }
            );
        }

        return $this;
    }

    /**
     * @return static
     */
    public function withSelf()
    {
        $this->excludeSelf = false;

        if (static::$constraints) {
            $this->getRelationQuery()->withoutGlobalScope('excludeSelfFromResults');
        }

        return $this;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        return $this->excludeSelfFromMatchesIfExcluded(
            parent::match($models, $results, $relation),
            $relation
        );
    }

    /**
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $models
     * @param  string  $relation
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function excludeSelfFromMatchesIfExcluded(array $models, $relation)
    {
        if (! $this->excludeSelf) {
            return $models;
        }

        foreach ($models as $model) {
            $related = $model->getRelation($relation);
            if (
                $related instanceof EloquentCollection
                && $related->contains($model)
            ) {
                $model->setRelation($relation, $related->except($model->getKey()));
            }
            if (
                $related instanceof Model
                && $related->getTable() == $model->getTable()
                && $related->getKey() === $model->getKey()
            ) {
                $this->initRelation([$model], $relation);
            }
        }

        return $models;
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @see \Illuminate\Database\Eloquent\Relations\HasOneOrMany::getRelationExistenceQueryForSelfRelation()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $this->excludeSelfFromRelationExistenceQueryIfExcluded(
            parent::getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns),
            $parentQuery
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::getRelationExistenceQueryForSelfJoin()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function getRelationExistenceQueryForSelfJoin(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $this->excludeSelfFromRelationExistenceQueryIfExcluded(
            parent::getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns),
            $parentQuery
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */    
    protected function excludeSelfFromRelationExistenceQueryIfExcluded(
        Builder $query,
        Builder $parentQuery,
    )
    {
        return $query
            ->when($this->excludeSelf, function ($query) use ($parentQuery) {
                $query->withGlobalScope(
                    'excludeSelfFromResults',
                    function ($query) use ($parentQuery) {
                        $query->whereColumn(
                            $parentQuery->qualifyColumn($this->parent->getKeyName()),
                            '!=',
                            $query->qualifyColumn($this->related->getKeyName())
                        );
                    }
                );
                $query->macro('withSelf', function ($query) {
                    $query->withoutGlobalScope('excludeSelfFromResults');
                });
            });
    }
}
