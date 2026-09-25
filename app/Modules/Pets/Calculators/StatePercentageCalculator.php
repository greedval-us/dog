<?php

namespace App\Modules\Pets\Calculators;

final class StatePercentageCalculator
{
    public function calculate(float $value, int $maximum): float
    {
        return $maximum > 0
            ? round(max(0, min(100, $value / $maximum * 100)), 1)
            : 0.0;
    }
}
