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
            'offspring' => $this->offspring($tier, $key),
        ];
    }

    /**
     * @return list<array{exterior:array<string, float>, titles:list<array{code:string}>}>
     */
    private function offspring(int $tier, string $key): array
    {
        $familyDraws = $this->randomness->draws($key.':offspring:family', 3);
        $offspring = [];
        for ($index = 0; $index < 3; $index++) {
            $draws = $this->randomness->draws($key.':offspring:'.$index, 4);
            $exterior = [];
            foreach (['type', 'structure', 'movement'] as $offset => $trait) {
                $familyQuality = 60 + $tier * 8 + $familyDraws[$offset] * 15;
                $exterior[$trait] = max(0.0, min(100.0, $familyQuality + $draws[$offset] * 10 - 5));
            }
            $titleCount = $tier === 0 ? 0 : $tier - 1 + (int) ($draws[3] < 0.5);
            $titles = array_slice([
                ['code' => 'conformation_daily_winner'],
                ['code' => 'conformation_weekly_winner'],
            ], 0, $titleCount);
            $offspring[] = ['exterior' => $exterior, 'titles' => $titles];
        }

        return $offspring;
    }
}
