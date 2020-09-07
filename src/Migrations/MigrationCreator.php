<?php

namespace Baril\Bonsai\Migrations;

use Illuminate\Database\Migrations\MigrationCreator as BaseCreator;
use Illuminate\Filesystem\Filesystem;

class MigrationCreator extends BaseCreator
{
    public function __construct(Filesystem $files)
    {
        parent::__construct($files, null);
    }
}
