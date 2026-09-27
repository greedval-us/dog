<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\PetCareAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PetCareAction> */
class PetCareActionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'user_id' => fn (array $attributes): ?int => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->user_id,
            'token' => (string) Str::uuid(),
            'activity_token' => (string) Str::uuid(),
            'group' => 'play',
            'variant' => 'attention',
            'inventory_item_ids' => [],
            'effects' => ['mood' => 10, 'bond' => 2, 'satiety' => -3, 'hydration' => -3],
            'ends_at' => now()->addMinutes(2),
            'available_at' => now()->addMinutes(12),
            'completed_at' => null,
        ];
    }
}
