<?php

namespace Database\Factories;

use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetDisease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetDisease>
 */
class PetDiseaseFactory extends Factory
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
            'disease_id' => Disease::factory(),
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    public function recovered(): static
    {
        return $this->state(fn (): array => ['started_at' => now()->subDay(), 'ended_at' => now()]);
    }
}
