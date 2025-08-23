<?php

namespace Baril\Bonsai\Relations;

use Baril\Bonsai\Relations\Concerns\ExcludesSelf;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasMany<TDeclaringModel, TDeclaringModel>
 */
class HasManySiblings extends HasMany
{
    use ExcludesSelf;
}
