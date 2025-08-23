<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTree
{
    use HasAncestors;
    use HasClosures;
    use HasDescendants;
    use ManagesClosures;

    /**
     * Shortcut method that returns a collection of the tree roots, with their
     * eager-loaded descendants.
     *
     * @param  int|null  $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree($depth = null)
    {
        return static::query()->onlyRoots()->withDescendants($depth)->get();
    }

    /**
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
        $this->scopeOnlyRoots($query, $bool);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $bool
     * @return void
     */
    public function scopeOnlyRoots(Builder $query, $bool = true)
    {
        $query->where(
            $this->getParentForeignKeyName(),
            ($bool ? '=' : '!='),
            null
        );
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->getParentKey() === null;
    }

    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
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
            $this->children->each(function ($child) {
                $child->parent()->dissociate();
                $child->save();
            });
        }

        return $this->delete();
    }

    /**
     * @deprecated
     *
     * @return bool|null
     */
    public function deleteTree()
    {
        return $this->deleteSubtree();
    }

    /**
     * Deletes the model and its descendants from the database.
     * 
     * @return bool|null
     */
    public function deleteSubtree()
    {
        $this->descendants()->withSelf()->delete();
        $this->deleteAllClosures();
    }
}
