<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Database\MigrateProcessor;

class CommandsTest extends TestCase
{
    protected $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tags = collect([]);
        $this->tags['A'] = factory(Tag::class)->create();
        $this->tags['AA'] = factory(Tag::class)->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['AB'] = factory(Tag::class)->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['ABA'] = factory(Tag::class)->create(['parent_id' => $this->tags['AB']->id]);
        $this->tags['B'] = factory(Tag::class)->create();
    }

    public function test_recreate_closures()
    {
        foreach ($this->tags as $tag) {
            $tag->descendants_count = $tag->descendants()->count();
        }

        $connection = $this->tags['A']->getConnection();
        $closures = $this->tags['A']->getClosureTable();
        $count = $connection->table($closures)->count();

        // Removing the closures:
        $connection->table($closures)->delete();
        // Inserting some bogus closure that should be deleted after the command has run:
        $connection->table($closures)->insert([
            ['ancestor_id' => $this->tags['A']->id, 'descendant_id' => $this->tags['B']->id, 'depth' => 1],
        ]);

        $this->artisan('bonsai:fix', ['model' => Tag::class])->assertExitCode(0)->execute();

        $this->assertEquals($count, $connection->table($closures)->count());
        foreach ($this->tags as $tag) {
            $this->assertEquals($tag->descendants_count, $tag->descendants()->count());
        }
        $descendants = $this->tags['A']->descendants()->pluck('id');
        $this->assertContains($this->tags['AB']->id, $descendants);
        $this->assertNotContains($this->tags['B']->id, $descendants);
    }

    public function test_grow_tree()
    {
        // Create temporary migration folder:
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';
        File::deleteDirectory($path);
        File::makeDirectory($path);

        // Create migration:
        $this->artisan('bonsai:grow', [
            'model' => Tag::class,
            '--name' => 'test_grow_tree',
            '--path' => $path,
            '--realpath' => true,
            '--migrate' => false,
        ]);

        // Assert migration has been created:
        $this->assertCount(1, File::glob("$path/*_test_grow_tree.php"));

        // Drop closure table:
        $schema = $this->tags['A']->getConnection()->getSchemaBuilder();
        $closures = $this->tags['A']->getClosureTable();
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

    public function test_show_tree()
    {
        $command = $this->artisan('bonsai:show', ['model' => Tag::class, '--label' => 'name', '--depth' => 1]);

        if (method_exists($command, 'expectsOutputToContain')) { // Only for Laravel >= 9
            $ab = "#{$this->tags['AB']->id}: {$this->tags['AB']->name}";
            $aba = "#{$this->tags['ABA']->id}: {$this->tags['ABA']->name}";
            $command
                ->expectsOutputToContain($ab)
                ->doesntExpectOutputToContain($aba);
        }

        $command->assertExitCode(0)->execute();
    }
}
