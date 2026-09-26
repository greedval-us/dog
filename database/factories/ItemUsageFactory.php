<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ItemUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemUsage> */
class ItemUsageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'inventory_item_id' => fn (array $attributes): int => InventoryItem::factory()->create(['user_id' => $attributes['user_id']])->id,
            'item_id' => fn (array $attributes): int => InventoryItem::query()->whereKey($attributes['inventory_item_id'])->firstOrFail()->item_id,
            'token' => fake()->uuid(),
            'uses_spent' => 1,
            'uses_before' => 5,
            'uses_after' => 4,
        ];
    }
}
