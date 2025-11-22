<?php

namespace Baril\Bonsai\Tests\Concerns;

trait TestsRelations
{
    /**
     * @dataProvider parentAndChildrenProvider
     */
    public function test_parent_and_children($node, $parent, $children)
    {
        $model = $this->getModel($node);

        // Parent:
        $this->assertModel(
            $parent,
            $model->parent()->getResults()
        );

        // Children:
        $this->assertModels(
            $children,
            $model->children()
        );
    }

    public static function parentAndChildrenProvider()
    {
        return [
            'regular node' => [
                'fraises',
                'fruits rouges',
                [
                    'fraises des bois',
                    'fraises tagada',
                ],
            ],
            'root' => ['fruits', null, ['fruits rouges', 'kiwis']],
            'leaf' => ['myrtilles', 'fruits rouges', []],
        ];
    }

    /**
     * @dataProvider siblingsProvider
     */
    public function test_siblings($node, $siblings, $isOrphan = false)
    {
        // Siblings:
        $this->assertModels(
            $isOrphan ? [] : $siblings,
            $this->getModel($node)->siblings()
        );

        // Eager load:
        $this->assertModels(
            $isOrphan ? [] : $siblings,
            $this->newQuery()
                ->with('siblings')
                ->where('name', $node)
                ->first()
                ->siblings
        );

        // Count:
        $this->assertEquals(
            $isOrphan ? 0 : count($siblings),
            $this->newQuery()
                ->withCount('siblings')
                ->where('name', $node)
                ->first()
                ->siblings_count
        );

        // Exists:
        $exists = $this->newQuery()->has('siblings')->get()->where('name', $node)->first();
        if (!$isOrphan && count($siblings)) {
            $this->assertNotNull($exists);
        } else {
            $this->assertNull($exists);
        }
    }

    public static function siblingsProvider()
    {
        return [
            'regular node' => [
                'framboises',
                ['fraises', 'myrtilles'],
            ],
            'only child' => [
                'brocolis pour mettre dans le minestrone',
                [],
            ],
            'orphan' => [
                'céréales',
                ['fruits', 'légumes'],
                true,
            ],
        ];
    }

    /**
     * @dataProvider ancestorsAndDescendantsProvider
     */
    public function test_ancestors_and_descendants($node, $ancestors, $descendants)
    {
        // Ancestors:
        $this->assertModels(
            $ancestors,
            $this->getModel($node)->ancestors()
        );

        // Descendants:
        $this->assertModels(
            $descendants,
            $this->getModel($node)->descendants()
        );

        // Eager loads:
        $model = $this->newQuery()
            ->with('ancestors', 'descendants')
            ->where('name', $node)
            ->first();
        $this->assertModels(
            $ancestors,
            $model->ancestors
        );
        $this->assertModels(
            $descendants,
            $model->descendants
        );

        // Count:
        $model = $this->newQuery()
            ->withCount(['ancestors', 'descendants'])
            ->where('name', $node)
            ->first();
        $this->assertEquals(count($ancestors), $model->ancestors_count);
        $this->assertEquals(count($descendants), $model->descendants_count);

        // Exists:
        $model = $this->newQuery()
            ->withExists(['ancestors', 'descendants'])
            ->where('name', $node)
            ->first();
        $this->assertEquals(!empty($ancestors), $model->ancestors_exists);
        $this->assertEquals(!empty($descendants), $model->descendants_exists);
    }

    public static function ancestorsAndDescendantsProvider()
    {
        return [
            'regular node' => [
                'fruits rouges',
                ['fruits'],
                [
                    'framboises',
                    'fraises',
                    'fraises des bois',
                    'fraises tagada',
                    'myrtilles',
                ],
            ],
            'root' => [
                'légumes',
                [],
                [
                    'haricots verts',
                    'brocolis',
                    'brocolis pour mettre dans le minestrone',
                    'tomates',
                ],
            ],
            'leaf' => [
                'framboises',
                ['fruits rouges', 'fruits'],
                [],
            ],
        ];
    }

    /**
     * @dataProvider closuresProvider
     */
    public function test_closure_relations($node, $ancestors, $descendants)
    {
        $ascending = array_merge([$node], $ancestors);
        $descending = array_merge([$node], $descendants);

        // Ascending:
        $results = $this->getModel($node)->ascendingClosures()->get();
        $this->assertModels(
            $ascending,
            $results->pluck('ancestor_id')
        );

        // Check that related model has been hydrated with the id:
        $this->assertModels(
            $ascending,
            $results->pluck('pivotRelated.id')
        );

        // Descending:
        $results = $this->getModel($node)->descendingClosures()->get();
        $this->assertModels(
            $descending,
            $results->pluck('descendant_id')
        );

        // Check that related model has been hydrated with the id:
        $this->assertModels(
            $descending,
            $results->pluck('pivotRelated.id')
        );

        // Ascending with related:
        $this->assertModels(
            $ascending,
            $this->getModel($node)
                ->ascendingClosures()
                ->with([
                    'related' => function ($query) {
                        $query->withoutGlobalScopes();
                    },
                ])
                ->get()
                ->pluck('related')
        );

        // Descending with related:
        $this->assertModels(
            $descending,
            $this->getModel($node)
                ->descendingClosures()
                ->with([
                    'related' => function ($query) {
                        $query->withoutGlobalScopes();
                    },
                ])
                ->get()
                ->pluck('related')
        );
    }

    public static function closuresProvider()
    {
        return static::ancestorsAndDescendantsProvider();
    }
}
