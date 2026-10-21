<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\PetSaleListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetSaleListing> */
class PetSaleListingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'user_id' => fn (array $attributes): ?int => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->user_id,
            'price' => 100, 'is_active' => true,
        ];
    }
}
