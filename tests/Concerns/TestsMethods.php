<?php

namespace Baril\Bonsai\Tests\Concerns;

trait TestsMethods
{
    /**
     * @dataProvider methodsProvider
     */
    public function test_methods($node, $method, $otherNode, $expected)
    {
        $arguments = $otherNode ? [$this->getModel($otherNode)] : [];
        $this->assertEquals(
            $expected,
            call_user_func_array([$this->getModel($node), $method], $arguments)
        );
    }

    public static function methodsProvider()
    {
        return [
            'isRoot()=true' => ['fruits', 'isRoot', null, true],
            'isRoot()=false' => ['fruits rouges', 'isRoot', null, false],
            'isLeaf()' => ['fraises tagada', 'isLeaf', null, true],
            'hasChildren()' => ['fruits rouges', 'hasChildren', null, true],
            'isChildOf()=true' => ['fraises tagada', 'isChildOf', 'fraises', true],
            'isChildOf()=false' => ['fraises tagada', 'isChildOf', 'fruits rouges', false],
            'isParentOf()' => ['fraises tagada', 'isParentOf', 'fruits rouges', false],
            'isDescendantOf()' => ['fraises tagada', 'isDescendantOf', 'fruits rouges', true],
            'isAncestorOf()' => ['fruits rouges', 'isAncestorOf', 'fraises tagada', true],
            'isSiblingOf()=true' => ['fraises tagada', 'isSiblingOf', 'fraises des bois', true],
            'isSiblingOf()=false' => ['fraises tagada', 'isSiblingOf', 'fraises', false],
        ];
    }
}
