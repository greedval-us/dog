<?php

namespace Database\Factories;

use App\Models\Dog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dog> */
class DogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'breed' => fake()->unique()->slug(),
            'name' => ['ru' => 'Тестовая порода', 'en' => 'Test breed'],
            'description' => ['ru' => 'Описание породы', 'en' => 'Breed description'],
            'size' => 'medium',
            'coat_colors' => ['black' => ['ru' => 'Чёрный', 'en' => 'Black']],
            'is_starter' => false,
            'endurance_potential' => 100,
            'speed_potential' => 100,
            'strength_potential' => 100,
            'agility_potential' => 100,
            'obedience_potential' => 100,
            'intelligence_potential' => 100,
            'health_max' => 100,
            'energy_max' => 100,
            'satiety_max' => 100,
            'hydration_max' => 100,
            'mood_max' => 100,
            'cleanliness_max' => 100,
            'bond_max' => 100,
            'food_per_day' => 50,
            'water_per_day' => 50,
        ];
    }
}
