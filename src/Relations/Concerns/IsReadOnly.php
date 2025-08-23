<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait IsReadOnly
{
    /**
     * @throws \LogicException
     */
    protected function readOnly()
    {
        throw new LogicException("The $this->relationName relation is read-only!");
    }

    public function save(Model $model, array $pivotAttributes = [], $touch = true)
    {
        $this->readOnly();
    }

    public function saveMany($models, array $pivotAttributes = [])
    {
        $this->readOnly();
    }

    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $this->readOnly();
    }

    public function createMany(iterable $records, array $joinings = [])
    {
        $this->readOnly();
    }

    public function toggle($ids, $touch = true)
    {
        $this->readOnly();
    }

    public function syncWithoutDetaching($ids)
    {
        $this->readOnly();
    }

    public function sync($ids, $detaching = true)
    {
        $this->readOnly();
    }

    public function attach($id, array $attributes = [], $touch = true)
    {
        $this->readOnly();
    }

    public function detach($ids = null, $touch = true)
    {
        $this->readOnly();
    }
}
