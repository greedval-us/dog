<?php

namespace Database\Factories;

use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemCategory> */
class ItemCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => ['ru' => 'Категория', 'en' => 'Category'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
