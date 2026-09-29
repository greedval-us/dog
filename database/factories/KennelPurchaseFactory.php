<?php

namespace Database\Factories;

use App\Models\KennelPurchase;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KennelPurchase>
 */
class KennelPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'user_id' => fn (array $attributes): ?int => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->user_id,
            'dog_id' => fn (array $attributes): int => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->dog_id,
            'pet_name' => fn (array $attributes): string => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->name,
            'token' => fake()->uuid(),
            'price_paid' => 500,
        ];
    }
}
