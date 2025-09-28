<?php

namespace Baril\Bonsai\Tests\Concerns;

use Baril\Bonsai\TreeException;

trait TestsMove
{
    /**
     * @dataProvider changeParentProvider
     */
    public function test_change_parent($node, $newParent)
    {
        $model = $this->getModel($node);
        $descendants = $model->descendants()->get();

        $parent = $newParent ? $this->getModel($newParent) : null;
        $ancestors = $parent ? $parent->ancestors()->withSelf()->get() : [];
        $parentsDescendants = $parent ? $parent->descendants()->get() : null;

        $model->parent()->associate($parent);
        $model->save();

        // New ancestors:
        $this->assertModels(
            $ancestors,
            $model->ancestors()
        );

        // Same descendants as before:
        $this->assertModels(
            $descendants,
            $model->descendants()
        );

        // Parent's new descendants:
        if ($parent) {
            $this->assertModels(
                $descendants->merge($parentsDescendants)->push($model),
                $parent->descendants()
            );
        }
    }

    public static function changeParentProvider()
    {
        return [
            'new parent is root' => ['fruits rouges', 'légumes'],
            'new parent is leaf' => ['fruits', 'brocolis pour mettre dans le minestrone'],
            'no new parent' => ['fraises', null],
            'former root gets a parent' => ['légumes', 'fraises tagada'],
        ];
    }

    /**
     * @dataProvider redundancyProvider
     */
    public function test_redundancy($node, $forbiddenParent)
    {
        $model = $this->getModel($node);

        $this->expectException(TreeException::class);

        $model->parent()->associate($this->getModel($forbiddenParent));
        $model->save();
    }

    public static function redundancyProvider()
    {
        return [
            'node itself' => ['fruits rouges', 'fruits rouges'],
            'child' => ['fruits rouges', 'framboises'],
            'descendant' => ['fruits rouges', 'fraises tagada'],
        ];
    }
}
