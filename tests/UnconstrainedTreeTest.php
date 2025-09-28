<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsDelete;
use Baril\Bonsai\Tests\Models\UnconstrainedNode;

class UnconstrainedTreeTest extends TreeTestCase
{
    use TestsDelete;

    protected static $defaultModelClass = UnconstrainedNode::class;
}
