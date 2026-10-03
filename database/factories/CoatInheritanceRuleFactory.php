<?php

namespace Database\Factories;

use App\Models\CoatInheritanceRule;
use App\Models\Dog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CoatInheritanceRule> */
class CoatInheritanceRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dog_id' => Dog::factory(),
            'first_color' => 'black',
            'second_color' => 'black',
            'offspring_color' => 'black',
            'weight' => 10000,
        ];
    }
}
