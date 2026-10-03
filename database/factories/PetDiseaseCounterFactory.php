<?php

namespace Database\Factories;

use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetDiseaseCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetDiseaseCounter>
 */
class PetDiseaseCounterFactory extends Factory
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
            'tracked_on' => now()->toDateString(),
            'action_count' => 0,
            'threshold' => 10,
        ];
    }
}
