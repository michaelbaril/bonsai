<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Models\Tag;
use Baril\Bonsai\TreeException;
use Illuminate\Support\Facades\DB;
use LogicException;

class TreeTest extends TestCase
{
    protected $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tags = collect([]);
        $this->tags['A'] = Tag::factory()->create();
        $this->tags['AA'] = Tag::factory()->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['AB'] = Tag::factory()->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['ABA'] = Tag::factory()->create(['parent_id' => $this->tags['AB']->id]);
        $this->tags['B'] = Tag::factory()->create();
    }

    public function test_relations()
    {
        // parent
        $this->assertEquals($this->tags['A']->id, $this->tags['AB']->parent->id);

        // siblings
        /*$expected = [
            $this->tags['AB']->id,
        ];
        $this->assertEquals($expected, $this->tags['AA']->siblings->pluck('id')->toArray());*/

        // children
        $expected = [
            $this->tags['AA']->id,
            $this->tags['AB']->id,
        ];
        $this->assertEquals($expected, $this->tags['A']->children->pluck('id')->toArray());

        // descendants
        $expected[] = $this->tags['ABA']->id;
        $this->assertEquals($expected, $this->tags['A']->descendants()->orderByDepth()->pluck('id')->toArray());

        // descendants including self
        array_unshift($expected, $this->tags['A']->id);
        $this->assertEquals($expected, $this->tags['A']->descendants()->includingSelf()->orderByDepth()->pluck('id')->toArray());

        // ancestors
        $expected = [
            $this->tags['AB']->id,
            $this->tags['A']->id,
        ];
        $this->assertEquals($expected, $this->tags['ABA']->ancestors()->orderByDepth()->pluck('id')->toArray());

        // ancestors including self
        array_unshift($expected, $this->tags['ABA']->id);
        $this->assertEquals($expected, $this->tags['ABA']->ancestors()->includingSelf()->orderByDepth()->pluck('id')->toArray());

        // ascending closures
        $expected = [
            $this->tags['A']->id,
            $this->tags['AB']->id,
        ];
        $this->assertEquals(
            $expected,
            $this->tags['AB']->ascendingClosures->pluck('ancestor_id')->sort()->values()->all()
        );
        $this->assertEquals(
            $expected,
            $this->tags['AB']->ascendingClosures->pluck('pivotRelated.id')->sort()->values()->all()
        );

        // descending closures
        $expected = [
            $this->tags['AB']->id,
            $this->tags['ABA']->id,
        ];
        $this->assertEquals(
            $expected,
            $this->tags['AB']->descendingClosures->pluck('descendant_id')->sort()->values()->all()
        );
        $this->assertEquals(
            $expected,
            $this->tags['AB']->descendingClosures->pluck('pivotRelated.id')->sort()->values()->all()
        );

        // closures with related
        $expected = [
            $this->tags['AB']->id,
            $this->tags['ABA']->id,
        ];
        $this->assertEquals(
            $expected,
            $this->tags['AB']
                ->descendingClosures()
                ->with('related')
                ->get()
                ->pluck('related.id')
                ->sort()
                ->values()
                ->all()
        );
    }

    /**
     * @dataProvider changeParentProvider
     */
    public function test_change_parent($newParent)
    {
        $this->tags['ABAA'] = Tag::factory()->create(['parent_id' => $this->tags['ABA']->id]);
        $this->tags['ABAAA'] = Tag::factory()->create(['parent_id' => $this->tags['ABAA']->id]);

        $newParent = $this->tags[$newParent];
        $expectedAncestors = $newParent->ancestors()->orderByDepth()->pluck('id')->prepend($newParent->id)->prepend($this->tags['ABA']->id);
        $expectedDescendants = $this->tags['ABA']->descendants()->orderByDepth()->pluck('id')->prepend($this->tags['ABA']->id);

        $this->tags['ABA']->parent()->associate($newParent);
        $this->tags['ABA']->save();

        // ancestors
        $this->assertEquals(
            $expectedAncestors->toArray(),
            $this->tags['ABA']->ancestors()->includingSelf()->orderByDepth()->pluck('id')->toArray()
        );

        // descendants
        $this->assertEquals(
            $expectedDescendants->toArray(),
            $this->tags['ABA']->descendants()->includingSelf()->orderByDepth()->pluck('id')->toArray()
        );

        // descendants' ancestors
        $expectedAncestors->prepend($this->tags['ABAA']->id);
        $this->assertEquals(
            $expectedAncestors->toArray(),
            $this->tags['ABAA']->ancestors()->includingSelf()->orderByDepth()->pluck('id')->toArray()
        );
        $expectedAncestors->prepend($this->tags['ABAAA']->id);
        $this->assertEquals(
            $expectedAncestors->toArray(),
            $this->tags['ABAAA']->ancestors()->includingSelf()->orderByDepth()->pluck('id')->toArray()
        );
    }

    public static function changeParentProvider()
    {
        return [
            'AA' => ['AA'],
            'B' => ['B'],
        ];
    }

    /**
     * @dataProvider redundancyProvider
     */
    public function test_redundancy($tag)
    {
        $this->expectException(TreeException::class);
        $this->tags['A']->parent_id = $this->tags[$tag]->id;
        $this->tags['A']->save();
    }

    public static function redundancyProvider()
    {
        return [
            'A' => ['A'],
            'AB' => ['AB'],
            'ABA' => ['ABA'],
        ];
    }

    public function test_methods()
    {
        $this->assertTrue($this->tags['B']->isRoot());
        $this->assertFalse($this->tags['AA']->isRoot());
        $this->assertTrue($this->tags['AA']->isLeaf());
        $this->assertTrue($this->tags['AB']->hasChildren());
        $this->assertTrue($this->tags['ABA']->isChildOf($this->tags['AB']));
        $this->assertFalse($this->tags['ABA']->isParentOf($this->tags['AB']));
        $this->assertTrue($this->tags['ABA']->isDescendantOf($this->tags['A']));
        $this->assertTrue($this->tags['AB']->isAncestorOf($this->tags['ABA']));
        $this->assertTrue($this->tags['AB']->isSiblingOf($this->tags['AA']));
        $this->assertFalse($this->tags['ABA']->isSiblingOf($this->tags['AA']));
        $this->assertFalse($this->tags['ABA']->isChildOf($this->tags['AA']));
    }

    public function test_common_ancestor()
    {
        $this->assertFalse($this->tags['A']->hasCommonAncestorWith($this->tags['B']));
        $this->assertNull($this->tags['A']->findCommonAncestorWith($this->tags['B']));
        $this->assertTrue($this->tags['AA']->hasCommonAncestorWith($this->tags['ABA']));
        $this->assertEquals($this->tags['A']->id, $this->tags['ABA']->findCommonAncestorWith($this->tags['AA'])->id);
        $this->assertEquals($this->tags['A']->id, $this->tags['ABA']->findCommonAncestorWith($this->tags['A'])->id);
        $this->assertEquals($this->tags['A']->id, $this->tags['A']->findCommonAncestorWith($this->tags['AA'])->id);
    }

    public function test_distance_exception()
    {
        $this->expectException(TreeException::class);
        $this->tags['A']->getDistanceTo($this->tags['B']);
    }

    public function test_distance_and_depth()
    {
        $this->assertEquals(0, $this->tags['AB']->getDistanceTo($this->tags['AB']));
        $this->assertEquals(2, $this->tags['ABA']->getDistanceTo($this->tags['A']));
        $this->assertEquals(3, $this->tags['AA']->getDistanceTo($this->tags['ABA']));
        $this->assertEquals(0, $this->tags['A']->getDepth());
        $this->assertEquals(2, $this->tags['ABA']->getDepth());
        $this->assertEquals(2, $this->tags['A']->getSubtreeDepth());
        $this->assertEquals(1, $this->tags['AB']->getSubtreeDepth());
        $this->assertEquals(0, $this->tags['ABA']->getSubtreeDepth());
    }

    public function test_scopes()
    {
        $this->assertEquals(2, Tag::onlyRoots()->count());
        $this->assertEquals(3, Tag::onlyRoots(false)->count());
        $this->assertEquals(3, Tag::onlyLeaves()->count());
        $this->assertEquals(2, Tag::whereHasChildren()->count());

        $this->assertEquals(3, Tag::whereIsDescendantOf($this->tags['A'])->count());
        $this->assertEquals(3, Tag::whereIsDescendantOf($this->tags['A']->id)->count());
        $this->assertEquals(2, Tag::whereIsDescendantOf($this->tags['A']->id, 1)->count());
        $this->assertEquals(3, Tag::whereIsDescendantOf($this->tags['A']->id, 1, true)->count());

        $this->assertEquals(2, Tag::whereIsAncestorOf($this->tags['ABA'])->count());
        $this->assertEquals(2, Tag::whereIsAncestorOf($this->tags['ABA']->id)->count());
        $this->assertEquals(1, Tag::whereIsAncestorOf($this->tags['ABA']->id, 1)->count());
        $this->assertEquals(2, Tag::whereIsAncestorOf($this->tags['ABA']->id, 1, true)->count());
    }

    public function test_with_descendants()
    {
        $tags = Tag::with('descendants')->whereKey($this->tags['A']->id)->get();
        DB::enableQueryLog();
        $count = count(DB::getQueryLog());
        $this->assertCount(3, $tags[0]->descendants);
        $this->assertCount(2, $tags[0]->children);
        $this->assertCount(1, $tags[0]->children[1]->children);
        $this->assertCount(0, $tags[0]->children[1]->children[0]->children);
        $this->assertEquals($count, count(DB::getQueryLog())); // checking that no new query has been necessary
    }

    public function test_with_scoped_descendants()
    {
        $tags = Tag::withDescendants(null, function ($query) {
            $query->whereKey($this->tags['AB']->id);
        })->whereKey($this->tags['A']->id)->get();
        $this->assertCount(1, $tags[0]->descendants);
    }

    public function test_with_descendants_and_limited_depth()
    {
        $tags = Tag::withDescendants(1)->whereKey($this->tags['A']->id)->get();
        $this->assertCount(2, $tags[0]->descendants);
        $this->assertTrue($tags[0]->relationLoaded('children'));
        $this->assertFalse($tags[0]->children[1]->relationLoaded('children'));
    }

    public function test_with_ancestors()
    {
        $tags = Tag::with('ancestors')->whereKey($this->tags['ABA']->id)->get();
        DB::enableQueryLog();
        $count = count(DB::getQueryLog());
        $this->assertCount(2, $tags[0]->ancestors);
        $this->assertEquals($this->tags['AB']->id, $tags[0]->parent->id);
        $this->assertEquals($this->tags['A']->id, $tags[0]->parent->parent->id);
        $this->assertNull($tags[0]->parent->parent->parent);
        $this->assertEquals($count, count(DB::getQueryLog())); // checking that no new query has been necessary
    }

    public function test_with_reversed_ancestors()
    {
        $tags = Tag::with([
            'ancestors' => function ($query) {
                $query->orderByDepth('desc');
            },
        ])->whereKey($this->tags['ABA']->id)->get();
        $this->assertEquals($this->tags['AB']->id, $tags[0]->parent->id);
        $this->assertEquals($this->tags['A']->id, $tags[0]->parent->parent->id);
    }

    public function test_with_scoped_ancestors()
    {
        $tags = Tag::with([
            'ancestors' => function ($query) {
                $query->whereKey($this->tags['A']->id);
            },
        ])->whereKey($this->tags['ABA']->id)->get();
        $this->assertNull($tags[0]->parent);
        $this->assertTrue($tags[0]->relationLoaded('parent'));
    }

    public function test_with_ancestors_and_limited_depth()
    {
        $tags = Tag::withAncestors(1)->whereKey($this->tags['ABA']->id)->get();
        $this->assertCount(1, $tags[0]->ancestors);
        $this->assertTrue($tags[0]->relationLoaded('parent'));
        $this->assertFalse($tags[0]->parent->relationLoaded('parent'));
    }

    public function test_with_depth()
    {
        $tags = Tag::withDepth()->get()->pluck('depth', 'id');
        $this->assertEquals(2, $tags[$this->tags['ABA']->id]);
        $tags = Tag::withDepth('alias')->get()->pluck('alias', 'id');
        $this->assertEquals(1, $tags[$this->tags['AA']->id]);
    }

    public function test_order_by_depth()
    {
        $this->tags['AA']->parent()->associate($this->tags['ABA'])->save(); // AA's parent is now ABA

        $ancestorsByDepth = $this->tags['AA']->ancestors()->orderByDepth()->pluck('id')->toArray();
        $expected = [
            $this->tags['ABA']->id,
            $this->tags['AB']->id,
            $this->tags['A']->id,
        ];
        $this->assertEquals($expected, $ancestorsByDepth);

        $tag = Tag::with(['descendants' => function ($query) {
            $query->orderByDepth('desc');
        }])->whereKey($this->tags['A']->id)->first();
        $descendantsByDepthDesc = $tag->descendants->pluck('id')->toArray();
        $expected = [
            $this->tags['AA']->id,
            $this->tags['ABA']->id,
            $this->tags['AB']->id,
        ];
        $this->assertEquals($expected, $descendantsByDepthDesc);
    }

    public function test_with_count()
    {
        $tags = Tag::withCount('descendants')->whereKey($this->tags['A']->id)->get();
        $this->assertEquals(3, $tags[0]->descendants_count);
        $tags = Tag::withCount('ancestors')->whereKey($this->tags['ABA']->id)->get();
        $this->assertEquals(2, $tags[0]->ancestors_count);
    }

    public function test_position()
    {
        $this->tags['AB']->moveToPosition(1);
        $this->assertEquals(1, Tag::find($this->tags['AB']->id)->position);
        $this->assertEquals(2, Tag::find($this->tags['AA']->id)->position);

        $expected = [
            $this->tags['AB']->id,
            $this->tags['AA']->id,
        ];
        $this->assertEquals($expected, $this->tags['A']->children()->ordered()->pluck('id')->toArray());
    }

    public function test_delete_failure()
    {
        $this->expectException(TreeException::class);
        $this->tags['AB']->delete();
    }

    public function test_delete_success()
    {
        $id = $this->tags['ABA']->id;

        $totalInitialClosuresCount = DB::table('tag_tree')->count();
        $modelInitialClosuresCount = DB::table('tag_tree')
            ->where('descendant_id', $id)
            ->orWhere('ancestor_id', $id)
            ->count();

        $this->tags['ABA']->delete();

        $this->assertNull(Tag::find($id));

        $this->assertEquals(
            0,
            DB::table('tag_tree')
                ->where('descendant_id', $id)
                ->orWhere('ancestor_id', $id)
                ->count()
        );
        $this->assertEquals(
            $totalInitialClosuresCount - $modelInitialClosuresCount,
            DB::table('tag_tree')->count()
        );
    }

    public function test_delete_tree()
    {
        $this->tags['ABAA'] = Tag::factory()->create(['parent_id' => $this->tags['ABA']->id]);

        $initialClosuresCount = DB::table('tag_tree')->count();
        $this->tags['AB']->deleteTree();
        $remainingClosuresCount = DB::table('tag_tree')->count();
        $this->assertEquals(9, $initialClosuresCount - $remainingClosuresCount);
        $this->assertNull(Tag::find($this->tags['AB']->id));
        $this->assertNull(Tag::find($this->tags['ABA']->id));
        $this->assertNull(Tag::find($this->tags['ABAA']->id));
    }

    public function test_delete_node()
    {
        $this->tags['AB']->deleteNode();
        $this->assertNull(Tag::find($this->tags['AB']->id));
        $this->assertEquals($this->tags['A']->id, Tag::find($this->tags['ABA']->id)->parent_id);
        $this->tags['A']->deleteNode();
        $this->assertNull(Tag::find($this->tags['A']->id));
        $this->assertNull(Tag::find($this->tags['ABA']->id)->parent_id);
    }

    /**
     * @dataProvider closureRelationIsReadonlyProvider
     */
    public function test_closure_relation_is_readonly($method, ...$args)
    {
        $this->expectException(LogicException::class);
        $this->tags['A']->descendants()->$method(...$args);
    }

    public static function closureRelationIsReadonlyProvider()
    {
        $testData = [
            ['save', new Tag()],
            ['saveMany', [new Tag()]],
            ['create', ['name' => 'toto']],
            ['createMany', [['name' => 'kiki']]],
            ['toggle', [123]],
            ['syncWithoutDetaching', [123]],
            ['sync', [123]],
            ['attach', 123],
            ['detach'],
        ];
        return array_combine(
            array_map(function ($data) {
                return $data[0];
            }, $testData),
            $testData
        );
    }

    public function test_tree_depth()
    {
        $this->assertEquals(2, Tag::getTreeDepth());
    }
}
