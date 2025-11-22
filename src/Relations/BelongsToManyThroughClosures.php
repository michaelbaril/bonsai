<?php

namespace Baril\Bonsai\Relations;

use Baril\Bonsai\Relations\Concerns\ExcludesSelf;
use Baril\Bonsai\Relations\Concerns\InteractsWithClosureTable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Baril\Bonsai\Closure = \Baril\Bonsai\Closure
 * @template TAccessor of string = 'closure'
 *
 * @extends \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, TDeclaringModel, TPivotModel, TAccessor>
 */
class BelongsToManyThroughClosures extends BelongsToMany
{
    use ExcludesSelf {
        ExcludesSelf::match as _match;
    }
    use InteractsWithClosureTable;

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $this->_match($models, $results, $relation);

        $this->matchOtherRelations($models, $results);
        $this->matchOtherRelations($results->all(), $results);

        return $models;
    }
}
