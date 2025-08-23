<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
trait InteractsWithClosureTable
{
    use IsReadOnly;

    /**
     * The name of the relation that is "closed" by this relation
     * (eg. "parent" for "ancestors").
     *
     * @var string
     */
    protected $closes;

    /**
     * @var int|null
     */
    protected $depth = null;

    /**
     * @param  string  $relation
     * @return $this
     */
    public function closes($relation)
    {
        $this->closes = $relation;

        return $this;
    }

    /**
     * @return string
     */
    public function getClosedRelation()
    {
        return $this->closes;
    }

    /**
     * @deprecated
     *
     * @return $this
     */
    public function excludingSelf()
    {
        return $this->withoutSelf();
    }

    /**
     * @return $this
     */
    public function withoutSelf()
    {
        // Using whereRaw() (instead of just calling wherePivot()
        // makes it easier to remove the clause when withSelf()
        // is called.

        $column = $this->qualifyPivotColumn('depth');
        $this->pivotWheres[] = [$column, '>', 0];
        return $this->whereRaw("$column > 0");
    }

    /**
     * @deprecated
     *
     * @return $this
     */
    public function includingSelf()
    {
        return $this->withSelf();
    }

    /**
     * @return $this
     */
    public function withSelf()
    {
        $column = $this->qualifyPivotColumn('depth');

        // Remove clause from $this->pivotWheres:
        foreach ($this->pivotWheres as $k => $where) {
            if ($where === [$column, '>', 0]) {
                unset($this->pivotWheres[$k]);
            }
        }
        $this->pivotWheres = array_values($this->pivotWheres);

        // Remove clause from actual query:
        $query = $this->getBaseQuery();
        $sql = "$column > 0";
        foreach ($query->wheres as $k => $where) {
            if ($where === ['type' => 'raw', 'sql' => $sql, 'boolean' => 'and']) {
                unset($query->wheres[$k]);
            }
        }
        $query->wheres = array_values($query->wheres);

        return $this;
    }

    /**
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::wherePivot()
     *
     * @return $this
     */
    public function upToDepth($depth)
    {
        // We'll need the depth again when we migrate the pivot attributes:
        $this->depth = $depth;
        return $this->wherePivot('depth', '<=', $depth);
    }

    /**
     * @param  string  $direction
     * @return $this
     */
    public function orderByDepth($direction = 'asc')
    {
        return $this->orderBy($this->table . '.depth', $direction);
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @see \Illuminate\Database\Eloquent\Relations\BelongsToMany::migratePivotAttributes()
     * @see \Baril\Bonsai\Concerns\BelongsToTree::setClosedRelation()
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array
     */
    protected function migratePivotAttributes(Model $model)
    {
        $values = parent::migratePivotAttributes($model);

        if ($this->depth !== null) {
            // This will be used when we set the "closed" relation:
            $values['_remaining_depth'] = $this->depth - $values['depth'];
        }

        return $values;
    }
}
