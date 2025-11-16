<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Models\OrderedNode;

class OrderedTreeTest extends TreeTestCase
{
    protected static $defaultModelClass = OrderedNode::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Just to make sure the models are not in the order they were inserted
        // let's order them alphabetically:
        $this->getModel('céréales')->moveBefore($this->getModel('fruits'));
        $this->getModel('fraises')->moveBefore($this->getModel('framboises'));
        $this->getModel('brocolis')->moveBefore($this->getModel('haricots verts'));
    }

    protected function sortTree($tree)
    {
        return collect($tree)->map(function ($value, $key) {
            return is_array($value) ? $this->sortTree($value) : $value;
        })->sortBy(function ($value, $key) {
            return is_array($value) ? $key : $value;
        })->all();
    }

    /**
     * @dataProvider childrenAreOrderedProvider
     */
    public function test_children_are_ordered($node, $children)
    {
        $this->assertModelsOrdered(
            $children,
            $this->getModel($node)->children()
        );
    }

    /**
     * @dataProvider childrenAreOrderedProvider
     */
    public function test_children_are_ordered_when_eager_loaded($node, $children)
    {
        // Eager-loaded:
        $models = $this->newQuery()->with('children')->get();
        $this->assertModelsOrdered(
            $children,
            $models->where('name', $node)->first()->children
        );
    }

    /**
     * @dataProvider childrenAreOrderedProvider
     */
    public function test_children_are_ordered_when_auto_loaded($node, $children)
    {
        $models = $this->newQuery()->with('descendants')->get();
        $this->assertModelsOrdered(
            $children,
            $models->where('name', $node)->first()->children
        );
    }

    public static function childrenAreOrderedProvider()
    {
        return [
            ['fruits rouges', ['fraises', 'framboises', 'myrtilles']],
            ['légumes', ['brocolis', 'haricots verts', 'tomates']],
        ];
    }

    public function test_tree_is_ordered()
    {
        $model = static::$defaultModelClass;

        $this->assertTree(
            $this->sortTree(static::$tree),
            $model::getTree(),
            null,
            true
        );
    }
}
