<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
        $models = parent::match($models, $results, $relation);

        if ($this->excludeSelf) {
            foreach ($models as $model) {
                $related = $model->getRelation($relation);
                if ($related instanceof EloquentCollection) {
                    $model->setRelation($relation, $related->except($model->getKey()));
                } elseif ($related->getTable() == $model->getTable() && $related->getKey() === $model->getKey()) {
                    $this->initRelation([$model], $relation);
                }
            }
        }

        return $models;
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @see \Illuminate\Database\Eloquent\Relations\Relation::getRelationExistenceQuery()
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $excludeSelf = $this->excludeSelf
            && $parentQuery->getQuery()->from == $query->getQuery()->from;

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->when($excludeSelf, function ($query) use ($parentQuery) {
                $query->whereColumn(
                    $parentQuery->qualifyColumn($this->parent->getKeyName()),
                    '!=',
                    $query->qualifyColumn($this->related->getKeyName())
                );
            });
    }
}
