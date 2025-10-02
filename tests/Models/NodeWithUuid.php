<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;

/**
 * @todo use HasUuids trait in v4
 */
class NodeWithUuid extends Model
{
    public static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->id = uniqid('uniqid_');
        });
    }

    use BelongsToTree;

    protected $table = 'nodes_with_uuid';
    protected $keyType = 'string';
    public $incrementing = false;
}
