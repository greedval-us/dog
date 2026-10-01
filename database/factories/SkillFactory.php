<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Skill> */
class SkillFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => ['ru' => 'Тестовая запись', 'en' => 'Test entry'],
            'description' => null,
            'levels' => array_map(fn (int $level): array => [
                'price' => 100 * $level,
                'requirements' => ['intelligence' => 10 * $level, 'obedience' => 5 * $level],
            ], range(1, 5)),
            'is_active' => true,
        ];
    }
}
