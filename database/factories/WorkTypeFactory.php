<?php

namespace Database\Factories;

use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkType>
 */
class WorkTypeFactory extends Factory
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
            'name' => ['ru' => 'Помощник в приюте', 'en' => 'Shelter helper'],
            'description' => ['ru' => 'Помоги приюту с повседневными делами.', 'en' => 'Help the shelter with everyday tasks.'],
            'coins_reward' => 50,
            'gems_bonus' => 5,
            'is_active' => true,
        ];
    }
}
