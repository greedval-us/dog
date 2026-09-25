<?php

namespace Database\Factories;

use App\Models\Disease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Disease> */
class DiseaseFactory extends Factory
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
