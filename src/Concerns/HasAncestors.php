<?php

namespace Baril\Bonsai\Concerns;

use Baril\Bonsai\TreeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasAncestors
{
    /**
     * @var string
     */
    protected $_parentForeignKey;

    /**
     * Return the name of the "parent_id" column.
     *
     * @return string
     */
    public function getParentForeignKeyName()
    {
        return $this->_parentForeignKey = $this->_parentForeignKey
            ?? $this->parent()->getForeignKeyName();
    }

    /**
     * Return the value of the "parent_id" column.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent()->getParentKey();
    }

    // ========================================================================
    // RELATIONS
    // ========================================================================

    /**
     * Many-to-one relation to the parent node.
     * Override this method if the foreign key is not "parent_id".
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(
            static::class,
            // Explicitely setting the $parentForeignKey property
            // is @deprecated in favor of overriding this method
            property_exists($this, 'parentForeignKey')
                ? $this->parentForeignKey
                : 'parent_id'
        );
    }

    /**
     * Many-to-many relation to the ancestors through the closure table.
     * Override this method to customize the closure table name.
     *
     * @return \Baril\Bonsai\Relations\BelongsToManyThroughClosures<static::class, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function ancestors()
    {
        return
            $this->belongsToManyThroughClosures(
                static::class,
                // Explicitely setting the $closureTable property
                // is @deprecated in favor of overriding this method
                isset($this->closureTable)
                    ? $this->closureTable
                    : Str::snake(class_basename($this)) . '_tree',
            )
            ->withoutSelf()
            ->closes('parent');
    }

    /**
     * One-to-many relationships to the ascending closures.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Baril\Bonsai\Closure, $this>
     */
    public function ascendingClosures()
    {
        return $this->hasManyClosuresWith(static::class, $this->getClosureTable());
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|null  $depth
     * @param  callable|null  $constraints
     * @return void
     */
    public function scopeWithAncestors(Builder $query, $depth = null, $constraints = null)
    {
        $this->scopeWithManyThroughClosures($query, 'ancestors', $depth, $constraints);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $as
     * @return void
     */
    public function scopeWithDepth(Builder $query, $as = 'depth')
    {
        $query->withCount([
            "ascendingClosures as $as" => function ($query) {
                $query->withoutSelf();
            },
        ]);
    }

    /**
     * @deprecated
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $ancestor
     * @param  int|null  $maxDepth
     * @param  bool  $withSelf
     * @return void
     */
    public function scopeWhereIsDescendantOf(Builder $query, $ancestor, $maxDepth = null, $withSelf = false)
    {
        $this->scopeDescendantsOf($query, $ancestor, $maxDepth, $withSelf);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $ancestor
     * @param  int|null  $maxDepth
     * @param  bool  $withSelf
     * @return void
     */
    public function scopeDescendantsOf(Builder $query, $ancestor, $maxDepth = null, $withSelf = false)
    {
        $this->scopeWhereHasClosuresWith(
            $query,
            $ancestor,
            'ascendingClosures',
            'whereAncestor',
            $maxDepth,
            $withSelf
        );
    }

    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function isChildOf($node)
    {
        $nodeId = $node instanceof Model ? $node->getKey() : $node;

        return $nodeId == $this->getParentKey();
    }

    /**
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function isDescendantOf($node)
    {
        return $this->ascendingClosures()
            ->whereAncestor($node)
            ->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function isSiblingOf(Model $node)
    {
        return $node->getParentKey() == $this->getParentKey();
    }

    /**
     * Returns the closest common ancestor with the provided $node.
     * May return null if the tree has multiple roots and the 2 nodes have no
     * common ancestor.
     *
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function findCommonAncestorWith($node)
    {
        return $this->newCommonAncestorQuery($node)
            ->orderByDepth()
            ->first();
    }

    /**
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function hasCommonAncestorWith($node)
    {
        return $this->newCommonAncestorQuery($node)
            ->exists();
    }

    /**
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return \Baril\Bonsai\Relations\BelongsToManyThroughClosures
     */
    protected function newCommonAncestorQuery($node)
    {
        return $this->ancestors()
            ->withSelf()
            ->ancestorsOf($node, null, true);
    }

    /**
     * Returns the distance between $this and another $item.
     * May throw an exception if the tree has multiple roots and the 2 items
     * have no common ancestor.
     *
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return int
     * @throws \Baril\Bonsai\TreeException
     */
    public function getDistanceTo($node)
    {
        $commonAncestor = $this->findCommonAncestorWith($node);
        if (!$commonAncestor) {
            throw new TreeException('The items have no common ancestor!');
        }

        return $commonAncestor
            ->descendingClosures()
            ->whereDescendant([$this, $node])
            ->sum('depth');
    }

    /**
     * Return the depth of $this in the tree (0 is $this is a root).
     *
     * @return int
     */
    public function getDepth()
    {
        return $this->ancestors()->count();
    }
}
