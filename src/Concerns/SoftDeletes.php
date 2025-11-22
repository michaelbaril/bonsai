<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes as EloquentSoftDeletes;

trait SoftDeletes
{
    use EloquentSoftDeletes;

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
