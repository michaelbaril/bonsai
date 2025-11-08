<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsCommands;
use Baril\Bonsai\Tests\Concerns\TestsRelations;
use Baril\Bonsai\Tests\Models\NodeWithCustomSetup;

class TreeWithCustomSetupTest extends TreeTestCase
{
    use TestsCommands;
    use TestsRelations;

    protected static $defaultModelClass = NodeWithCustomSetup::class;
}
