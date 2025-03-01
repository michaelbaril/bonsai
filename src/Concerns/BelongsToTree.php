<?php

namespace Baril\Bonsai\Concerns;

use Baril\Bonsai\Relations\Closure;
use Baril\Bonsai\TreeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait BelongsToTree
{
    // protected $parentForeignKey Name of the foreign key for the "parent/children" relation (defaults to parent_id)
    // protected $closureTable Name of the closure table (defaults to [snake_cased_model_name]_tree)

    public static function bootBelongsToTree()
    {
        static::saving(function ($item) {
            $item->checkIfParentIdIsValid();
        });
        static::created(function ($item) {
            $item->refreshClosures(false);
        });
        static::updated(function ($item) {
            if ($item->isDirty($item->getParentForeignKeyName())) {
                $item->refreshClosures(true);
            }
        });
        static::deleting(function ($item) {
            if ($item->children()->exists()) {
                throw new TreeException('Can\'t delete an item with children!');
            }
            $item->deleteClosuresForLeaf();
        });
    }

    /**
     * Returns the name of the "parent_id" column.
     *
     * @return string
     */
    public function getParentForeignKeyName()
    {
        return property_exists($this, 'parentForeignKey') ? $this->parentForeignKey : 'parent_id';
    }

    /**
     * Returns the name of the closure table.
     *
     * @return string
     */
    public function getClosureTable()
    {
        return isset($this->closureTable) ? $this->closureTable : Str::snake(class_basename($this)) . '_tree';
    }

    /**
     * Deletes the model after having attached its children to its parent.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function deleteNode()
    {
        $parent = $this->parent;
        if ($parent) {
            $parent->children()->saveMany($this->children);
        } else {
            $this->children->each(function ($child) {
                $child->parent()->dissociate();
                $child->save();
            });
        }
        return $this->delete();
    }

    /**
     * Deletes the model and its descendants from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function deleteTree()
    {
        $this->getConnection()->transaction(function () {
            $table = $this->getTable();
            $closureTable = $this->getClosureTable();
            $this->descendants()->update([$this->getParentForeignKeyName() => null]); // to bypass FK constraints
            $deleteQuery = "
                DELETE _descendants, _descendants_closures
                FROM `$closureTable` _closures
                INNER JOIN `$table` _descendants ON _closures.descendant_id = _descendants.id
                INNER JOIN `$closureTable` _descendants_closures
                    ON _descendants_closures.ancestor_id = _descendants.id
                    OR _descendants_closures.descendant_id = _descendants.id
                WHERE _closures.ancestor_id = ?";
            $this->getConnection()->delete($deleteQuery, [$this->getKey()]);
            $this->delete();
        });
    }

    // =========================================================================
    // RELATIONS
    // =========================================================================

    /**
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(static::class, $this->getParentForeignKeyName());
    }

    /**
     * Requires the package baril/octopus.
     *
     * @return \Baril\Octopus\Relations\HasManySiblings
     */
    public function siblings()
    {
        $parentForeignKey = $this->getParentForeignKeyName();
        return new \Baril\Octopus\Relations\HasManySiblings(
            $this->newInstance()->newQuery(),
            $this,
            $this->table . '.' . $parentForeignKey,
            $parentForeignKey
        );
    }

    /**
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(static::class, $this->getParentForeignKeyName());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function ancestors()
    {
        $instance = $this->newRelatedInstance(static::class);
        return (new Closure(
            $instance->newQuery(),
            $this,
            $this->getClosureTable(),
            'descendant_id',
            'ancestor_id',
            $this->getKeyName(),
            $instance->getKeyName(),
            'ancestors'
        ))->as('closure')->withPivot('depth')->excludingSelf();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function descendants()
    {
        $instance = $this->newRelatedInstance(static::class);
        return (new Closure(
            $instance->newQuery(),
            $this,
            $this->getClosureTable(),
            'ancestor_id',
            'descendant_id',
            $this->getKeyName(),
            $instance->getKeyName(),
            'descendants'
        ))->as('closure')->withPivot('depth')->excludingSelf();
    }

    /**
     *
     * @param string $relation
     * @param mixed $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        switch ($relation) {
            case 'descendants':
                $this->setChildrenFromDescendants($value);
                break;
            case 'ancestors':
                $this->setParentsFromAncestors($value);
                break;
        }
        return parent::setRelation($relation, $value);
    }

    /**
     * Automatically sets the "children" (for the current object and each of
     * its loaded descendants) when the "descendants" relationship is loaded.
     *
     * @param \Illuminate\Database\Eloquent\Collection $descendants
     * @return $this
     */
    protected function setChildrenFromDescendants($descendants)
    {
        $descendants = $descendants->keyBy($this->primaryKey);
        $parentKey = $this->getParentForeignKeyName();

        $descendants->each(function ($item, $key) use ($descendants, $parentKey) {
            if ($descendants->has($item->$parentKey)) {
                if (!$descendants[$item->$parentKey]->relationLoaded('children')) {
                    $descendants[$item->$parentKey]->setRelation('children', collect([]));
                }
                $descendants[$item->$parentKey]->children->push($item);
            }
        });

        // Prevents an unneeded query in case we try to access the children of a leaf.
        $descendants->each(function ($item, $key) {
            if (!$item->relationLoaded('children') && $item->closure->_remaining_depth !== 0) {
                $item->setRelation('children', collect([]));
            }
        });

        return $this->setRelation('children', $descendants->values()->filter(function ($item) use ($parentKey) {
            return $item->$parentKey == $this->getKey();
        })->values());
    }

    /**
     * Automatically sets the "parent" (for the current object and each of
     * its loaded ancestors) when the "ancestors" relationship is loaded.
     *
     * @param \Illuminate\Database\Eloquent\Collection $ancestors
     * @return $this
     */
    protected function setParentsFromAncestors($ancestors)
    {
        if (!$ancestors->count()) {
            return;
        }

        $parentKey = $this->getParentForeignKeyName();
        $keyedAncestors = $ancestors->keyBy($this->primaryKey);

        $ancestors->merge([$this])->each(function ($model) use ($keyedAncestors, $parentKey) {
            if (null === $model->$parentKey) {
                $model->setRelation('parent', null);
            } elseif ($keyedAncestors->has($model->$parentKey)) {
                $model->setRelation('parent', $keyedAncestors->get($model->$parentKey));
            }
        });
    }

    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
     *
     * @return bool
     */
    public function isRoot()
    {
        $parentKey = $this->getParentForeignKeyName();
        return $this->$parentKey === null;
    }

    /**
     *
     * @return bool
     */
    public function isLeaf()
    {
        return !$this->children()->exists();
    }

    /**
     *
     * @return bool
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    /**
     *
     * @return bool
     */
    public function isChildOf($item)
    {
        $parentKey = $this->getParentForeignKeyName();
        return $item->getKey() == $this->$parentKey;
    }

    /**
     *
     * @param static $item
     * @return bool
     */
    public function isParentOf($item)
    {
        return $item->isChildOf($this);
    }

    /**
     *
     * @param static $item
     * @return bool
     */
    public function isDescendantOf($item)
    {
        return $this->ancestors()->whereKey($item->getKey())->exists();
    }

    /**
     *
     * @param static $item
     * @return bool
     */
    public function isAncestorOf($item)
    {
        return $item->isDescendantOf($this);
    }

    /**
     *
     * @param static $item
     * @return bool
     */
    public function isSiblingOf($item)
    {
        $parentKey = $this->getParentForeignKeyName();
        return $item->$parentKey == $this->$parentKey;
    }

    /**
     * Returns the closest common ancestor with the provided $item.
     * May return null if the tree has multiple roots and the 2 items have no
     * common ancestor.
     *
     * @param static $item
     * @return static|null
     */
    public function findCommonAncestorWith($item)
    {
        return $this->ancestors()
            ->includingSelf()
            ->whereIsAncestorOf($item->getKey(), null, true)
            ->orderByDepth()
            ->first();
    }

    /**
     * Returns the distance between $this and another $item.
     * May throw an exception if the tree has multiple roots and the 2 items
     * have no common ancestor.
     *
     * @param static $item
     * @return int
     * @throws TreeException
     */
    public function getDistanceTo($item)
    {
        $commonAncestor = $this->findCommonAncestorWith($item);
        if (!$commonAncestor) {
            throw new TreeException('The items have no common ancestor!');
        }
        $depths = $commonAncestor->descendants()->includingSelf()->whereKey([$this->getKey(), $item->getKey()])
                ->toBase()->select($this->getClosureTable() . '.depth')
                ->get()->pluck('depth');
        return $depths->sum();
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

    /**
     * Returns the depth of the subtree of which $this is a root.
     *
     * @return int
     */
    public function getSubtreeDepth()
    {
        return (int) $this->descendants()->orderByDepth('desc')->value('depth');
    }


    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    public function scopeWithAncestors($query, $depth = null, $constraints = null)
    {
        $query->with(['ancestors' => function ($query) use ($depth, $constraints) {
            if ($depth !== null) {
                $query->upToDepth($depth)->orderByDepth();
            }
            if ($constraints !== null) {
                $constraints($query);
            }
        }]);
    }

    public function scopeWithDescendants($query, $depth = null, $constraints = null)
    {
        $query->with(['descendants' => function ($query) use ($depth, $constraints) {
            if ($depth !== null) {
                $query->upToDepth($depth)->orderByDepth();
            }
            if ($constraints !== null) {
                $constraints($query);
            }
        }]);
    }

    public function scopeWithDepth($query, $as = 'depth')
    {
        $query->withCount('ancestors as ' . $as);
    }

    public function scopeWhereIsRoot($query, $bool = true)
    {
        $query->where($this->getParentForeignKeyName(), ($bool ? '=' : '!='), null);
    }

    public function scopeWhereIsLeaf($query, $bool = true)
    {
        if ($bool) {
            $query->has('descendants', '=', 0);
        } else {
            $query->has('descendants');
        }
    }

    public function scopeWhereHasChildren($query, $bool = true)
    {
        $this->scopeWhereIsLeaf($query, !$bool);
    }

    public function scopeWhereIsDescendantOf($query, $ancestor, $maxDepth = null, $includingSelf = false)
    {
        $ancestorId = ($ancestor instanceof Model) ? $ancestor->getKey() : $ancestor;
        $closureTable = $this->getClosureTable();
        $alias = $closureTable . uniqid();
        $query->join(
            $closureTable . ' as ' . $alias,
            function ($join) use ($ancestorId, $maxDepth, $alias, $includingSelf) {
                $join->on($alias . '.descendant_id', '=', $this->getQualifiedKeyName());
                $join->where($alias . '.ancestor_id', '=', $ancestorId);
                if (!$includingSelf) {
                    $join->where($alias . '.depth', '>', 0);
                }
                if ($maxDepth !== null) {
                    $join->where($alias . '.depth', '<=', $maxDepth);
                }
            }
        );
        $query->where($alias . '.ancestor_id', '!=', null);
    }

    public function scopeWhereIsAncestorOf($query, $descendant, $maxDepth = null, $includingSelf = false)
    {
        $descendantId = ($descendant instanceof Model) ? $descendant->getKey() : $descendant;
        $closureTable = $this->getClosureTable();
        $alias = $closureTable . uniqid();
        $query->join(
            $closureTable . ' as ' . $alias,
            function ($join) use ($descendantId, $maxDepth, $alias, $includingSelf) {
                $join->on($alias . '.ancestor_id', '=', $this->getQualifiedKeyName());
                $join->where($alias . '.descendant_id', '=', $descendantId);
                if (!$includingSelf) {
                    $join->where($alias . '.depth', '>', 0);
                }
                if ($maxDepth !== null) {
                    $join->where($alias . '.depth', '<=', $maxDepth);
                }
            }
        );
        $query->where($alias . '.ancestor_id', '!=', null);
    }

    // =========================================================================
    // ADDITIONAL USEFUL METHODS
    // =========================================================================

    /**
     * Shortcut method that returns a collection of the tree roots, with their
     * eager-loaded descendants.
     *
     * @param int $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree($depth = null)
    {
        return static::query()->whereIsRoot()->withDescendants($depth)->get();
    }

    /**
     * Return the depth of the tree (0 if the tree is flat).
     *
     * @return int
     */
    public static function getTreeDepth()
    {
        $instance = new static();
        return $instance->getConnection()->table($instance->getClosureTable())
                ->selectRaw('MAX(depth)')->value('MAX(depth)');
    }

    // =========================================================================
    // INSERTING AND UPDATING CLOSURES
    // =========================================================================

    /**
     * Check if the parent_id points to a descendant of the current object
     * (and trigger an exception if it's the case).
     *
     * @throws TreeException
     * @return void
     */
    protected function checkIfParentIdIsValid()
    {
        $parentKey = $this->getParentForeignKeyName();

        if (is_null($this->$parentKey)) {
            return;
        }
        if (
            $this->$parentKey == $this->getKey()
            || $this->newQuery()->whereKey($this->$parentKey)->whereIsDescendantOf($this->getKey())->exists()
        ) {
            throw new TreeException(
                'Redundancy error! The item\'s parent can\'t be the item itself or one of its descendants.'
            );
        }
    }

    /**
     * Re-calculate the closures when the parent_id has changed on a single item.
     *
     * @param bool $deleteOldClosures Can be set to false if it's a new item
     */
    public function refreshClosures($deleteOldClosures = true)
    {
        $this->getConnection()->transaction(function () use ($deleteOldClosures) {

            $closureTable = $this->getClosureTable();
            $parentKey = $this->getParentForeignKeyName();
            $id = $this->getKey();
            $newParentId = $this->$parentKey;

            // Delete old closures:
            if ($deleteOldClosures) {
                $this->getConnection()->delete("
                    DELETE FROM closures USING $closureTable AS closures
                        INNER JOIN $closureTable AS descendants
                            ON closures.descendant_id = descendants.descendant_id
                        INNER JOIN $closureTable AS ancestors
                            ON closures.ancestor_id = ancestors.ancestor_id
                        WHERE descendants.ancestor_id = ?
                            AND ancestors.descendant_id = ?
                            AND closures.depth > descendants.depth", [$id, $id]);
            }

            // Create self-closure if needed:
            $this->getConnection()->insert("
                INSERT IGNORE INTO $closureTable
                SET ancestor_id = ?, descendant_id = ?, depth = 0", [$id, $id]);

            // Create new closures:
            if ($newParentId) {
                $this->getConnection()->insert("
                    INSERT INTO $closureTable (ancestor_id, descendant_id, depth)
                    SELECT ancestors.ancestor_id, descendants.descendant_id, ancestors.depth + descendants.depth + 1
                        FROM $closureTable AS ancestors
                        CROSS JOIN $closureTable AS descendants
                        WHERE ancestors.descendant_id = ?
                            AND descendants.ancestor_id = ?", [$newParentId, $id]);
            }
        });
    }

    /**
     * Deletes the closures for the item. Assumes that the item has no children.
     */
    protected function deleteClosuresForLeaf()
    {
        $closureTable = $this->getClosureTable();
        $this->getConnection()->table($closureTable)->where('descendant_id', $this->getKey())->delete();
    }
}
