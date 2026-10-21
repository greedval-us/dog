<?php

use App\Modules\Pets\Calculators\PetBoardingRules;

test('hourly boarding purchases share scarce coins in chronological order', function (array $candidates, int $coins, int $price, array $expected) {
    $rules = new PetBoardingRules([], []);

    expect($rules->allocate($candidates, $coins, $price))->toBe($expected);
})->with([
    'two complete rounds' => [[1 => ['starts_at' => 3600, 'through' => 14400], 2 => ['starts_at' => 3600, 'through' => 14400]], 4, 1, [1 => 2, 2 => 2]],
    'partial final round follows pet order' => [[1 => ['starts_at' => 3600, 'through' => 14400], 2 => ['starts_at' => 3600, 'through' => 14400]], 5, 1, [1 => 3, 2 => 2]],
    'premium cannot borrow coins' => [[1 => ['starts_at' => 3600, 'through' => 14400], 2 => ['starts_at' => 3600, 'through' => 14400]], 5, 3, [1 => 1, 2 => 0]],
    'later dog does not lose its first hour to earlier dogs future hours' => [[1 => ['starts_at' => 3600, 'through' => 14400], 2 => ['starts_at' => 5400, 'through' => 14400]], 2, 1, [1 => 1, 2 => 1]],
    'a retirement boundary stops further purchases' => [[1 => ['starts_at' => 3600, 'through' => 7199], 2 => ['starts_at' => 3600, 'through' => 14400]], 20, 1, [1 => 1, 2 => 4]],
]);
