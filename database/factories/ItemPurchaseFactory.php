<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemPurchase;
use App\Models\ShopOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemPurchase> */
class ItemPurchaseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_offer_id' => ShopOffer::factory(),
            'item_id' => fn (array $attributes): int => ShopOffer::query()->whereKey($attributes['shop_offer_id'])->firstOrFail()->item_id,
            'currency_transaction_id' => null,
            'token' => fake()->uuid(),
            'currency' => 'coins',
            'price_paid' => 25,
            'item_snapshot' => fn (array $attributes): array => Item::query()->whereKey($attributes['item_id'])->firstOrFail()->inventorySnapshot(),
        ];
    }
}
