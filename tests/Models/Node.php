<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;

class Node extends Model
{
    use BelongsToTree;
}
