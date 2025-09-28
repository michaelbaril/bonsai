<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;

class UnconstrainedNode extends Model
{
    use BelongsToTree;
}
