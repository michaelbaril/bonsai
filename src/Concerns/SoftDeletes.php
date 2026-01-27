<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes as EloquentSoftDeletes;

trait SoftDeletes
{
    use EloquentSoftDeletes {
        bootSoftDeletes as _bootSoftDeletes;
    }

    public static function bootSoftDeletes()
    {
        static::_bootSoftDeletes();

        // When we're force deleting, we need to delete the closures before
        // the node is deleted, because the ON CASCADE would delete the node's
        // closure and we wouldn't be able to detach its ancestors any more.
        // @todo replace with forceDeleting in v4 and remove if
        static::deleting(function ($item) {
            if ($item->forceDeleting) {
                $item->deleteClosures('>=');
            }
        });
    }

    /**
     * Force a hard delete on a soft deleted model and its descendants.
     *
     * @param  bool  $withTrashed
     * @return int
     */
    public function forceDeleteTree($withTrashed = true)
    {
        return $this
            ->descendants()
            ->withSelf()
            ->when($withTrashed, function ($query) {
                $query->withTrashed();
            })
            ->orderByDepth('desc')
            ->select($this->getKeyName())
            ->cursor()
            ->map
            ->forceDelete()
            ->sum();
    }

    /**
     * Restore the model and its soft-deleted descendants.
     *
     * @return int
     */
    public function restoreTree()
    {
        return $this
            ->descendants()
            ->withSelf()
            ->withTrashed()
            ->orderByDepth('asc')
            ->select($this->getKeyName())
            ->cursor()
            ->map
            ->restore()
            ->sum();
    }
}
