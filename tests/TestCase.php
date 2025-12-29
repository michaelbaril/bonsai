<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\BonsaiServiceProvider;
use Baril\Orderly\OrderlyServiceProvider;
use Dotenv\Dotenv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ramsey\Uuid\Uuid;

abstract class TestCase extends OrchestraTestCase
{
    protected $models;

    protected function setUp(): void
    {
        parent::setUp();
        DB::getSchemaBuilder()->dropAllTables();
        $this->app->bind(MigrationCreator::class, function ($app) {
            return new MigrationCreator(
                $app->make(Filesystem::class),
                ''
            );
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->loadEnv(['.env.test', '.env']);
        $this->setupDatabase($app, env('DB_ENGINE', 'sqlite'));
    }

    protected function loadEnv($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->loadEnv($f);
            }
            return;
        }
        if (file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . $file)) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__), $file);
            $dotenv->load();
        }
    }

    protected function setupDatabase($app, $engine = 'mysql')
    {
        $method = 'setup' . ucfirst($engine);
        method_exists($this, $method) ? $this->$method($app) : $this->setupOtherSgbd($app, $engine);
        $app['config']->set('database.default', $engine);
    }

    protected function setupSqlite($app)
    {
        $database = env('SQLITE_DATABASE', database_path('database.sqlite'));
        if (file_exists($database)) {
            unlink($database);
        }
        touch($database);

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ]);
    }

    protected function setupMariadb($app)
    {
        $engine = class_exists(\Illuminate\Database\MariaDbConnection::class)
            ? 'mariadb'
            : 'mysql';
        $this->setupOtherSgbd($app, $engine);
        $app['config']->set('database.connections.mariadb', $app['config']["database.connections.$engine"]);
    }

    protected function setupSqlsrv($app)
    {
        $this->setupOtherSgbd($app, 'sqlsrv');
        $app['config']->set('database.connections.sqlsrv.trust_server_certificate', true);
    }

    protected function setupOtherSgbd($app, $engine)
    {
        $envPrefix = strtoupper($engine);
        $app['config']->set("database.connections.$engine", [
            'driver' => $engine,
            'host' => env('DB_HOST'),
            'port' => env("{$envPrefix}_PORT"),
            'database' => env("{$envPrefix}_DATABASE", env('DB_DATABASE')),
            'username' => env("{$envPrefix}_USERNAME", env('DB_USERNAME')),
            'password' => env("{$envPrefix}_PASSWORD", env('DB_PASSWORD')),
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

    protected function newQuery()
    {
        $class = static::$defaultModelClass;
        return $class::query();
    }

    protected function getModel($name)
    {
        return $this->models->get($name);
    }

    protected function getFresh($name)
    {
        return $this->models->get($name)->fresh();
    }

    protected function getId($name)
    {
        $model = $this->getModel($name);

        return $model ? $model->getKey() : null;
    }

    protected function assertModel($expected, $actual, $class = null)
    {
        $this->assertModels(
            Arr::wrap($expected),
            Arr::wrap($actual),
            $class,
            true
        );
    }

    protected function assertModels($expected, $actual, $class = null, $checkOrder = false)
    {
        $this->assertEquals(
            $this->parseModels($expected, !$checkOrder, $class),
            $this->parseModels($actual, !$checkOrder, $class)
        );
    }

    protected function assertModelsOrdered($expected, $actual, $class = null)
    {
        $this->assertModels($expected, $actual, $class, true);
    }

    protected function assertModelsContain($expected, $actual, $class = null)
    {
        foreach ($this->parseModels($expected, true, $class) as $model) {
            $this->assertContains($model, $this->parseModels($actual, true, $class));
        }
    }

    protected function assertModelsDontContain($expected, $actual, $class = null)
    {
        foreach ($this->parseModels($expected, true, $class) as $model) {
            $this->assertNotContains($model, $this->parseModels($actual, true, $class));
        }
    }

    protected function parseModels($models, $orderById = false, $class = null)
    {
        if ($models instanceof Model) {
            $models = [$models];
        }
        if ($models instanceof Builder || $models instanceof Relation) {
            $class = $class ?? get_class($models->getModel());
            $models = $models->pluck($models->getModel()->getKeyName());
        } else {
            $models = collect($models);
        }

        $class = $class
            ?? $models->map(function ($model) {
                return $model instanceof Model ? get_class($model) : null;
            })->filter()->first()
            ?? static::$defaultModelClass;

        return collect($models)
            ->map(function ($model) use ($class) {
                if (is_scalar($model)) {
                    if (is_numeric($model) || (is_string($model) && Uuid::isValid($model))) {
                        $id = $model;
                        $model = $this->models->filter(function ($model) use ($class, $id) {
                            return get_class($model) == $class && $model->getKey() == $id;
                        })->first();
                    } elseif (is_string($model)) {
                        $model = $this->getModel($model);
                    }
                }
                return [
                    'class' => class_basename($model),
                    'id' => $model->getKey(),
                    'name' => $model->name,
                ];
            })
            ->values()
            ->when($orderById, function ($collection) {
                return $collection->sortBy(['class', 'id'])->values();
            })
            ->map(function ($model) {
                return "{$model['class']} #{$model['id']}: {$model['name']}";
            })
            ->all();
    }
}
