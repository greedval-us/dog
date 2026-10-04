<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\PetSportRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetSportRecord> */
class PetSportRecordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pet_id' => Pet::factory(), 'discipline' => 'agility', 'starts' => 0, 'wins' => 0, 'experience' => 0, 'tier' => 0];
    }
}
