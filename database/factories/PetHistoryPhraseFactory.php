<?php

namespace Database\Factories;

use App\Models\PetHistoryEvent;
use App\Models\PetHistoryPhrase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetHistoryPhrase> */
class PetHistoryPhraseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pet_history_event_id' => PetHistoryEvent::factory()->thought(),
            'text' => ['ru' => 'Хозяин, хочу есть!', 'en' => 'Human, I am hungry!'], 'sort_order' => 0, 'is_active' => true];
    }
}
