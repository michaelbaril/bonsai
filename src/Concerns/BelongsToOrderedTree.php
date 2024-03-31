<?php

namespace Baril\Bonsai\Concerns;

use Baril\Orderly\Concerns\Orderable;

trait BelongsToOrderedTree
{
    use BelongsToTree {
        children as _children;
        setChildrenFromDescendants as _setChildrenFromDescendants;
        getTree as _getTree;
    }
    use Orderable;

    /**
     * @return string
     */
    public function getGroupColumn()
    {
        return $this->getParentForeignKeyName();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relation\HasMany
     */
    public function children()
    {
        return static::_children()->ordered();
    }

    protected function setChildrenFromDescendants($descendants)
    {
        $this->_setChildrenFromDescendants($descendants->sortBy($this->getOrderColumn()));
    }

    /**
     * @param int $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree($depth = null)
    {
        return static::_getTree($depth)->sortBy((new static)->getOrderColumn())->values();
    }
}
