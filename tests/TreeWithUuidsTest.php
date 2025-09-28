<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsCommands;
use Baril\Bonsai\Tests\Concerns\TestsRelations;
use Baril\Bonsai\Tests\Models\NodeWithUuid;

class TreeWithUuidsTest extends TreeTestCase
{
    use TestsRelations;
    use TestsCommands;

    protected static $defaultModelClass = NodeWithUuid::class;
}
