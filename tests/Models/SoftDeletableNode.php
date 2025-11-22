<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;
use Baril\Bonsai\Concerns\SoftDeletes;

class SoftDeletableNode extends Model
{
    use BelongsToTree;
    use SoftDeletes;
}
