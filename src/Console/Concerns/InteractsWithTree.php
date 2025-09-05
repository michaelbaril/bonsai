<?php

namespace Baril\Bonsai\Console\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait InteractsWithTree
{
    protected function checkModel($model)
    {
        if (
            !class_exists($model)
            || !is_subclass_of($model, Model::class)
            || !method_exists($model, 'getClosureTable')
        ) {
            throw new InvalidArgumentException(
                sprintf('%s is not a valid model class or does not use the BelongsToTree trait!', $model)
            );
        }
    }
}
