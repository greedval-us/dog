<?php

namespace Database\Factories;

use App\Models\DogWorkType;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DogWorkType> */
class DogWorkTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(3), 'name' => ['ru' => 'Поиск в парке', 'en' => 'Park search'],
            'description' => ['ru' => 'Помоги найти потерянную вещь.', 'en' => 'Help find a lost item.'],
            'required_skill_id' => Skill::factory(), 'required_skill_level' => 1, 'coins_reward' => 150,
            'gems_reward' => 0, 'duration_seconds' => 1800, 'energy_cost' => 10, 'daily_limit' => 20, 'is_active' => true];
    }
}
