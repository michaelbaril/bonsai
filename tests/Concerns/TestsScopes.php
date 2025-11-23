<?php

namespace Baril\Bonsai\Tests\Concerns;

trait TestsScopes
{
    /**
     * @dataProvider scopesProvider
     */
    public function test_scopes($scope, $parameters, $expected)
    {
        $this->assertModels($expected, $this->newQuery()->$scope(...$parameters));
    }

    public static function scopesProvider()
    {
        return [
            'onlyRoots' => [
                'whereIsRoot',
                [],
                ['fruits', 'légumes', 'céréales'],
            ],
            'withoutRoots' => [
                'whereIsRoot',
                [false],
                [
                    'fruits rouges',
                    'framboises',
                    'fraises',
                    'fraises des bois',
                    'fraises tagada',
                    'myrtilles',
                    'kiwis',
                    'haricots verts',
                    'brocolis',
                    'brocolis pour mettre dans le minestrone',
                    'tomates',
                ],
            ],
            'onlyLeaves' => [
                'whereIsLeaf',
                [],
                [
                    'framboises',
                    'fraises des bois',
                    'fraises tagada',
                    'myrtilles',
                    'kiwis',
                    'haricots verts',
                    'brocolis pour mettre dans le minestrone',
                    'tomates',
                    'céréales',
                ],
            ],
            'withoutLeaves' => [
                'whereIsLeaf',
                [false],
                ['fruits', 'fruits rouges', 'fraises', 'légumes', 'brocolis'],
            ],
            'hasChildren' => [
                'whereHasChildren',
                [],
                ['fruits', 'fruits rouges', 'fraises', 'légumes', 'brocolis'],
            ],
        ];
    }

    /**
     * @dataProvider ancestorsAndDescendantsScopesProvider
     */
    public function test_ancestors_and_descendants_scopes($node, $expectedAncestors, $expectedDescendants, $maxDepth = null)
    {
        $model = $this->getModel($node);

        // ancestorsOf with model as argument
        $this->assertEquals(
            $expectedAncestors,
            $this->newQuery()->whereIsAncestorOf($model, $maxDepth)->count()
        );

        // ancestorsOf withSelf with id as argument
        $this->assertEquals(
            $expectedAncestors + 1,
            $this->newQuery()->whereIsAncestorOf($model->getKey(), $maxDepth, true)->count()
        );

        // descendantsOf with model as argument
        $this->assertEquals(
            $expectedDescendants,
            $this->newQuery()->whereIsDescendantOf($model, $maxDepth)->count()
        );

        // descendantsOf withSelf with id as argument
        $this->assertEquals(
            $expectedDescendants + 1,
            $this->newQuery()->whereIsDescendantOf($model->getKey(), $maxDepth, true)->count()
        );
    }

    public static function ancestorsAndDescendantsScopesProvider()
    {
        return [
            'some node' => ['fruits rouges', 1, 5],
            'some node with max depth 0' => ['fruits rouges', 0, 0, 0],
            'some node with max depth 1' => ['fruits rouges', 1, 3, 1],
            'some node with max depth 10' => ['fruits rouges', 1, 5, 10],
            'some other node' => ['fraises', 2, 2],
            'root' => ['fruits', 0, 7],
            'leaf' => ['brocolis pour mettre dans le minestrone', 2, 0],
        ];
    }

    /**
     * @dataProvider withDepthprovider
     */
    public function test_with_depth($node, $expectedDepth)
    {
        $depth = $this->newQuery()->where('name', $node)->withDepth('alias')->first()->alias;
        $this->assertEquals($expectedDepth, $depth);
    }

    public static function withDepthProvider()
    {
        return [
            'root' => ['fruits', 0],
            'level 1' => ['fruits rouges', 1],
            'level 2' => ['fraises', 2],
            'leaf' => ['fraises tagada', 3],
        ];
    }

    /**
     * @dataProvider withHeightprovider
     */
    public function test_with_height($node, $expectedHeight)
    {
        $depth = $this->newQuery()->where('name', $node)->withHeight('alias')->first()->alias;
        $this->assertEquals($expectedHeight, $depth);
    }

    public static function withHeightprovider()
    {
        return [
            'root' => ['fruits', 3],
            'level 1' => ['fruits rouges', 2],
            'level 2' => ['fraises', 1],
            'leaf' => ['fraises tagada', 0],
            'empty node' => ['céréales', 0],
        ];
    }
}
