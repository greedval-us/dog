<?php

namespace Database\Factories;

use App\Models\BreedingLitter;
use App\Models\Pet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BreedingLitter> */
class BreedingLitterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'operation_token' => (string) Str::uuid(),
            'own_pet_id' => Pet::factory()->state(['born_at' => now()->subDays(8)]),
            'initiator_id' => fn (array $attributes): ?int => Pet::query()->findOrFail((int) $attributes['own_pet_id'])->user_id,
            'listing_id' => null,
            'partner_id' => null,
            'father_id' => fn (array $attributes): int => $attributes['own_pet_id'],
            'mother_id' => fn (array $attributes): int => Pet::factory()->female()
                ->for(Pet::query()->findOrFail((int) $attributes['father_id'])->dog)
                ->createOne(['born_at' => now()->subDays(8)])->id,
            'price' => 0,
            'snapshots' => [],
            'born_at' => now()->addDay(),
            'expires_at' => fn (array $attributes) => CarbonImmutable::parse($attributes['born_at'])->addDays(7),
            'delivered_at' => null,
        ];
    }
}
