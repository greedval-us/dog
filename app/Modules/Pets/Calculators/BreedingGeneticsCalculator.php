<?php

namespace App\Modules\Pets\Calculators;

use InvalidArgumentException;

final class BreedingGeneticsCalculator
{
    /** @return array{min: float, max: float} */
    public function range(int $current, int $potential): array
    {
        if ($potential < 1 || $potential > 2147483647) {
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
