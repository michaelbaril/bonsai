<?php

namespace Baril\Bonsai\Relations;

use Baril\Bonsai\Relations\Concerns\ExcludesSelf;
use Baril\Bonsai\Relations\Concerns\InteractsWithClosureTable;
use Baril\Bonsai\Relations\Concerns\IsReadOnly;
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
    use ExcludesSelf;
    use InteractsWithClosureTable;
    use IsReadOnly;
}
