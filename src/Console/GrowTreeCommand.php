<?php

namespace Baril\Bonsai\Console;

use Baril\Bonsai\Migrations\MigrationCreator;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class GrowTreeCommand extends MigrateMakeCommand
{
    protected $signature = 'bonsai:grow {model : The model class.}
        {--name= : The name of the migration.}
        {--path= : The location where the migration file should be created.}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths.}
        {--migrate : Migrate the database and fill the table after the migration file has been created.}';
    protected $description = 'Create the migration file for a closure table, and optionally run the migration';

    public function __construct(MigrationCreator $creator, Composer $composer)
    {
        parent::__construct($creator, $composer);
    }

    public function handle()
    {
        $model = $this->input->getArgument('model');
        if (
            !class_exists($model)
            || !is_subclass_of($model, Model::class)
            || !method_exists($model, 'getClosureTable')
        ) {
            $this->error('{model} must be a valid model class and use the BelongsToTree trait!');
            return;
        }

        $this->writeClosureMigration($model);
        $this->composer->dumpAutoloads();

        if ($this->input->hasOption('migrate') && $this->option('migrate')) {
            $this->call('migrate');
            $this->call('bonsai:fix', ['model' => $model]);
        }
    }

    protected function writeClosureMigration($model)
    {
        // Retrieve all informations about the tree:
        $instance = new $model();
        $closureTable = $instance->getClosureTable();

        // Get the name for the migration file:
        $name = $this->input->getOption('name') ?: 'create_' . $closureTable . '_table';
        $name = Str::snake(trim($name));
        $migrationClassName = Str::studly($name);

        // Generate the content of the migration file:
        $contents = $this->getMigrationContents([
            '%migration%' => $migrationClassName,
            '%closureTable%' => $closureTable,
            '%mainTable%' => $instance->getTable(),
            '%model%' => get_class($instance),
        ]);

        // Generate the file:
        $file = $this->creator->create(
            $name,
            $this->getMigrationPath(),
            $closureTable,
            true
        );
        file_put_contents($file, $contents);

        // Output information:
        $file = pathinfo($file, PATHINFO_FILENAME);
        $this->line("<info>Created Migration:</info> {$file}");
    }

    protected function getMigrationContents($replacements)
    {
        $contents = file_get_contents(__DIR__ . '/../Migrations/stubs/grow_tree.stub');
        $contents = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        );
        $contents = preg_replace('/\;[\s]*\/\/.*\n/U', ";\n", $contents);
        return $contents;
    }
}
