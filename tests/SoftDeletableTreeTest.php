<?php

namespace Baril\Bonsai\Tests;

use Baril\Bonsai\Tests\Concerns\TestsClosedRelations;
use Baril\Bonsai\Tests\Concerns\TestsCommands;
use Baril\Bonsai\Tests\Concerns\TestsDelete;
use Baril\Bonsai\Tests\Concerns\TestsMethods;
use Baril\Bonsai\Tests\Concerns\TestsMove;
use Baril\Bonsai\Tests\Concerns\TestsReadOnly;
use Baril\Bonsai\Tests\Concerns\TestsRelations;
use Baril\Bonsai\Tests\Concerns\TestsRelationScopes;
use Baril\Bonsai\Tests\Concerns\TestsScopes;
use Baril\Bonsai\Tests\Concerns\TestsTraversal;
use Baril\Bonsai\Tests\Models\SoftDeletableNode;
use Baril\Bonsai\TreeException;

class SoftDeletableTreeTest extends TreeTestCase
{
    use TestsRelations;
    use TestsRelationScopes;
    use TestsScopes;
    use TestsTraversal;
    use TestsMove;
    use TestsDelete;
    use TestsClosedRelations;
    use TestsMethods;
    use TestsReadOnly;
    use TestsCommands;

    protected static $defaultModelClass = SoftDeletableNode::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Add some soft-deleted nodes:
        $this->addToTree([
            'bananes',
        ], $this->getId('fruits'));
        $this->addToTree([
            'bananes plantain',
        ], $this->getId('bananes'));
        $this->addToTree([
            'groseilles',
        ], $this->getId('fruits rouges'));
        $this->addToTree([
            'gariguettes',
        ], $this->getId('fraises'));
        $this->addToTree([
            'petits pois',
            'carottes',
        ], $this->getId('légumes'));
        $this->addToTree([
            'avoine',
            'blé',
        ], $this->getId('céréales'));
        foreach (
            [
            'bananes plantain',
            'bananes',
            'groseilles',
            'gariguettes',
            'petits pois',
            'carottes',
            'avoine',
            'blé',
            ] as $node
        ) {
            $this->getModel($node)->delete();
        }
    }

    public static function closuresProvider()
    {
        $data = static::ancestorsAndDescendantsProvider();
        array_push(
            $data['regular node'][2],
            'groseilles',
            'gariguettes'
        );
        array_push(
            $data['root'][2],
            'petits pois',
            'carottes'
        );

        return $data;
    }

    /**
     * @dataProvider graftSoftDeletedNodeProvider
     */
    public function test_graft_soft_deleted_node($node, $newParent, $expectedDescendants)
    {
        $model = $this->getModel($node);
        $parent = $this->getModel($newParent);
        $parentDescendants = $parent->descendants()->withoutGlobalScopes()->get();

        $model->graftOnto($parent);
        $this->assertModel($parent, $model->parent);
        $this->assertModels(
            array_merge([$node], $expectedDescendants),
            $model->descendants()->withoutGlobalScopes()
        );
        $this->assertModels(
            $parentDescendants->pluck('name')->push($model)->merge($expectedDescendants),
            $parent->descendants()->withoutGlobalScopes()
        );
    }

    public static function graftSoftDeletedNodeProvider()
    {
        return [
            ['bananes', 'légumes', ['bananes plantain']]
        ];
    }

    public static function deleteFailureProvider()
    {
        return [
            ['fraises'],
            ['fraises', 'forceDelete'],
        ];
    }

    public static function deleteSuccessProvider()
    {
        return [
            'leaf' => ['fraises tagada', 'delete', false],
            'node with trashed descendants' => ['céréales', 'delete', false],
        ];
    }

    /**
     * @dataProvider forceDeleteSuccessProvider
     */
    public function test_force_delete_success($node)
    {
        $model = $this->getModel($node);
        $additionalClosuresToDelete = $model->newClosureQuery()
            ->whereIn('descendant_id', $model->descendants()->withTrashed()->pluck('id'))
            ->whereIn('ancestor_id', $model->ancestors()->pluck('id'))
            ->get();

        $this->test_delete_success($node, 'forceDelete', true, $additionalClosuresToDelete);
    }

    public static function forceDeleteSuccessProvider()
    {
        return [
            'leaf' => ['fraises tagada'],
            'node with trashed descendants' => ['céréales'],
            'trashed node' => ['bananes'],
            'trashed leaf' => ['bananes plantain'],
        ];
    }

    public static function deleteNodeProvider()
    {
        return [
            'node with descendants' => ['fruits rouges', 'delete', false],
            'node without descendants' => ['framboises', 'delete', false],
            'root with descendants' => ['fruits', 'delete', false],
            'root without descendants' => ['céréales', 'delete', false],
        ];
    }

    public static function deleteTreeProvider()
    {
        return array_merge(
            static::deleteNodeProvider(),
            [
                'node with descendants (force)' => ['fruits rouges', 'forceDelete', true],
                'node without descendants (force)' => ['framboises', 'forceDelete', true],
                'root with descendants (force)' => ['fruits', 'forceDelete', true],
                'root without descendants (force)' => ['céréales', 'forceDelete', true],
            ]
        );
    }

    /**
     * @dataProvider forceDeleteTreeWithTrashedDescendantsProvider
     */
    public function test_force_delete_tree_with_trashed_descendants($node, $withTrashed)
    {
        $model = $this->getModel($node);
        $descendants = $model->descendants()->get();
        $trashedDescendants = $model->descendants()->onlyTrashed()->get();
        $result = (int) $model->forceDeleteTree($withTrashed);

        $this->assertEquals(
            1 + $descendants->count() + ($withTrashed ? $trashedDescendants->count() : 0),
            $result
        );

        $this->assertFalse($this->newQuery()->withoutGlobalScopes()->whereKey($model->getKey())->exists());
        $this->assertFalse($this->newQuery()->withoutGlobalScopes()->whereKey($descendants->modelKeys())->exists());
        $this->assertEquals(
            !$withTrashed,
            $this->newQuery()->withoutGlobalScopes()->whereKey($trashedDescendants->modelKeys())->exists()
        );
    }

    public static function forceDeleteTreeWithTrashedDescendantsProvider()
    {
        return [
            'with' => ['fruits', true],
            'without' => ['fruits', false],
        ];
    }

    /**
     * @dataProvider restoreProvider
     */
    public function test_restore($node, $method = 'restore', $expectedDescendants = [])
    {
        $model = $this->getModel($node);
        $result = (int) $model->$method();
        $this->assertEquals(count($expectedDescendants) + 1, $result);
        $this->assertModels(
            array_merge([$node], $expectedDescendants),
            $model->descendants()->withSelf()
        );
    }

    public static function restoreProvider()
    {
        return [
            'restore leaf' => ['gariguettes'],
            'restore node' => ['bananes'],
            'restore tree' => ['bananes', 'restoreTree', ['bananes plantain']],
        ];
    }

    /**
     * @dataProvider restoreFailureProvider
     */
    public function test_restore_failure($delete, $restore, $method = 'restore')
    {
        $this->getModel($delete)->deleteTree();
        $this->expectException(TreeException::class);
        $this->getModel($restore)->$method();
    }

    public static function restoreFailureProvider()
    {
        return [
            ['fruits rouges', 'fraises des bois'],
            ['fruits rouges', 'fraises', 'restoreTree'],
        ];
    }
}
