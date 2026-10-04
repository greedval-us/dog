<?php

namespace Database\Factories;

use App\Models\GameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameEvent> */
class GameEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'discipline' => 'agility', 'frequency' => 'daily', 'status' => 'registration',
            'registration_opens_at' => now()->subDay(), 'closes_at' => now()->addMinutes(45),
            'starts_at' => now()->addHour(), 'ends_at' => now()->addMinutes(70), 'seed' => bin2hex(random_bytes(16)),
            'rules' => ['version' => 1, 'fee' => 25, 'prizes' => [100, 60, 40], 'stages' => ['approach', 'technical', 'finish'], 'energy_cost' => 20, 'field_size' => 8],
        ];
    }
}
