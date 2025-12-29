<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;
use Ramsey\Uuid\Uuid;

/**
 * @todo use HasUuids trait in v4
 */
class NodeWithUuid extends Model
{
    public static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->id = (string) Uuid::uuid4();
        });
    }

    use BelongsToTree;

    protected $table = 'nodes_with_uuid';
    protected $keyType = 'string';
    public $incrementing = false;
}
