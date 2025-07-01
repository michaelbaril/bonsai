<?php

namespace Baril\Bonsai\Tests\Models;

class Tag extends Model
{
    use \Baril\Bonsai\Concerns\BelongsToOrderedTree;

    protected $fillable = ['name'];
}
