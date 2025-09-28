<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsDelete;
use Baril\Bonsai\Tests\Concerns\TestsMove;
use Baril\Bonsai\Tests\Concerns\TestsRelations;
use Baril\Bonsai\Tests\Models\SoftDeletableNode;

class SoftDeletableTreeTest extends TreeTestCase
{
    use TestsRelations;
    use TestsMove;
    use TestsDelete;

    protected static $defaultModelClass = SoftDeletableNode::class;
}
