<?php

namespace Baril\Bonsai\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Ordered
{
    use Orderable;

    public static function bootOrdered()
    {
        static::addGlobalScope('ordered', function (Builder $builder) {
            $builder->ordered();
        });
    }
}
