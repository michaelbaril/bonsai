<?php

namespace Baril\Bonsai;

use Baril\Bonsai\Console\FixTreeCommand;
use Baril\Bonsai\Console\GrowTreeCommand;
use Baril\Bonsai\Console\ShowTreeCommand;
use Illuminate\Support\ServiceProvider;

class BonsaiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            FixTreeCommand::class,
            GrowTreeCommand::class,
            ShowTreeCommand::class,
        ]);
    }
}
