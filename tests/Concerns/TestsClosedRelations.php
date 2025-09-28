<?php

namespace Baril\Bonsai\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;

trait TestsClosedRelations
{
    /**
     * @dataProvider loadClosedRelationProvider
     */
    public function test_load_closed_relation($node, $relation)
    {
        $model = $this->getModel($node);
        $closedRelation = $model->$relation()->getClosedRelation();

        $mergedRelated = [];
        $related = $model->$relation;

        // Check that the closed relation has been loaded (with the correct models)
        // on the model and all related models:
        $related->merge([$model])->each(function ($modelToCheck) use ($closedRelation, &$mergedRelated) {
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
        $this->assertModels(
            $related,
            $mergedRelated
        );      
    }

    public static function loadClosedRelationProvider()
    {
        return [
            'leaf\'s ancestors' => ['fraises tagada', 'ancestors'],
            'root\'s descendants' => ['fruits', 'descendants'],
            'no ancestors' => ['céréales', 'ancestors'],
            'no descendants' => ['céréales', 'descendants'],
        ];
    }
}
