<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Enums\PetStat;
use InvalidArgumentException;

/**
 * @phpstan-import-type Preparation from GameEventProtocol
 * @phpstan-import-type Modifiers from GameEventProtocol
 */
final class GameEventPerformance
{
    public function normalizedStat(int|float $value): float
    {
        $value = max(0, $this->finite($value));

        return 100 * $value / ($value + 70);
    }

    /** @return array<string, float> */
    public function stageWeights(string $discipline, int $index): array
    {
        if ($index < 0 || $index > 2) {
            throw new InvalidArgumentException('Unknown event stage.');
        }

        return match ($discipline.':'.$index) {
            'agility:0' => ['speed' => 0.6, 'agility' => 0.4],
            'agility:1' => ['agility' => 0.45, 'obedience' => 0.35, 'intelligence' => 0.2],
            'agility:2' => ['endurance' => 0.35, 'speed' => 0.45, 'agility' => 0.2],
            'canicross:0' => ['endurance' => 0.45, 'strength' => 0.45, 'speed' => 0.1],
            'canicross:1' => ['obedience' => 0.5, 'agility' => 0.3, 'speed' => 0.2],
            'canicross:2' => ['endurance' => 0.6, 'speed' => 0.4],
            'nosework:0' => ['intelligence' => 0.6, 'obedience' => 0.4],
            'nosework:1' => ['intelligence' => 0.5, 'obedience' => 0.5],
            'nosework:2' => ['intelligence' => 0.6, 'endurance' => 0.4],
            'conformation:0', 'conformation:1', 'conformation:2' => ['obedience' => 1.0],
            'progeny:0', 'progeny:1', 'progeny:2' => [],
            default => throw new InvalidArgumentException('Unsupported event discipline.'),
        };
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array<string, int|float>  $skills
     */
    public function stageQuality(string $discipline, array $stats, array $skills, int $index): float
    {
        $quality = 0.0;
        foreach ($this->stageWeights($discipline, $index) as $stat => $weight) {
            $quality += $this->normalizedStat($stats[$stat] ?? 0) * $weight;
        }
        if ($discipline === 'nosework') {
            $skill = $index === 1 ? 'search' : 'keen_nose';
            $quality += max(0, min(5, $this->finite($skills[$skill] ?? 0))) * 2;
        }

        return $quality;
    }

    /** @param array<string, int|float> $states */
    public function careMultiplier(array $states): float
    {
        $readiness = $this->state($states, 'health') * 0.35 + $this->state($states, 'energy') * 0.30
            + $this->state($states, 'hydration') * 0.20 + $this->state($states, 'satiety') * 0.15;

        return 0.72 + 0.28 * $readiness / 100;
    }

    /** @param array<string, int|float> $states */
    public function startingFatigue(array $states): float
    {
        return (100 - $this->state($states, 'energy')) * 0.35
            + (100 - $this->state($states, 'hydration')) * 0.12
            + (100 - $this->state($states, 'satiety')) * 0.08;
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array<string, int|float>  $states
     * @param  array<string, int|float>  $modifiers
     */
    public function startingFocus(array $stats, array $states, array $modifiers = []): float
    {
        return max(0, min(100, ($this->state($states, 'bond', 0) + $this->state($states, 'mood', 0)
            + $this->normalizedStat($stats['obedience'] ?? 0)) / 3 + $this->modifiers($modifiers)['focus'] * 100));
    }

    /**
     * @param  array<string, int|float>  $values
     * @return Modifiers
     */
    public function modifiers(array $values): array
    {
        return [
            'precision' => max(-0.2, min(0.2, $this->finite($values['precision'] ?? 0))),
            'stamina' => max(-0.2, min(0.2, $this->finite($values['stamina'] ?? 0))),
            'pace' => max(-0.2, min(0.2, $this->finite($values['pace'] ?? 0))),
            'focus' => max(-0.2, min(0.2, $this->finite($values['focus'] ?? 0))),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return Preparation
     */
    public function preparation(string $discipline, array $snapshot): array
    {
        $kind = GameEventDiscipline::tryFrom($discipline);
        if ($kind === null || ! is_array($snapshot['stats'] ?? null) || ! is_array($snapshot['states'] ?? null)) {
            throw new InvalidArgumentException('Invalid event preparation.');
        }
        $states = [];
        foreach (['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'mood', 'bond'] as $state) {
            $states[$state] = $this->state($snapshot['states'], $state, in_array($state, ['mood', 'bond'], true) ? 0 : 100);
        }
        $normalizedStats = [];
        foreach (PetStat::cases() as $stat) {
            $normalizedStats[$stat->value] = $this->normalizedStat($snapshot['stats'][$stat->value] ?? 0);
        }
        $modifiers = $this->modifiers($snapshot['modifiers'] ?? []);

        return [
            'careMultiplier' => $kind->isDocumentary() ? 1.0 : $this->careMultiplier($states),
            'initialFatigue' => $kind->isDocumentary() ? 0.0 : $this->startingFatigue($states),
            'initialFocus' => $kind->isDocumentary() ? 100.0 : $this->startingFocus($snapshot['stats'], $states, $modifiers),
            'states' => $states, 'normalizedStats' => $normalizedStats, 'modifiers' => $modifiers,
        ];
    }

    /** @param array<string, int|float> $states */
    private function state(array $states, string $key, float $fallback = 100): float
    {
        return max(0, min(100, $this->finite($states[$key] ?? $fallback)));
    }

    private function finite(int|float $value): float
    {
        if (! is_finite((float) $value)) {
            throw new InvalidArgumentException('Event characteristics must be finite.');
        }

        return (float) $value;
    }
}
