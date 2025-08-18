<?php

namespace Baril\Bonsai\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class BelongsToManyThroughClosures extends BelongsToMany
{
    protected $depth = null;

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        parent::addConstraints();

        $this->as('closure')->withPivot('depth');
    }

    public function orderByDepth($direction = 'asc')
    {
        return $this->orderBy($this->table . '.depth', $direction);
    }

    public function upToDepth($depth)
    {
        $this->depth = $depth;
        return $this->wherePivot('depth', '<=', $depth);
    }

    public function excludingSelf()
    {
        $this->pivotWheres[] = ['depth', '>', 0];
        return $this->whereRaw("$this->table.depth > 0"); // whereRaw makes it easier to remove the clause if needed
    }

    public function includingSelf()
    {
        foreach ($this->pivotWheres as $k => $where) {
            if ($where === ['depth', '>', 0]) {
                unset($this->pivotWheres[$k]);
            }
        }
        $this->pivotWheres = array_values($this->pivotWheres);

        $query = $this->getBaseQuery();
        $sql = "$this->table.depth > 0";
        foreach ($query->wheres as $k => $where) {
            if ($where === ['type' => 'raw', 'sql' => $sql, 'boolean' => 'and']) {
                unset($query->wheres[$k]);
            }
        }
        $query->wheres = array_values($query->wheres);
        return $this;
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array
     */
    protected function migratePivotAttributes(Model $model)
    {
        $values = parent::migratePivotAttributes($model);
        if (array_key_exists('depth', $values) && $this->depth !== null) {
            $values['_remaining_depth'] = $this->depth - $values['depth'];
        }

        return $values;
    }

    // Since the Closures relation is read-only, all the methods below will
    // throw an exception.

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
