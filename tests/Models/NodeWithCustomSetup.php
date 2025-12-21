<?php

namespace Baril\Bonsai\Tests\Models;

use Baril\Bonsai\Concerns\BelongsToTree;

class NodeWithCustomSetup extends Model
{
    use BelongsToTree;

    protected $table = 'nodes_with_custom_setup';
    protected $closureTable = 'node_with_custom_setup_closures';
    protected $parentForeignKey = 'parent_key';
}
