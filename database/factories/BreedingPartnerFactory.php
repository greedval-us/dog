<?php

namespace Database\Factories;

use App\Models\BreedingPartner;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BreedingPartner> */
class BreedingPartnerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory()->state([
                'user_id' => null, 'born_at' => now()->subDays(8),
                'endurance' => 70, 'speed' => 70, 'strength' => 70,
                'agility' => 70, 'obedience' => 70, 'intelligence' => 70,
            ]),
            'code' => fake()->unique()->uuid(),
            'price' => 100,
            'is_active' => true,
        ];
    }
}
