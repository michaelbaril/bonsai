<?php

namespace Baril\Bonsai\Relations\Concerns;

use Baril\Bonsai\Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
trait InteractsWithClosureTable
{
    use AutoloadsOtherRelations;
    use IsReadOnly;

    /**
     * @var int|null
     */
    protected $depth = null;

    /**
     * @param  string  $relation
     * @return $this
     */
    public function closes($relation, $callback = null)
    {
        $defaultCallback = function (array $models, EloquentCollection $results) {
            // When the relation has been queried with a max depth,
            // we don't want the closed relation to be set to null
            // or empty collection on models that belong to the
            // last level before the limit.
            if (null !== $this->depth) {
                $models = array_filter(
                    $models,
                    function ($model) {
                        $depth = $model->closure->depth ?? 0;
                        return $depth < $this->depth;
                    }
                );
            }

            return [
                $models,
                $results->unique()
            ];
        };

        return $this->autoloads(
            $relation,
            $callback
                ? function ($models, $results) use ($callback, $defaultCallback) {
                    list($models, $results) = $defaultCallback($models, $results);
                    return $callback($models, $results);
                }
                : $defaultCallback
        );
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        parent::addConstraints();

        $this
            ->as('closure')
            ->using(Closure::class)
            ->withPivot('depth');
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);

        $query->macro('maxDepth', function ($query, $depth) {
            $query->where($this->qualifyPivotColumn('depth'), '<=', $depth);
        });

        return $query;
    }

    /**
     * @deprecated
     *
     * @return $this
     */
    public function excludingSelf()
    {
        return $this->withoutSelf();
    }

    /**
     * @deprecated
     *
     * @return $this
     */
    public function includingSelf()
    {
        return $this->withSelf();
    }

    /**
     * @deprecated
     *
     * @param  int  $depth
     * @return $this
     */
    public function upToDepth($depth)
    {
        return $this->maxDepth($depth);
    }

    /**
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::wherePivot()
     *
     * @param  int  $depth
     * @return $this
     */
    public function maxDepth($depth)
    {
        // We'll need the depth again when we match the eager-loaded models:
        $this->depth = $depth;
        return $this->wherePivot('depth', '<=', $depth);
    }

    /**
     * @param  string  $direction
     * @return $this
     */
    public function orderByDepth($direction = 'asc')
    {
        return $this->orderBy($this->table . '.depth', $direction);
    }
}
