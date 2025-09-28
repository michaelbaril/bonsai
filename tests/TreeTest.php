<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsClosedRelations;
use Baril\Bonsai\Tests\Concerns\TestsCommands;
use Baril\Bonsai\Tests\Concerns\TestsDelete;
use Baril\Bonsai\Tests\Concerns\TestsMethods;
use Baril\Bonsai\Tests\Concerns\TestsMove;
use Baril\Bonsai\Tests\Concerns\TestsReadOnly;
use Baril\Bonsai\Tests\Concerns\TestsRelations;
use Baril\Bonsai\Tests\Concerns\TestsRelationScopes;
use Baril\Bonsai\Tests\Concerns\TestsScopes;
use Baril\Bonsai\Tests\Concerns\TestsTraversal;
use Baril\Bonsai\Tests\Models\Node;

class TreeTest extends TreeTestCase
{
    use TestsRelations;
    use TestsRelationScopes;
    use TestsScopes;
    use TestsTraversal;
    use TestsMove;
    use TestsDelete;
    use TestsClosedRelations;
    use TestsMethods;
    use TestsReadOnly;
    use TestsCommands;

    protected static $defaultModelClass = Node::class;
}
