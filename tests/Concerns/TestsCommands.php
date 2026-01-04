<?php

namespace Baril\Bonsai\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Orchestra\Testbench\Database\MigrateProcessor;

trait TestsCommands
{
    /**
     * @dataProvider fixTreeProvider
     */
    public function test_fix_tree($node, $expectations, $unrelatedNode)
    {
        $model = $this->getModel($node);
        $unrelatedModel = $this->getModel($unrelatedNode);

        // Delete all closures:
        $model->newClosureQuery()->delete();

        // Insert bogus closures:
        $model->newClosureQuery()->insert(
            [
                [
                    'ancestor_id' => $model->getKey(),
                    'descendant_id' => $unrelatedModel->getKey(),
                    'depth' => 1,
                ],
                [
                    'ancestor_id' => $unrelatedModel->getKey(),
                    'descendant_id' => $model->getKey(),
                    'depth' => 1,
                ],
            ],
        );

        foreach ($expectations as $relation => $related) {
            $this->assertModelsDontContain(
                $related,
                $model->$relation()
            );
        }
        $this->assertModelsContain(
            $unrelatedModel,
            $model->ancestors()
        );
        $this->assertModelsContain(
            $unrelatedModel,
            $model->descendants()
        );

        $this->artisan('bonsai:fix', ['model' => static::$defaultModelClass])->assertExitCode(0)->execute();

        foreach ($expectations as $relation => $related) {
            $this->assertModelsContain(
                $related,
                $model->$relation()
            );
        }
        $this->assertModelsDontContain(
            $unrelatedModel,
            $model->ancestors()
        );
        $this->assertModelsDontContain(
            $unrelatedModel,
            $model->descendants()
        );
    }

    public static function fixTreeProvider()
    {
        return [
            [
                'fruits rouges',
                [
                    'ancestors' => 'fruits',
                    'descendants' => 'fraises tagada',
                ],
                'céréales',
            ],
        ];
    }

    public function test_grow_tree()
    {
        $model = static::$defaultModelClass;
        $instance = new $model();

        // Create temporary migration folder:
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';
        File::deleteDirectory($path);
        File::makeDirectory($path);

        // Create migration:
        $migrationName = 'test_grow_tree_' . Str::snake(class_basename($model));
        $className = Str::studly($migrationName);
        $this->artisan('bonsai:grow', [
            'model' => $model,
            '--name' => $migrationName,
            '--path' => $path,
            '--realpath' => true,
            '--migrate' => false,
        ]);

        // Assert migration has been created:
        $generatedMigrations = File::glob("$path/*_test_grow_tree_*.php");
        $this->assertCount(1, $generatedMigrations);

        // Check migration contents:
        $originalMigrations = File::glob(__DIR__ . '/../database/migrations/' . class_basename($model) . '/*.php');
        $referenceMigration = File::get($originalMigrations[1]);
        $actualMigration = File::get($generatedMigrations[0]);
        $this->assertEquals(
            preg_replace('/class\s+\w+\s+extends/', "class $className extends", $referenceMigration),
            $actualMigration,
        );

        // Drop closure table:
        $schema = $instance->getConnection()->getSchemaBuilder();
        $closures = $instance->getClosureTable();
        $schema->drop($closures);

        // Make sure table has been dropped before running the migration:
        $this->assertFalse(DB::getSchemaBuilder()->hasTable($closures));

        // Run the migration:
        $migrator = new MigrateProcessor($this, [
            '--path' => $path,
            '--realpath' => true,
        ]);
        $migrator->up();

        // Assert closure table is back:
        $this->assertTrue(DB::getSchemaBuilder()->hasTable($closures));

        // Clean stuff:
        File::deleteDirectory($path);
    }

    /**
     * @dataProvider showTreeProvider
     */
    public function test_show_tree($depth, $shouldContain, $shouldNotContain)
    {
        $command = $this->artisan('bonsai:show', [
            'model' => static::$defaultModelClass,
            '--label' => 'name',
            '--depth' => $depth
        ]);

        if (method_exists($command, 'expectsOutputToContain')) { // Only for Laravel >= 9
            if ($shouldContain) {
                $model = $this->getModel($shouldContain)->fresh();
                $command->expectsOutputToContain("#{$model->getKey()}: {$model->name}");
            }
            if ($shouldNotContain) {
                $model = $this->getModel($shouldNotContain);
                $command->doesntExpectOutputToContain("#{$model->getKey()}: {$model->name}");
            }
        }

        $command->assertExitCode(0)->execute();
    }

    public static function showTreeProvider()
    {
        return [
            [null, 'fraises des bois', null],
            [0, 'céréales', 'fruits rouges'],
            [1, 'kiwis', 'fraises tagada'],
            [2, 'brocolis pour mettre dans le minestrone', 'fraises des bois'],
            [3, 'fraises tagada', null],
        ];
    }
}
