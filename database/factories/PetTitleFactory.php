<?php

namespace Database\Factories;

use App\Models\GameEventEntry;
use App\Models\PetTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetTitle> */
class PetTitleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'game_event_entry_id' => fn (): int => GameEventEntry::factory()->createOne()->id,
            'pet_id' => fn (array $attributes): int => GameEventEntry::query()->whereKey($attributes['game_event_entry_id'])->firstOrFail()->pet_id,
            'discipline' => 'agility', 'frequency' => 'daily', 'code' => 'agility_daily_winner', 'awarded_at' => now(),
        ];
    }
}
