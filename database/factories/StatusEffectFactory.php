<?php

namespace Database\Factories;

use App\Models\StatusEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusEffect>
 */
class StatusEffectFactory extends Factory
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
            'kind' => 'buff',
            'name' => ['ru' => 'Комфорт', 'en' => 'Comfort'],
            'description' => ['ru' => 'Меньше расход энергии.', 'en' => 'Reduced energy cost.'],
            'modifiers' => ['energy_cost_percent' => -10],
            'duration_seconds' => 1800,
            'condition_state' => null,
            'condition_below' => null,
            'is_active' => true,
        ];
    }
}
