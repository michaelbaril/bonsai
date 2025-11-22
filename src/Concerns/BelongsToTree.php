<?php

namespace Baril\Bonsai\Concerns;

use Baril\Bonsai\Relations\BelongsToManyThroughClosures;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTree
{
    use HasAncestors;
    use HasClosures;
    use HasDescendants;
    use ManagesClosures;

    /**
     * @deprecated
     * 
     * Shortcut method that returns a collection of the tree roots, with their
     * eager-loaded descendants.
     *
     * @param  int|null  $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree($depth = null)
    {
        return static::query()->onlyRoots()->with([
            'descendants' => function (BelongsToManyThroughClosures $relation) use ($depth) {
                if (null !== $depth) {
                    $relation->maxDepth($depth);
                }
            },
        ])->get();
    }

    /**
     * @deprecated
     * 
     * Return the depth of the tree (0 if the tree is flat).
     *
     * @return int
     */
    public static function getTreeDepth()
    {
        $instance = new static();

        return $instance->newClosureQuery()->max('depth');
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    /**
     * @deprecated Use ->onlyRoots() instead
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $bool
     * @return void
     */
    public function scopeWhereIsRoot(Builder $query, $bool = true)
    {
        if ($bool) {
            $this->scopeOnlyRoots($query);
        } else {
            $this->scopeWithoutRoots($query);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnlyRoots(Builder $query)
    {
        $query->where(
            $this->getParentForeignKeyName(),
            '=',
            null
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeWithoutRoots(Builder $query)
    {
        $query->where(
            $this->getParentForeignKeyName(),
            '!=',
            null
        );
    }

    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->getParentKey() === null;
    }

    /**
     * @deprecated
     * 
     * Deletes the model after having attached its children to its parent.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function deleteNode()
    {
        if ($this->parent) {
            $this->parent->children()->saveMany($this->children);
        } else {
            $this->children()->get([
                $this->getKeyName(),
                $this->getParentForeignKeyName(),
            ])->each(function ($child) {
                $child->parent()->dissociate();
                $child->save();
            });
        }

        return $this->delete();
    }

    /**
     * @return int
     */
    public function deleteTree()
    {
        return $this
            ->descendants()
            ->withSelf()
            ->orderByDepth('desc')
            ->select(array_filter([
                $this->getKeyName(),
                $this->usesTimestamps() ? $this->getUpdatedAtColumn() : null,
                static::isSoftDeletable() ? $this->getDeletedAtColumn() : null,
            ]))
            ->cursor()
            ->map
            ->delete()
            ->sum();
    }

    /**
     * Attach $newChild to $this.
     * 
     * @param  static  $newChild
     * @return $this
     */
    public function graft($newChild)
    {
        $this->children()->save($newChild);

        return $this;
    }

    /**
     * Attach $this to $newParent.
     * 
     * @param  static  $newParent
     * @return $this
     */
    public function graftOnto($newParent)
    {
        $this->parent()->associate($newParent);
        $this->save();

        return $this;
    }

    /**
     * Detach $this from its current parent and make it a new root.
     * 
     * @return $this
     */
    public function cut()
    {
        $this->parent()->dissociate();
        $this->save();

        return $this;
    }
}
