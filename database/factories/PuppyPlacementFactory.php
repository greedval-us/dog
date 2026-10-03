<?php

namespace Database\Factories;

use App\Models\Puppy;
use App\Models\PuppyPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PuppyPlacement> */
class PuppyPlacementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'puppy_id' => Puppy::factory(),
            'user_id' => fn (array $attributes): ?int => Puppy::query()->findOrFail((int) $attributes['puppy_id'])->user_id,
            'pet_id' => null,
            'operation_token' => (string) Str::uuid(),
            'kind' => 'keep',
            'price' => 0,
            'name' => fn (array $attributes): string => Puppy::query()->findOrFail((int) $attributes['puppy_id'])->name,
            'seller_id' => null,
        ];
    }
}
