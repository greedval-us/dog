<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\PetHistoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetHistoryEntry> */
class PetHistoryEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pet_id' => Pet::factory(), 'kind' => 'action', 'event_code' => 'care.meal',
            'source_key' => fake()->uuid(), 'title' => ['ru' => 'Кормление', 'en' => 'Feeding'],
            'message' => null, 'details' => ['stage' => 'completed'], 'occurred_at' => now()];
    }
}
