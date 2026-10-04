<?php

namespace Database\Factories;

use App\Models\ShopDelivery;
use App\Models\ShopOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopDelivery>
 */
class ShopDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_offer_id' => ShopOffer::factory(),
            'scheduled_at' => now()->startOfSecond(),
            'stock_before' => 0,
            'stock_after' => 18,
        ];
    }
}
