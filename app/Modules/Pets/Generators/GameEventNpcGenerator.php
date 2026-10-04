<?php

namespace App\Modules\Pets\Generators;

use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\Enums\PetStat;

final class GameEventNpcGenerator
{
    public function __construct(private GameEventRandomness $randomness) {}

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    public function generate(array $reference, string $division, string $key, int $index): array
    {
        $draws = $this->randomness->draws($key, 12);
        $tier = str_starts_with($division, 'champion') ? 2 : (str_starts_with($division, 'open') ? 1 : 0);
        $stats = [];
        foreach (PetStat::cases() as $offset => $stat) {
            $stats[$stat->value] = (int) round(25 + $tier * 35 + $draws[$offset] * 30);
        }
        $exterior = ['type' => 60 + $draws[6] * 25, 'structure' => 60 + $draws[7] * 25, 'movement' => 60 + $draws[8] * 25];

        return [
            'version' => GameEventProtocol::SNAPSHOT_VERSION,
            'name' => 'NPC #'.($index + 1), 'breed' => $reference['breed'], 'breed_id' => $reference['breed_id'], 'size' => $reference['size'],
            'stats' => $stats, 'potentials' => array_fill_keys(array_keys($stats), 140),
            'states' => ['health' => 100, 'energy' => 90, 'satiety' => 90, 'hydration' => 90, 'mood' => 85, 'cleanliness' => 90, 'bond' => 70],
            'skills' => ['keen_nose' => $tier + 1, 'search' => $tier + 1], 'exterior' => $exterior,
            'career_experience' => $tier * 40, 'gear' => [], 'modifiers' => [],
            'offspring' => array_fill(0, 3, ['exterior' => $exterior, 'titles' => $tier > 0 ? [['code' => 'conformation_daily_winner']] : []]),
        ];
    }
}
