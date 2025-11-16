<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\Relation
 */
trait AutoloadsOtherRelations
{
    protected $otherRelationsAutoloads;

    public function autoloads($relation, $callback = null)
    {
        $this->otherRelationsAutoloads[$relation] = $callback ?? function ($models, $results) {
            return [$models, $results];
        };

        return $this;
    }

    /**
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::getResults()
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        $results = parent::getResults();

        $this->matchOtherRelations([$this->parent], $results);
        $this->matchOtherRelations($results->all(), $results);

        return $results;
    }

    /**
     * Match the eagerly loaded results to their parents.
     * 
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::match()
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        parent::match($models, $results, $relation);

        $this->matchOtherRelations($models, $results);
        $this->matchOtherRelations($results->all(), $results);

        return $models;
    }

    protected function matchOtherRelations(array $models, EloquentCollection $results)
    {
        foreach ($this->otherRelationsAutoloads as $relation => $callback) {
            list($models, $results) = $callback($models, $results);
            $this->matchOtherRelation($models, $results, $relation);
        }
    }

    /**
     * On $this, and on each of the related models that were loaded by
     * a "through-closures" relation (eg. "ancestors"), load the corresponding
     * "closed" relation (eg. "parent").
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array
     */
    protected function matchOtherRelation(array $models, EloquentCollection $results, $relation)
    {
        $model = $models[0] ?? null;
        if (!$model) {
            return $models;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Relations\Relation
         */
        $relationObject = Relation::noConstraints(function () use ($model, $relation) {
            return $model->$relation();
        });

        // Prevents an unneeded query in case we try to access the relation on a leaf/root:
        $relationObject->initRelation($models, $relation);

        // Set relation for all related models and parent model:
        return $relationObject->match(
            $models,
            $results,
            $relation
        );
    }
}
