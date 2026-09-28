<?php

namespace App\Modules\Pets\Calculators;

final class StatePercentageCalculator
{
    public function calculate(float $value, int $maximum, ?int $precision = 1): float
    {
        $percentage = $maximum > 0 ? max(0, min(100, $value / $maximum * 100)) : 0.0;

        return $precision === null ? $percentage : round($percentage, $precision);
    }
}
