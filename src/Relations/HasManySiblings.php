<?php

namespace Baril\Bonsai\Relations;

use Baril\Bonsai\Relations\Concerns\ExcludesSelf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasMany<TDeclaringModel, TDeclaringModel>
 */
class HasManySiblings extends HasMany
{
    use ExcludesSelf {
        getRelationExistenceQuery as _getRelationExistenceQuery;
        match as _match;
    }

    protected $withOrphans = false;

    /**
     * @return $this
     */
    public function withOrphans()
    {
        $this->withOrphans = true;

        if (static::$constraints) {
            $this->getRelationQuery()->withoutGlobalScope('withoutOrphans');
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutOrphans()
    {
        $this->withOrphans = false;

        if (static::$constraints) {
            $this->addWithoutOrphansGlobalScope();
        }

        return $this;
    }

    /**
     * @return void
     */
    protected function addWithoutOrphansGlobalScope()
    {
        $this->getRelationQuery()->withGlobalScope(
            'withoutOrphans',
            function ($query) {
                return $query->whereNotNull($this->foreignKey);
            }
        );
    }

    /** @inheritDoc */
    public function addConstraints()
    {
        if (static::$constraints) {
            $query = $this->getRelationQuery();

            $query->where($this->foreignKey, '=', $this->getParentKey());

            $this->addWithoutOrphansGlobalScope();
        }
    }

    /** @inheritDoc */
    public function addEagerConstraints(array $models)
    {
        $this->where(function ($nestedWhere) use ($models) {

            $keys = $this->getKeys($models, $this->localKey);

            $whereIn = $this->whereInMethod($this->parent, $this->localKey);

            $nestedWhere->{$whereIn}(
                $this->foreignKey,
                $keys
            );

            // At this point, the custom constraints that may have been
            // provided when calling with() have not been applied yet,
            // thus we can't trust $this->withOrphans. We have to include
            // the orphans in the query. We can still exclude them when
            // we do the matching.
            if (in_array(null, $keys, true)) {
                $nestedWhere->orWhereNull($this->foreignKey);
            }
        });
    }

    /** @inheritDoc */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        return $this->_match(
            $models,
            $results->when(! $this->withOrphans, function ($results) {
                $foreignKey = explode('.', $this->foreignKey);
                $foreignKey = end($foreignKey);
                return $results->whereNotNull($foreignKey);
            }),
            $relation
        );
    }

    /** @inheritDoc */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query = $this->_getRelationExistenceQuery($query, $parentQuery, $columns);

        if (! $this->withOrphans) {
            return $query;
        }

        $from = $query->getquery()->from;
        $segments = preg_split('/\s+as\s+/i', $from);
        $as = end($segments);

        return $query->orWhere(function ($nestedWhere) use ($as) {
            $nestedWhere->whereNull($this->getQualifiedParentKeyName());
            $nestedWhere->whereNull($as . '.' . $this->getForeignKeyName());
        });
    }
}
