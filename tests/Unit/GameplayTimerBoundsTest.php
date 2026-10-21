<?php

use App\Modules\Pets\Calculators\DogWorkRules;
use App\Modules\Pets\Calculators\TrainingRules;

test('new training rejects activity durations outside the five to fifteen second range', function (int $duration, int $cooldown, bool $valid) {
    expect((new TrainingRules)->valid(['speed' => 4], ['satiety' => 5], 10, $duration, $cooldown))->toBe($valid);
})->with([
    'duration below minimum' => [4, 10, false],
    'duration at minimum' => [5, 10, true],
    'duration at maximum' => [15, 10, true],
    'duration above maximum' => [16, 10, false],
    'cooldown below minimum' => [10, 4, false],
    'cooldown at minimum' => [10, 5, true],
    'cooldown at maximum' => [10, 15, true],
    'cooldown above maximum' => [10, 16, false],
]);

test('new dog work rejects activity durations outside the five to fifteen second range', function (int $duration, bool $valid) {
    expect((new DogWorkRules)->valid(1, 100, 0, $duration, 10, 20))->toBe($valid);
})->with([
    'duration below minimum' => [4, false],
    'duration at minimum' => [5, true],
    'duration at maximum' => [15, true],
    'duration above maximum' => [16, false],
]);
