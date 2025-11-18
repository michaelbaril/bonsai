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
     * @dataProvider graftProvider
     */
    public function test_graft($node, $newParent)
    {
        $model = $this->getModel($node);
        $parent = $this->getModel($newParent);
        $return = $parent->graft($model);

        $this->assertModel($parent, $return);
        $this->assertModel($parent, $model->fresh()->parent);
    }

    /**
     * @dataProvider graftProvider
     */
    public function test_graft_onto($node, $newParent)
    {
        $model = $this->getModel($node);
        $parent = $this->getModel($newParent);
        $return = $model->graftOnto($parent);

        $this->assertModel($model, $return);
        $this->assertModel($parent, $model->fresh()->parent);
    }

    public static function graftProvider()
    {
        return array_filter(static::changeParentProvider(), function ($set) {
            return $set[1] !== null;
        });
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

    /**
     * @dataProvider cutProvider
     */
    public function test_cut($node)
    {
        $model = $this->getModel($node);
        $descendantsBefore = $model->descendants;
        $return = $model->cut();

        $this->assertModel($model, $return);
        $this->assertNull($model->fresh()->parent);
        $this->assertModels($descendantsBefore, $model->descendants());
    }

    public static function cutProvider()
    {
        return [
            'already a root' => ['fruits'],
            'regular node' => ['fruits rouges'],
            'leaf' => ['fraises des bois'],
        ];
    }
}
