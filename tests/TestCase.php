<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\BonsaiServiceProvider;
use Baril\Orderly\OrderlyServiceProvider;
use Dotenv\Dotenv;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
        $app['config']->set('database.default', 'Bonsai');
        $app['config']->set('database.connections.Bonsai', [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'database' => $_ENV['DB_DATABASE'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            OrderlyServiceProvider::class,
            BonsaiServiceProvider::class,
        ];
    }

    protected function setUp() : void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->withFactories(__DIR__ . '/database/factories');
        \DB::enableQueryLog();
    }

    protected function dumpQueryLog()
    {
        dump(\DB::getQueryLog());
    }
}
