<?php

namespace Baril\Bonsai\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;

trait TestsClosedRelations
{
    /**
     * @dataProvider loadClosedRelationProvider
     */
    public function test_load_closed_relation($node, $relation, $closedRelation, $checkModel = true, $checkRelated = true, $checkMerged = true)
    {
        $model = $this->getModel($node);

        $mergedRelated = [];
        $related = $model->$relation;

        $modelsToCheck = collect();
        if ($checkModel) {
            $modelsToCheck = $modelsToCheck->push($model);
        }
        if ($checkRelated) {
            $modelsToCheck = $modelsToCheck->merge($related);
        }

        // Check that the closed relation has been loaded (with the correct models)
        // on the model and all related models:
        $modelsToCheck
            ->each(function ($modelToCheck) use ($closedRelation, &$mergedRelated) {
                $this->assertTrue($modelToCheck->relationLoaded($closedRelation));
                $related = $modelToCheck->$closedRelation ?? collect();
                if ($related instanceof Model) {
                    $related = collect([$related]);
                }
                $this->assertModels(
                    $modelToCheck->$closedRelation()->get(),
                    $related
                );
                $mergedRelated = array_merge($mergedRelated, $related->all());
            });

        // Check that when we merge all closed relation, we find the same models
        // as the "through-closures" relation:
        if ($checkMerged) {
            $this->assertModels(
                $related,
                $mergedRelated
            );
        }
    }

    public static function loadClosedRelationProvider()
    {
        return [
            'leaf\'s ancestors' => ['fraises tagada', 'ancestors', 'parent'],
            'root\'s descendants' => ['fruits', 'descendants', 'children'],
            'no ancestors' => ['céréales', 'ancestors', 'parent'],
            'no descendants' => ['céréales', 'descendants', 'children'],
        ];
    }

    /**
     * @dataProvider eagerloadClosedRelationProvider
     */
    public function test_eager_load_closed_relation($class, $relation, $closedRelation, $checkModel = true, $checkRelated = true)
    {
        $models = $this->newQuery($class)->with($relation)->get();        

        $models->each(function ($model) use ($relation, $closedRelation, $checkModel, $checkRelated) {
            if ($checkModel) {
                $this->assertTrue($model->relationLoaded($closedRelation));
                $this->assertModels($model->$closedRelation()->get(), $model->$closedRelation);
            }

            if ($checkRelated) {
                $model->$relation->each(function ($model) use ($closedRelation) {
                    $this->assertTrue($model->relationLoaded($closedRelation));
                    $this->assertModels($model->$closedRelation()->get(), $model->$closedRelation);
                });
            }
        });
    }

    public static function eagerloadClosedRelationProvider()
    {
        return [
            'ancestors' => [null, 'ancestors', 'parent'],
            'descendants' => [null, 'descendants', 'children'],
        ];
    }
}
