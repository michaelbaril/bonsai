<?php

namespace Baril\Bonsai\Tests\Concerns;

trait TestsRelationScopes
{
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
