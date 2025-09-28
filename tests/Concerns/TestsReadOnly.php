<?php

namespace Baril\Bonsai\Tests\Concerns;

use LogicException;

trait TestsReadOnly
{
    /**
     * @dataProvider belongsToManyThroughClosuresRelationIsReadonlyProvider
     */
    public function test_belongs_to_many_through_closures_relation_is_readonly($method, $args = [])
    {
        $this->expectException(LogicException::class);
        $this->newQuery()->first()->descendants()->$method(...$args);
    }

    public static function belongsToManyThroughClosuresRelationIsReadonlyProvider()
    {
        $class = static::$defaultModelClass;
        $testData = [
            ['save', [new $class()]],
            ['saveMany', [[]]],
            ['create', [[]]],
            ['createMany', [[]]],
            ['toggle', [[]]],
            ['syncWithoutDetaching', [[]]],
            ['sync', [[]]],
            ['attach', [new $class()]],
            ['detach'],
        ];
        return array_combine(
            array_map(function ($data) {
                return $data[0];
            }, $testData),
            $testData
        );
    }
}
