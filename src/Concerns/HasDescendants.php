<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasDescendants
{
    // ========================================================================
    // RELATIONS
    // ========================================================================

    /**
     * One-to-many relation to the children nodes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(static::class, $this->getParentForeignKeyName());
    }

    /**
     * Many-to-many relation to the descendants through the closure table.
     *
     * @return \Baril\Bonsai\Relations\BelongsToManyThroughClosures<static::class, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function descendants()
    {
        return
            $this->belongsToManyThroughClosures(
                static::class,
                $this->getClosureTable(),
                'ancestor_id',
                'descendant_id',
                'descendants'
            )
            ->withoutSelf()
            ->closes('children');
    }

    /**
     * One-to-many relationships to the descending closures.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Baril\Bonsai\Closure, $this>
     */
    public function descendingClosures()
    {
        return $this->hasManyClosuresWith(
            static::class,
            $this->getClosureTable(),
            'ancestor_id',
            'descendant_id'
        );
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
    public function scopeWithDescendants(Builder $query, $depth = null, $constraints = null)
    {
        $this->scopeWithManyThroughClosures($query, 'descendants', $depth, $constraints);
    }

    /**
     * @deprecated Use ->onlyLeaves() instead
     * 
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $bool
     * @return void
     */
    public function scopeWhereIsLeaf(Builder $query, $bool = true)
    {
        $this->scopeHasChildren($query, !$bool);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnlyLeaves(Builder $query)
    {
        $this->scopeHasChildren($query, false);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeWithoutLeaves(Builder $query)
    {
        $this->scopeHasChildren($query);
    }

    /**
     * @deprecated
     * 
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $bool
     * @return void
     */
    public function scopeWhereHasChildren(Builder $query, $bool = true)
    {
        $this->scopeHasChildren($query, $bool);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $bool
     * @return void
     */
    public function scopeHasChildren(Builder $query, $bool = true)
    {
        if ($bool) {
            $query->has('children');
        } else {
            $query->doesntHave('children');
        }
    }

    /**
     * @deprecated
     * 
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $descendant
     * @param  int|null  $maxDepth
     * @param  bool  $withSelf
     * @return void
     */
    public function scopeWhereIsAncestorOf(Builder $query, $descendant, $maxDepth = null, $withSelf = false)
    {
        $this->scopeAncestorsOf($query, $descendant, $maxDepth, $withSelf);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $descendant
     * @param  int|null  $maxDepth
     * @param  bool  $withSelf
     * @return void
     */
    public function scopeAncestorsOf(Builder $query, $descendant, $maxDepth = null, $withSelf = false)
    {
        $this->scopeWhereHasClosuresWith(
            $query,
            $descendant,
            'descendingClosures',
            'whereDescendant',
            $maxDepth,
            $withSelf
        );
    }

    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return !$this->hasChildren();
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function isParentOf(Model $node)
    {
        return $node->isChildOf($this);
    }

    /**
     * @param  mixed|\Illuminate\Database\Eloquent\Model  $node
     * @return bool
     */
    public function isAncestorOf($node)
    {
        return $this->descendingClosures()
            ->whereDescendant($node)
            ->exists();
    }

    /**
     * Returns the depth of the subtree of which $this is a root.
     *
     * @return int
     */
    public function getSubtreeDepth()
    {
        return (int) $this->descendants()->max('depth');
    }
}
