<?php

namespace Baril\Bonsai;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Closure extends Pivot
{
    protected $as;

    /**
     * Alias for the query builder instance that references
     * this model.
     * 
     * @return string
     */
    protected function getAlias()
    {
        return $this->as ?? $this->getTable();
    }

    /** @inheritDoc */
    public function newInstance($attributes = [], $exists = false)
    {
        return parent::newInstance($attributes, $exists)
            ->setPivotKeys($this->foreignKey, $this->relatedKey)
            ->setParentModel($this->pivotParent)
            ->setRelatedModel($this->pivotRelated)
            ->hydrateRelatedKey($attributes);
    }

    /**
     * Set the parent model of the relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return $this
     */
    public function setParentModel(Model $parent)
    {
        $this->pivotParent = $parent;

        return $this;
    }

    /** @inheritDoc */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        return parent::setRawAttributes($attributes, $sync)
            ->hydrateRelatedKey($attributes);
    }

    /**
     * Hydrate the related model id when the closure is hydrated.
     *
     * @param  array  $attributes
     * @return $this
     */
    protected function hydrateRelatedKey($attributes)
    {
        if (
            array_key_exists($this->relatedKey, $attributes)
            && $this->pivotRelated->getKey() != $attributes[$this->relatedKey]
        ) {
            $this->pivotRelated = $this->pivotRelated
                ->newInstance([], true)
                ->setRawAttributes([
                    $this->pivotRelated->getKeyName() => $attributes[$this->relatedKey]
                ], true);
        }

        return $this;
    }

    /**
     * Belongs-to relation to the related model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function related()
    {
        return $this->belongsTo(
            get_class($this->pivotRelated),
            $this->getRelatedKey()
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $as
     * @return  void
     */
    public function scopeAs(Builder $query, $as)
    {
        $this->as = $as;

        $from = $query->getQuery()->from;
        $table = preg_split('/\s+as\s+/i', $from)[0];

        $query->getQuery()->from = "$table as $as";
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $as
     * @return  void
     */
    public function scopeSelfCrossJoin(Builder $query, $as)
    {
        $table = $this->getTable();

        $query->crossJoin("$table as $as");
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $as
     * @param  string  $first
     * @param  string  $second
     * @return  void
     */    
    public function scopeSelfJoin(Builder $query, $as, $first = null, $second = null)
    {
        $table = $this->getTable();

        $first = $first ?? $this->getRelatedKey();
        $second = $second ?? $first;

        if (false === strstr($first, '.')) {
            $first = $this->getAlias() . ".$first";
        }
        if (false === strstr($second, '.')) {
            $second = "$as.$second";
        }

        $query->join(
            "$table as $as",
            $first,
            '=',
            $second
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return  void
     */    
    public function scopeWhereAncestor(Builder $query, $node)
    {
        $value = $this->parseIds($node);

        $method = is_array($value) ? 'whereIn' : 'where';

        $query->$method('ancestor_id', $value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return  void
     */    
    public function scopeWhereDescendant(Builder $query, $node)
    {
        $value = $this->parseIds($node);

        $method = is_array($value) ? 'whereIn' : 'where';

        $query->$method('descendant_id', $value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $operator
     * @param  int  $value
     * @return  void
     */    
    public function scopeWhereDepth(Builder $query, $operator, $value)
    {
        $query->where('depth', $operator, $value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return  void
     */    
    public function scopeWithoutSelf(Builder $query)
    {
        $query->where('depth', '>', 0);
    }

    /**
     * Get the ID or IDs from the given mixed value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parseIds($value)
    {
        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (!is_iterable($value)) {
            return $value;
        }

        $parsedIds = [];
        foreach ($value as $id) {
            $parsedIds[] = $this->parseIds($id);
        }

        return $parsedIds;
    }
}
