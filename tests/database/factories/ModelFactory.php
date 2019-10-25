<?php

use Faker\Generator as Faker;

$factory->define(Baril\Bonsai\Tests\Models\Tag::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->word,
    ];
});
