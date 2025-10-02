<?php

namespace Baril\Bonsai\Tests\Concerns;

use Baril\Bonsai\TreeException;

trait TestsDelete
{
    /**
     * @dataProvider deleteFailureProvider
     */
    public function test_delete_failure($node)
    {
        $this->expectException(TreeException::class);
        $this->getModel($node)->delete();
    }

    public static function deleteFailureProvider()
    {
        return [
            ['fraises'],
        ];
    }

    /**
     * @dataProvider deleteSuccessProvider
     */
    public function test_delete_success($node)
    {
        $model = $this->getModel($node);
        $closuresCount = $model->newClosureQuery()->count();
        $modelClosuresCount = $model->ascendingClosures()->count() + $model->descendingClosures()->count() - 1;

        $model->delete();

        $this->assertFalse($this->newQuery()->whereKey($model->getKey())->exists());
        $this->assertFalse($model->newClosureQuery()->where('ancestor_id', $model->getKey())->exists());
        $this->assertFalse($model->newClosureQuery()->where('descendant_id', $model->getKey())->exists());
        $this->assertEquals(
            $closuresCount - $modelClosuresCount,
            $model->newClosureQuery()->count()
        );
    }

    public static function deleteSuccessProvider()
    {
        return [
            ['fraises tagada'],
        ];
    }

    /**
     * @dataProvider deleteProvider
     */
    public function test_delete_node($node)
    {
        $model = $this->getModel($node);
        $parent = $model->parent;
        $children = $model->children;

        // Load descendants on children:
        $children->each(function ($child) {
            $child->descendants;
        });

        $model->deleteNode();

        $this->assertFalse($this->newQuery()->whereKey($model->getKey())->exists());

        $children->each(function ($child) use ($parent) {
            $newParent = $child->fresh()->parent;

            // Check that child has been attached to parent:
            $this->assertEquals(
                $parent ? $parent->getKey() : null,
                $newParent ? $newParent->getKey() : null,
            );

            // Check that child has not lost its descendants:
            $this->assertModels(
                $child->descendants,
                $child->descendants()
            );
        });
    }

    /**
     * @dataProvider deleteProvider
     */
    public function test_delete_tree($node)
    {
        $model = $this->getModel($node);
        $parent = $model->parent;
        $parentClosuresBefore = $parent ? $parent->descendingClosures()->count() : null;
        $descendants = $model->descendants;

        $model->deleteTree();

        // Check that all nodes have been deleted:
        $descendants->merge([$model])->each(function ($model) {
            $this->assertFalse($this->newQuery()->whereKey($model->getKey())->exists());
        });

        // Check that closures have been deleted:
        if ($parent) {
            $this->assertEquals(
                $parentClosuresBefore - $descendants->count() - 1,
                $parent->descendingClosures()->count()
            );
        }
    }

    public static function deleteProvider()
    {
        return [
            'node with descendants' => ['fruits rouges'],
            'node without descendants' => ['framboises'],
            'root with descendants' => ['fruits'],
            'root without descendants' => ['céréales'],
        ];
    }
}
