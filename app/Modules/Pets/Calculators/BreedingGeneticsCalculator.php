<?php

namespace App\Modules\Pets\Calculators;

use InvalidArgumentException;

final class BreedingGeneticsCalculator
{
    public const int MIN_OFFSPRING_SPREAD = 9800;

    public const int MAX_OFFSPRING_SPREAD = 10200;

    private const int MAX_DATABASE_INTEGER = 2147483647;

    public function offspringPotential(float $father, float $mother, int $spread): int
    {
        return max(1, min(self::MAX_DATABASE_INTEGER, (int) round(($father + $mother) / 2 * ($spread / 10000))));
    }

    /** @return array{min: float, max: float} */
    public function range(int $current, int $potential): array
    {
        if ($potential < 1 || $potential > self::MAX_DATABASE_INTEGER) {
            throw new InvalidArgumentException('Parent genetic limits must be positive database integers.');
        }

        $current = max(0, min($potential, $current));
        $trainedPercent = $current / $potential * 100;

        if ($trainedPercent < 50) {
            $adjusted = $potential * (1 - 0.15 * (50 - $trainedPercent) / 50);

            return ['min' => $adjusted, 'max' => $adjusted];
        }

        $steps = intdiv(max(0, $current * 100 - $potential * 50), $potential * 20);

        return [
            'min' => $potential * (1 + $steps * 0.03),
            'max' => $potential * (1 + $steps * 0.05),
        ];
    }
}
