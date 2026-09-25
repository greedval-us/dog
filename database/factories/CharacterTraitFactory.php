<?php

namespace Database\Factories;

use App\Models\CharacterTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CharacterTrait> */
class CharacterTraitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => ['ru' => 'Тестовая запись', 'en' => 'Test entry'],
            'description' => null,
        ];
    }
}
