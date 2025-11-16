<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * @deprecated Instead, use Orderable or Ordered trait together with BelongsToTree.
 */
trait BelongsToOrderedTree
{
    use BelongsToTree {
        children as _children;
        descendants as _descendants;
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
     * Many-to-many relation to the descendants through the closure table.
     *
     * @return \Baril\Bonsai\Relations\BelongsToManyThroughClosures<static::class, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function descendants()
    {
        return $this->_descendants()
            ->closes('children', function ($models, $results) {
                return [$models, $results->sortBy($this->getOrderColumn())];
            });
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
}
