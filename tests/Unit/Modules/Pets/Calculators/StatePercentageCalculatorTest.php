<?php

use App\Modules\Pets\Calculators\StatePercentageCalculator;

test('state percentages are rounded and bounded for every capacity', function (float $value, int $maximum, float $expected) {
    expect((new StatePercentageCalculator)->calculate($value, $maximum))->toBe($expected);
})->with([
    'full' => [200.0, 200, 100.0],
    'fractional' => [1.25, 10, 12.5],
    'rounded' => [1.0, 3, 33.3],
    'empty' => [0.0, 100, 0.0],
    'negative value' => [-10.0, 100, 0.0],
    'over capacity' => [150.0, 100, 100.0],
    'zero capacity' => [10.0, 0, 0.0],
    'negative capacity' => [10.0, -10, 0.0],
]);
