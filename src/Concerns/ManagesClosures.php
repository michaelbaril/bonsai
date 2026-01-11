<?php

namespace Baril\Bonsai\Concerns;

use Baril\Bonsai\TreeException;

trait ManagesClosures
{
    /**
     * Create, update and delete the closures when the model is saved.
     */
    public static function bootManagesClosures()
    {
        static::saving(function ($item) {
            $item->checkIfParentIdIsValid();
        });

        static::created(function ($item) {
            $item->createSelfClosure();
            $item->attachSubtree();
        });

        static::updated(function ($item) {
            // If the parent has changed, update the closures
            // for the node and its descendants:
            if ($item->isDirty($item->getParentForeignKeyName())) {
                $item->detachSubtree();
                $item->attachSubtree();
            }
        });

        static::deleting(function ($item) {
            $item->checkIfDeletable();
        });

        static::deleted(function ($item) {
            // Delete the node's closures:
            // @todo replace with !static::isSoftDeletable()
            if (!property_exists($item, 'forceDeleting')) {
                $item->deleteClosures();    
            } elseif ($item->forceDeleting) {
                $item->deleteClosures('>=');
            }
        });
    }

    /**
     * Check if the node can be deleted.
     *
     * @throws \Baril\Bonsai\TreeException
     * @return void
     */
    protected function checkIfDeletable()
    {
        if ($this->children()->exists()) {
            throw new TreeException('Can\'t delete an item with children!');
        }
    }

    /**
     * Check that the parent is not the model itself or one of
     * its descendants (which triggers an exception).
     *
     * @throws \Baril\Bonsai\TreeException
     * @return void
     */
    protected function checkIfParentIdIsValid()
    {
        if (is_null($parentKey = $this->getParentKey())) {
            return;
        }

        if (!$this->parent()->exists()) {
            throw new TreeException('The item\'s parent doesn\'t exist!');
        }

        if (
            $parentKey == $this->getKey()
            || $this->descendingClosures()->whereDescendant($parentKey)->exists()
        ) {
            throw new TreeException(
                'Redundancy error! The item\'s parent can\'t be the item itself or one of its descendants.'
            );
        }
    }

    /**
     * Insert the self-closure for the model.
     *
     * @return int
     */
    protected function createSelfClosure()
    {
        return $this->newClosureQuery()->insert([
            'ancestor_id' => $this->getKey(),
            'descendant_id' => $this->getKey(),
            'depth' => 0,
        ]);
    }

    /**
     * Create the closures to attach the model and its subtree
     * to the rest of the tree (ie. the model's ancestors).
     * This assumes that the "internal" closures of the subtree
     * already exist.
     *
     * @return int
     */
    protected function attachSubtree()
    {
        if (! $parentKey = $this->getParentKey()) {
            return;
        }

        $connection = $this->getConnection();
        $grammar = $connection->getQueryGrammar();

        // The new closures are all the possible combinations
        // between the model's descending closures (including the self-closure)
        // and the new parent's ascending closures (including the self-closure),
        // with a depth that's the sum of both depths + 1.

        // INSERT INTO $closureTable (descendant_id, ancestor_id, depth)
        // SELECT descendants.descendant_id, ancestors.ancestor_id, descendants.depth + ancestors.depth + 1
        //     FROM $closureTable AS descendants
        //     CROSS JOIN $closureTable AS ancestors
        //     WHERE descendants.ancestor_id = $id
        //         AND ancestors.descendant_id = $newParentId

        $select = $this->newClosureQuery('descendants')
            ->selfCrossJoin('ancestors')
            ->where('descendants.ancestor_id', $this->getKey())
            ->where('ancestors.descendant_id', $parentKey)
            ->select(
                'descendants.descendant_id',
                'ancestors.ancestor_id',
                $connection->raw(sprintf(
                    '%s + %s + 1',
                    $grammar->wrap('descendants.depth'),
                    $grammar->wrap('ancestors.depth')
                ))
            );

        return $this->newClosureQuery()
            ->insertUsing(['descendant_id', 'ancestor_id', 'depth'], $select);
    }

    /**
     * Delete the closures attaching the model and its subtree
     * to the rest of the tree (ie. the model's ancestors),
     * but not the "internal" closures of the subtree.
     *
     * @return int
     */
    protected function detachSubtree()
    {
        return $this->deleteClosures('>');
    }

    /**
     * Delete the ascending closures of the model and optionally its
     * descendants.
     *
     * @param  string|null  $operator  - '>' to detach the subtree (including the node) from the main tree
     *                                 - '>=' to detach the descendants from the node
     *                                 - null to delete all the subtree closures
     * @return int
     */
    protected function deleteClosures($operator = null)
    {
        // DELETE FROM closures USING $closureTable AS closures
        //     INNER JOIN $closureTable AS descendants
        //         ON closures.descendant_id = descendants.descendant_id
        //     WHERE descendants.ancestor_id = $id
        //         AND closures.depth > descendants.depth

        $query = $this->newClosureQuery('closures_to_delete')
            ->selfJoin('descendants', 'descendant_id')
            ->where('descendants.ancestor_id', $this->id)
            // This condition preserves the internal closures:
            ->when($operator, function ($query, $operator) {
                $query->whereColumn('closures_to_delete.depth', $operator, 'descendants.depth');
            });

        return $query->delete();
    }
}
