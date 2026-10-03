<?php

use App\Modules\Pets\DTO\PetHistoryChange;

test('history percentages keep four decimals and record signed percentage point differences', function () {
    expect(PetHistoryChange::percent('energy', 100 / 3, 25)->toArray())->toBe([
        'metric' => 'energy', 'before' => 33.3333, 'after' => 25.0, 'delta' => -8.3333, 'unit' => 'percent',
    ]);
});

test('history point changes describe the actual capped gain', function () {
    expect(PetHistoryChange::points('speed', 99.75, 100)->toArray())->toBe([
        'metric' => 'speed', 'before' => 99.75, 'after' => 100.0, 'delta' => 0.25, 'unit' => 'points',
    ]);
});

test('history rejects values that cannot form a numeric change', function (string $metric, float $before, float $after) {
    expect(fn () => PetHistoryChange::percent($metric, $before, $after))->toThrow(InvalidArgumentException::class);
})->with([
    'empty metric' => ['', 0.0, 1.0],
    'blank metric' => [' ', 0.0, 1.0],
    'not a number' => ['energy', NAN, 1.0],
    'infinite result' => ['energy', 0.0, INF],
    'infinite difference' => ['energy', -PHP_FLOAT_MAX, PHP_FLOAT_MAX],
]);
