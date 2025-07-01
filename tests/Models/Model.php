<?php

namespace Baril\Bonsai\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    use HasFactory;

    protected static function newFactory()
    {
        $class = str_replace('\\Models\\', '\\Factories\\', static::class) . 'Factory';
        return $class::new();
    }
}
