<?php

namespace Baril\Bonsai\Relations;

use Baril\Bonsai\Closure;
use Baril\Bonsai\Relations\Concerns\InteractsWithClosureTable;
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
    use InteractsWithClosureTable;

    /**
     * @var array
     */
    protected $pivotColumns = ['depth'];

    /**
     * @var string
     */
    protected $using = Closure::class;

    /**
     * @var string
     */
    protected $accessor = 'closure';
}
