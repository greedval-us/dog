<?php

namespace App\Modules\Pets\Calculators;

final class ExteriorGeneticsCalculator
{
    /**
     * @param  array{type: int, structure: int, movement: int}  $father
     * @param  array{type: int, structure: int, movement: int}  $mother
     * @param  array{type: int, structure: int, movement: int}  $variation
     * @return array{type: int, structure: int, movement: int}
     */
    public function inherit(array $father, array $mother, array $variation): array
    {
        $result = [];
        foreach (['type', 'structure', 'movement'] as $quality) {
            $result[$quality] = max(0, min(100, (int) round(($father[$quality] + $mother[$quality]) / 2) + $variation[$quality]));
        }

        return $result;
    }
}
