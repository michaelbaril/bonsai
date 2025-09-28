<?php

namespace Baril\Bonsai\Tests\Concerns;

use Baril\Bonsai\TreeException;
use Illuminate\Support\Arr;

trait TestsTraversal
{
    /**
     * @dataProvider commonAncestorProvider
     */
    public function test_common_ancestor($firstNode, $secondNode, $commonAncestor)
    {
        $firstModel = $this->getModel($firstNode);
        $secondModel = $this->getModel($secondNode);

        // Has common ancestor with model as argument:
        $this->assertEquals(
            (bool) $commonAncestor,
            $firstModel->hasCommonAncestorWith($secondModel)
        );

        // Find common ancestor with id as argument:
        $this->assertEquals(
            (bool) $commonAncestor,
            $firstModel->hasCommonAncestorWith($secondModel->getKey())
        );

        // Has common ancestor with model as argument:
        $this->assertModel(
            $commonAncestor,
            $firstModel->findCommonAncestorWith($secondModel)
        );

        // Find common ancestor with id as argument:
        $this->assertModel(
            $commonAncestor,
            $firstModel->findCommonAncestorWith($secondModel->getKey())
        );
    }

    public static function commonAncestorProvider()
    {
        return [
            'no common ancestor' => ['fruits', 'légumes', null],
            'common ancestor exists' => ['fraises', 'framboises', 'fruits rouges'],
            'first node is common ancestor' => ['légumes', 'brocolis pour mettre dans le minestrone', 'légumes'],
            'second node is common ancestor' => ['brocolis pour mettre dans le minestrone', 'légumes', 'légumes'],
        ];
    }

    /**
     * @dataProvider distanceProvider
     */
    public function test_distance($firstNode, $secondNode, $distance)
    {
        $firstModel = $this->getModel($firstNode);
        $secondModel = $this->getModel($secondNode);

        if ($distance === null) {
            $this->expectException(TreeException::class);
            $firstModel->getDistanceTo($secondModel);
        } else {
            $this->assertEquals(
                $distance,
                $firstModel->getDistanceTo($secondModel)
            );
        }
    }

    public static function distanceProvider()
    {
        return [
            'no common ancestor' => ['framboises', 'haricots verts', null],
            'common ancestor' => ['fraises tagada', 'kiwis', 4],
            'same node' => ['fraises', 'fraises', 0],
            'ancestor to descendant' => ['fruits', 'fraises tagada', 3],
            'descendant to ancestor' => ['fraises tagada', 'fruits', 3],
        ];
    }

    /**
     * @dataProvider depthProvider
     */
    public function test_depth($node, $depth, $subtreeDepth)
    {
        $model = $this->getModel($node);

        $this->assertEquals(
            $depth,
            $model->getDepth()
        );

        $this->assertEquals(
            $subtreeDepth,
            $model->getSubtreeDepth()
        );
    }

    public static function depthProvider()
    {
        return [
            'regular node' => ['fraises', 2, 1],
            'root' => ['fruits', 0, 3],
            'leaf' => ['brocolis pour mettre dans le minestrone', 2, 0],
        ];
    }

    public function test_tree_depth()
    {
        $expectedDepth = collect(Arr::dot(static::$tree))->map(function ($value, $key) {
            return substr_count($key, '.');
        })->sortDesc()->first();

        $class = static::$defaultModelClass;

        $this->assertEquals($expectedDepth, $class::getTreeDepth());
    }

    public function test_get_tree()
    {
        $class = static::$defaultModelClass;
        $depth = $class::getTreeDepth();

        $this->assertTree(static::$tree, $class::getTree(), $class);

        for ($i = 0; $i <= $depth; $i++) {
            $tree = $class::getTree($i);
            $this->assertTree(static::$tree, $tree, $class);

            // Check that tree hasn't been loaded beyond specified depth:
            foreach ($tree as $root) {
                if ($root->descendants->isNotEmpty()) {
                    $loadedDepth = $root->descendants->max('closure.depth');
                    $this->assertNotNull($loadedDepth);
                    $this->assertLessThanOrEqual($i, $loadedDepth);
                }
            }
        }
    }
}
