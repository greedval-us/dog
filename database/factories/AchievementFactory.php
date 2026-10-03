<?php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
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
            'name' => ['ru' => 'Первый шаг', 'en' => 'First step'],
            'description' => ['ru' => 'Всё начинается с одной лапы.', 'en' => 'It all starts with one paw.'],
            'image_path' => 'images/achievements/first-dog.svg',
            'rules' => ['metric' => 'first_dog', 'target' => 1],
            'rule_description' => ['ru' => 'Заведи первую собаку.', 'en' => 'Adopt your first dog.'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
