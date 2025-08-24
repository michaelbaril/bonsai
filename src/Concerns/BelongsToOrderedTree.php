<?php

namespace Baril\Bonsai\Concerns;

trait BelongsToOrderedTree
{
    use BelongsToTree {
        children as _children;
        getTree as _getTree;
        setClosedRelation as _setClosedRelation;
    }
    use Orderable;

    /**
     * @return \Illuminate\Database\Eloquent\Relation\HasMany
     */
    public function children()
    {
        return $this->_children()->ordered();
    }

    /**
     * @param  int|null  $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree($depth = null)
    {
        return static::_getTree($depth)
            // Sort roots (the rest is already sorted):
            ->sortBy((new static())->getOrderColumn())
            ->values();
    }

    /**
     * @param  string  $relationName
     * @param  \Illuminate\Database\Eloquent\Collection  $related
     * @return $this
     */
    public function setClosedRelation($relationName, $related)
    {
        return $this->_setClosedRelation(
            $relationName,
            $related->sortBy($this->getOrderColumn())
        );
    }
}
