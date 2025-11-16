<?php

namespace Baril\Bonsai\Concerns;

trait BelongsToOrderedTree
{
    use BelongsToTree {
        children as _children;
        getTree as _getTree;
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
     * Set the given relationship on the model.
     * 
     * @see \Illuminate\Database\Eloquent\Model::setRelation()
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        if ('children' == $relation) {
            $value = $value->sortBy($this->getOrderColumn());
        }

        return parent::setRelation($relation, $value);
    }
}
