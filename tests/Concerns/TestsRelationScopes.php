<?php

namespace Baril\Bonsai\Tests\Concerns;

trait TestsRelationScopes
{
    /**
     * @dataProvider ancestorsAndDescendantsProvider
     */
    public function test_ancestors_and_descendants_with_or_without_self($node, $ancestors, $descendants)
    {
        $ancestorsWithSelf = array_merge([$node], $ancestors);
        $descendantsWithSelf = array_merge([$node], $descendants);

        // Ancestors with self:
        $this->assertModels(
            $ancestorsWithSelf,
            $this->getModel($node)->ancestors()->includingSelf()
        );

        // Descendants with self:
        $this->assertModels(
            $descendantsWithSelf,
            $this->getModel($node)->descendants()->includingSelf()
        );

        // Ancestors without self:
        $this->assertModels(
            $ancestors,
            $this->getModel($node)->ancestors()->includingSelf()->excludingSelf()
        );

        // Descendants without self:
        $this->assertModels(
            $descendants,
            $this->getModel($node)->descendants()->includingSelf()->excludingSelf()
        );

        // Eager loads with self:
        $model = $this->newQuery()
            ->with([
                'ancestors' => function ($query) {
                    return $query->includingSelf();
                },
                'descendants' => function ($query) {
                    return $query->includingSelf();
                },
            ])
            ->where('name', $node)
            ->first();
        $this->assertModels(
            $ancestorsWithSelf,
            $model->ancestors
        );
        $this->assertModels(
            $descendantsWithSelf,
            $model->descendants
        );

        // Count:
        $model = $this->newQuery()
            ->withCount([
                'ancestors' => function ($query) {
                    $query->withSelf();
                },
                'descendants' => function ($query) {
                    $query->withSelf();
                },
            ])
            ->where('name', $node)
            ->first();
        $this->assertEquals(count($ancestorsWithSelf), $model->ancestors_count);
        $this->assertEquals(count($descendantsWithSelf), $model->descendants_count);

        // Exists:
        $model = $this->newQuery()
            ->withExists([
                'ancestors' => function ($query) {
                    $query->withSelf();
                },
                'descendants' => function ($query) {
                    $query->withSelf();
                },
            ])
            ->where('name', $node)
            ->first();
        $this->assertEquals(true, $model->ancestors_exists);
        $this->assertEquals(true, $model->descendants_exists);
    }

    /**
     * @dataProvider siblingsProvider
     */
    public function test_siblings_with_or_without_self_and_orphans($node, $siblings, $isOrphan = false)
    {
        $siblingsWithSelf = array_merge($siblings, [$node]);

        // Siblings with self:
        $this->assertModels(
            $isOrphan ? [] : $siblingsWithSelf,
            $this->getModel($node)->siblings()->withSelf()
        );

        // Siblings without self:
        $this->assertModels(
            $isOrphan ? [] : $siblings,
            $this->getModel($node)->siblings()->withSelf()->withoutSelf()
        );

        // Siblings with orphans:
        $this->assertModels(
            $siblings,
            $this->getModel($node)->siblings()->withOrphans()
        );

        // Siblings with orphans and self:
        $this->assertModels(
            $siblingsWithSelf,
            $this->getModel($node)->siblings()->withOrphans()->withSelf()
        );

        // Eager load with self:
        $this->assertModels(
            $isOrphan ? [] : $siblingsWithSelf,
            $this->newQuery()
                ->with([
                    'siblings' => function ($query) {
                        $query->withSelf();
                    }
                ])
                ->where('name', $node)
                ->first()
                ->siblings
        );

        // Eager load with orphans:
        $this->assertModels(
            $siblings,
            $this->newQuery()
                ->with([
                    'siblings' => function ($siblings) {
                        $siblings->withOrphans();
                    },
                ])
                ->where('name', $node)
                ->first()
                ->siblings
        );

        // Eager load with orphans and self:
        $this->assertModels(
            $siblingsWithSelf,
            $this->newQuery()
                ->with([
                    'siblings' => function ($siblings) {
                        $siblings->withOrphans()->withSelf();
                    },
                ])
                ->where('name', $node)
                ->first()
                ->siblings
        );

        // Count:
        // $this->assertEquals(
        //     $isOrphan ? 0 : count($siblings),
        //     $this->newQuery()
        //         ->withCount([
        //             'siblings' => function ($query) { $query->withOrphans(); }
        //         ])
        //         ->where('name', $node)
        //         ->first()
        //         ->siblings_count
        // );
        $this->assertEquals(
            $isOrphan ? 0 : count($siblings) + 1,
            $this->newQuery()
                ->withCount([
                    'siblings' => function ($query) {
                        $query->withSelf();
                    }
                ])
                ->where('name', $node)
                ->first()
                ->siblings_count
        );
        // $this->assertEquals(
        //     count($siblings) + 1,
        //     $this->newQuery()
        //         ->withCount([
        //             'siblings' => function ($query) { $query->withOrphans()->withSelf(); }
        //         ])
        //         ->where('name', $node)
        //         ->first()
        //         ->siblings_count
        // );

        // Exists:
        // $exists = $this->newQuery()->has([
        //     'siblings' => function ($query) { $query->withOrphans(); }
        // ])->get()->where('name', $node)->first();
        // if (count($siblings)) {
        //     $this->assertNotNull($exists);
        // } else {
        //     $this->assertNull($exists);
        // }
    }

    /**
     * @dataProvider upToDepthProvider
     */
    public function test_up_to_depth($node, $relation, $related)
    {
        $expected = [];
        $closedRelation = $this->getModel($node)->$relation()->getClosedRelation();

        foreach ($related as $level => $levelRelated) {
            $previousLevels = $expected;
            $expected = array_merge($expected, $levelRelated);

            $results = $this->getModel($node)->$relation()->upToDepth($level + 1)->get();

            $this->assertModels(
                $expected,
                $results
            );

            // Eager load:
            $model = $this->newQuery()->with([
                $relation => function ($query) use ($level) {
                    $query->upToDepth($level + 1);
                }
            ])->whereName($node)->first();
            $eagerResults = $model->$relation;

            $this->assertModels(
                $expected,
                $eagerResults
            );

            // Check that the closed relation has been loaded up to the expected depth:
            $this->assertTrue($model->relationLoaded($closedRelation));
            foreach ($previousLevels as $relatedNode) {
                $this->assertTrue($eagerResults->where('name', $relatedNode)->first()->relationLoaded($closedRelation));
            }
            foreach ($levelRelated as $relatedNode) {
                $this->assertFalse($eagerResults->where('name', $relatedNode)->first()->relationLoaded($closedRelation));
            }

            // Relation count:
            $model = $this->newQuery()->withCount([
                $relation => function ($query) use ($level) {
                    $query->upToDepth($level + 1);
                }
            ])->whereName($node)->first();
            $this->assertEquals(
                count($expected),
                $model->getAttribute("{$relation}_count")
            );
        }
    }

    public static function upToDepthProvider()
    {
        return [
            [
                'fruits',
                'descendants',
                [
                    ['fruits rouges', 'kiwis'],
                    ['framboises', 'fraises', 'myrtilles'],
                    ['fraises des bois', 'fraises tagada'],
                ],

            ],
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
     * @dataProvider orderByDepthProvider
     */
    public function test_order_by_depth($parent, $relation, $related)
    {
        $model = $this->getModel($parent);

        // Asc:
        $this->assertModelsOrdered(
            $related,
            $model->$relation()->orderByDepth()->orderBy($model->getKeyName())
        );

        // Desc:
        $this->assertModelsOrdered(
            array_reverse($related),
            $model->$relation()->orderByDepth('desc')->orderBy($model->getKeyName(), 'desc')
        );

        // Eager load asc:
        $this->assertModelsOrdered(
            $related,
            $this->newQuery()->whereName($parent)->with([
                $relation => function ($query) {
                    $query->orderByDepth()->orderBy($query->getModel()->getKeyName());
                }
            ])->first()->descendants
        );

        // Eager load desc:
        $this->assertModelsOrdered(
            array_reverse($related),
            $this->newQuery()->whereName($parent)->with([
                $relation => function ($query) {
                    $query->orderByDepth('desc')->orderBy($query->getModel()->getKeyName(), 'desc');
                }
            ])->first()->descendants
        );
    }

    public static function orderByDepthProvider()
    {
        return [
            [
                'légumes',
                'descendants',
                ['haricots verts', 'brocolis', 'tomates', 'brocolis pour mettre dans le minestrone']
            ],
        ];
    }

    /**
     * @dataProvider withCountProvider
     */
    public function test_with_count($node, $expectedAncestorsCount, $expectedDescendantsCount)
    {
        $counts = $this->newQuery()->where('name', $node)->withCount('ancestors', 'descendants')->first();

        $this->assertEquals($expectedAncestorsCount, $counts->ancestors_count);
        $this->assertEquals($expectedDescendantsCount, $counts->descendants_count);
    }

    public static function withCountProvider()
    {
        return [
            'regular node' => ['fruits rouges', 1, 5],
            'root' => ['fruits', 0, 7],
            'leaf' => ['fraises tagada', 3, 0],
        ];
    }
}
