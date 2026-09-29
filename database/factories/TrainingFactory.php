<?php

namespace Database\Factories;

use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => ['ru' => 'Бег', 'en' => 'Running'],
            'stat_gains' => ['speed' => 4, 'endurance' => 4],
            'state_costs' => ['satiety' => 5, 'hydration' => 8, 'cleanliness' => 3],
            'energy_cost' => 15,
            'duration_seconds' => 120,
            'cooldown_seconds' => 600,
            'risk_chance' => 0,
            'is_active' => true,
        ];
    }
}
