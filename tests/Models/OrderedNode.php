<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToOrderedTree;

class OrderedNode extends Model
{
    use BelongsToOrderedTree;
}
