<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ShopOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopOffer> */
class ShopOfferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'currency' => 'coins',
            'price' => 25,
            'stock' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
