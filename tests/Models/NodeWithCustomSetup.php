<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;

class NodeWithCustomSetup extends Model
{
    use BelongsToTree;

    protected $table = 'nodes_with_custom_setup';

    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_key');
    }

    public function ancestors()
    {
        return $this->belongsToManyThroughClosures(static::class, 'node_with_custom_setup_closures')
            ->withoutSelf()
            ->closes('parent');
    }
}
