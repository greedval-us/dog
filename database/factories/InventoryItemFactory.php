<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_id' => Item::factory(),
            'item_purchase_id' => null,
            'name' => ['ru' => 'Мяч', 'en' => 'Ball'],
            'quality' => 3,
            'usage_limit' => 5,
            'remaining_uses' => 5,
            'characteristics' => ['mood' => 10, 'size' => 'medium'],
        ];
    }
}
