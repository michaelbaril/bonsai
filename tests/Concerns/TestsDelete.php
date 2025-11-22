<?php

namespace Baril\Bonsai\Tests\Concerns;

use Baril\Bonsai\TreeException;

trait TestsDelete
{
    /**
     * @dataProvider deleteFailureProvider
     */
    public function test_delete_failure($node, $method = 'delete')
    {
        $this->expectException(TreeException::class);
        $this->getModel($node)->$method();
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
    public function test_delete_success($node, $method = 'delete', $shouldDeleteClosures = true)
    {
        $model = $this->getModel($node);
        $closuresCount = $model->newClosureQuery()->count();
        $modelClosuresCount = $model->ascendingClosures()->count() + $model->descendingClosures()->count() - 1;

        $model->$method();

        $this->assertFalse($this->newQuery()->whereKey($model->getKey())->exists());

        $this->assertEquals(
            !$shouldDeleteClosures,
            $model->newClosureQuery()->where('ancestor_id', $model->getKey())->exists()
        );
        $this->assertEquals(
            !$shouldDeleteClosures,
            $model->newClosureQuery()->where('descendant_id', $model->getKey())->exists()
        );
        $this->assertEquals(
            $shouldDeleteClosures
                ? $closuresCount - $modelClosuresCount
                : $closuresCount,
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
     * @dataProvider deleteNodeProvider
     */
    public function test_delete_node($node, $method = 'delete')
    {
        $method .= 'Node';
        $model = $this->getModel($node);
        $parent = $model->parent;
        $children = $model->children;

        // Load descendants on children:
        $children->each(function ($child) {
            $child->descendants;
        });

        $model->$method();

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

    public static function deleteNodeProvider()
    {
        return [
            'node with descendants' => ['fruits rouges'],
            'node without descendants' => ['framboises'],
            'root with descendants' => ['fruits'],
            'root without descendants' => ['céréales'],
        ];
    }

    /**
     * @dataProvider deleteTreeProvider
     */
    public function test_delete_tree($node, $method = 'delete', $shouldDeleteClosures = true)
    {
        $method .= 'Tree';
        $model = $this->getModel($node);
        $parent = $model->parent;
        $parentClosuresBefore = $parent ? $parent->descendingClosures()->count() : null;
        $descendants = $model->descendants;
        $descendingClosuresCount = $model->descendingClosures()->count();

        $model->$method();

        // Check that all nodes have been deleted:
        $descendants->merge([$model])->each(function ($model) {
            $this->assertFalse($this->newQuery()->whereKey($model->getKey())->exists());
        });

        // Check that closures have been deleted (or not):
        if ($parent) {
            $this->assertEquals(
                $shouldDeleteClosures
                    ? $parentClosuresBefore - $descendingClosuresCount
                    : $parentClosuresBefore,
                $parent->descendingClosures()->count()
            );
        }
    }

    public static function deleteTreeProvider()
    {
        return static::deleteNodeProvider();
    }
}
