<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_category_id' => ItemCategory::factory(),
            'code' => fake()->unique()->slug(3),
            'name' => ['ru' => 'Мяч', 'en' => 'Ball'],
            'description' => null,
            'quality' => 3,
            'usage_limit' => 5,
            'characteristics' => ['mood' => 10, 'size' => 'medium'],
            'is_active' => true,
        ];
    }
}
