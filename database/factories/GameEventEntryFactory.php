<?php

namespace Database\Factories;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GameEventEntry> */
class GameEventEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'game_event_id' => GameEvent::factory(), 'user_id' => User::factory(),
            'pet_id' => fn (array $attributes): int => Pet::factory()->create(['user_id' => $attributes['user_id']])->id,
            'operation_token' => (string) Str::uuid(), 'registration_hash' => hash('sha256', fake()->uuid()),
            'division' => 'novice:medium', 'status' => 'registered', 'fee' => 25,
            'plan' => ['stages' => ['balanced', 'balanced', 'balanced']], 'gear_ids' => [],
        ];
    }
}
