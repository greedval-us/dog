<?php

namespace Database\Factories;

use App\Models\PetSaleListing;
use App\Models\PetSalePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetSalePurchase> */
class PetSalePurchaseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'listing_id' => PetSaleListing::factory(),
            'pet_id' => fn (array $attributes): int => PetSaleListing::query()->whereKey($attributes['listing_id'])->firstOrFail()->pet_id,
            'seller_id' => fn (array $attributes): ?int => PetSaleListing::query()->whereKey($attributes['listing_id'])->firstOrFail()->user_id,
            'buyer_id' => User::factory(), 'token' => fake()->uuid(), 'price_paid' => 100,
        ];
    }
}
