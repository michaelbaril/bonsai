<?php

namespace Baril\Bonsai\Tests;

abstract class TreeTestCase extends TestCase
{
    protected static $defaultModelClass;

    protected static $tree = [
        'fruits' => [
            'fruits rouges' => [
                'framboises',
                'fraises' => [
                    'fraises des bois',
                    'fraises tagada',
                ],
                'myrtilles',
            ],
            'kiwis',
        ],
        'légumes' => [
            'haricots verts',
            'brocolis' => [
                'brocolis pour mettre dans le minestrone',
            ],
            'tomates',
        ],
        'céréales',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/' . class_basename(static::$defaultModelClass));
        $this->createTree(static::$tree);
    }

    protected function createTree($data)
    {
        // Making sure the table is empty
        // to avoid issues with PostgreSQL:
        $class = static::$defaultModelClass;
        (new $class())->newClosureQuery()->delete();
        $class::query()->delete();

        $this->models = collect();
        $this->addToTree($data);
    }

    protected function addToTree($data, $parent = null)
    {
        foreach ($data as $key => $value) {
            if (is_iterable($value)) {
                $node = $this->createNode($key, $parent);
                $this->addToTree($value, $node->getKey());
            } else {
                $node = $this->createNode($value, $parent);
            }
            $this->models->put($node->name, $node);
        }
    }

    protected function createNode($node, $parent)
    {
        [$class, $name] = $this->parseNode($node);
        $instance = new $class();
        $parentKey = $instance->getParentForeignKeyName();
        return $class::create([
            'name' => $name,
            $parentKey => $parent
        ]);
    }

    protected function parseNode($text)
    {
        if (preg_match('/^\[(.*)\](.*)$/', $text, $matches)) {
            return [
                dirname(static::class) . '\\Models\\' . $matches[1],
                trim($matches[2]),
            ];
        }

        return [static::$defaultModelClass, trim($text)];
    }

    protected function assertTree($expected, $actual, $class = null, $checkOrder = false)
    {
        $expectedCurrentLevel = [];
        foreach ($expected as $k => $v) {
            $expectedCurrentLevel[] = is_array($v) ? $k : $v;
        }

        $this->assertModels($expectedCurrentLevel, $actual, $class, $checkOrder);

        $actual->each(function ($model) use ($expected, $class, $checkOrder) {
            if ($model->relationLoaded('children')) {
                $expectedChildren = $expected[$model->name] ?? [];
                $this->assertTree($expectedChildren, $model->children, $class, $checkOrder);
            }
        });
    }

    protected function showClosures()
    {
        $closures = $this->models->first()->newClosureQuery()->get()
            ->sortBy(['ancestor_id', 'descendant_id'])
            ->map(function ($closure) {
                return "{$closure->ancestor_id} -> {$closure->descendant_id} ({$closure->depth})";
            })
            ->values()
            ->all();
        dump($closures);
    }
}
