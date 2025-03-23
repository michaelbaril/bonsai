<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Models\Tag;

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

    // public function test_grow_tree()
    // {
    //     $schema = $this->tags['A']->getConnection()->getSchemaBuilder();
    //     $closures = $this->tags['A']->getClosureTable();
    //     $schema->drop($closures);
    //     $this->artisan('bonsai:grow', [
    //         'model' => Tag::class,
    //         '--name' => 'test_grow_tree',
    //         '--migrate' => true,
    //     ]);
    //     $this->assertDatabaseHas($closures, [
    //         'ancestor_id' => $this->tags['A']->id,
    //         'descendant_id' => $this->tags['ABA']->id,
    //         'depth' => 2,
    //     ]);
    //     foreach (glob($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*_test_grow_tree.php') as $file) {
    //         unlink($file);
    //     }
    // }

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
