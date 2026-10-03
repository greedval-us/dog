<?php

namespace Database\Factories;

use App\Models\BreedingLitter;
use App\Models\Puppy;
use App\Modules\Pets\Enums\PetSex;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Puppy> */
class PuppyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'litter_id' => BreedingLitter::factory(),
            'dog_id' => fn (array $attributes): int => BreedingLitter::query()->findOrFail((int) $attributes['litter_id'])->father->dog_id,
            'father_id' => fn (array $attributes): int => BreedingLitter::query()->findOrFail((int) $attributes['litter_id'])->father_id,
            'mother_id' => fn (array $attributes): int => BreedingLitter::query()->findOrFail((int) $attributes['litter_id'])->mother_id,
            'user_id' => fn (array $attributes): ?int => BreedingLitter::query()->findOrFail((int) $attributes['litter_id'])->initiator_id,
            'pet_id' => null,
            'status' => 'unborn',
            'name' => fake()->firstName(),
            'sex' => PetSex::Male,
            'coat_color' => 'black',
            'generation' => 2,
            'endurance_potential' => 100, 'speed_potential' => 100,
            'strength_potential' => 100, 'agility_potential' => 100,
            'obedience_potential' => 100, 'intelligence_potential' => 100,
            'sale_price' => null,
            'expires_at' => fn (array $attributes) => BreedingLitter::query()->findOrFail((int) $attributes['litter_id'])->expires_at,
            'placed_at' => null,
        ];
    }
}
