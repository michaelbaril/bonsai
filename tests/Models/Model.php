<?php

namespace Baril\Bonsai\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    protected $guarded = [];
}
