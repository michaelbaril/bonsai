<?php

namespace Baril\Bonsai\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use \Baril\Bonsai\Concerns\BelongsToOrderedTree;

    protected $fillable = ['name'];
}
