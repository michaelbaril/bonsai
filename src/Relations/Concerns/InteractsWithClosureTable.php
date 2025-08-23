<?php

namespace Baril\Bonsai\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
trait InteractsWithClosureTable
{
    use ExcludesSelf;
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
     * @deprecated
     *
     * @return $this
     */
    public function includingSelf()
    {
        return $this->withSelf();
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
