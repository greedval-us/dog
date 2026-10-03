<?php

namespace Database\Factories;

use App\Models\BreedingListing;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BreedingListing> */
class BreedingListingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory()->state(['born_at' => now()->subDays(8)]),
            'user_id' => fn (array $attributes): ?int => Pet::query()->findOrFail((int) $attributes['pet_id'])->user_id,
            'price' => 100,
            'is_active' => true,
        ];
    }
}
