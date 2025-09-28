<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletableNode extends Model
{
    use BelongsToTree;
    use SoftDeletes;
}
