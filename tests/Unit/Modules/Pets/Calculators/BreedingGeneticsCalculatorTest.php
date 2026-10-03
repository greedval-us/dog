<?php

use App\Modules\Pets\Calculators\BreedingGeneticsCalculator;

test('parent genetic limits reward full training steps and penalize training below fifty percent', function (int $current, int $potential, float $minimum, float $maximum) {
    $calculator = new BreedingGeneticsCalculator;

    $range = $calculator->range($current, $potential);

    expect($range['min'])->toEqualWithDelta($minimum, 0.000001);
    expect($range['max'])->toEqualWithDelta($maximum, 0.000001);
})->with([
    'untrained loses fifteen percent' => [0, 100, 85.0, 85.0],
    'quarter trained loses half the maximum penalty' => [25, 100, 92.5, 92.5],
    'just below neutral' => [49, 100, 99.7, 99.7],
    'neutral threshold' => [50, 100, 100.0, 100.0],
    'below first full step' => [69, 100, 100.0, 100.0],
    'first full step' => [70, 100, 103.0, 105.0],
    'below second full step' => [89, 100, 103.0, 105.0],
    'second full step' => [90, 100, 106.0, 110.0],
    'fully trained still has two full steps' => [100, 100, 106.0, 110.0],
    'personal potential defines the percentage' => [140, 200, 206.0, 210.0],
    'negative current training is bounded' => [-10, 100, 85.0, 85.0],
    'current training above potential is bounded' => [1000, 100, 106.0, 110.0],
]);

test('parent genetics reject limits outside the positive database range', function (int $potential) {
    $calculator = new BreedingGeneticsCalculator;

    expect(fn () => $calculator->range(1, $potential))->toThrow(InvalidArgumentException::class);
})->with([0, -1, 2147483648]);
