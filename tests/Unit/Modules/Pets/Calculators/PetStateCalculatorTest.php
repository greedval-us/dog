<?php

use App\Modules\Pets\Calculators\PetStateCalculator;

function stateBalance(array $overrides = []): PetStateCalculator
{
    return new PetStateCalculator([...require dirname(__DIR__, 5).'/config/pet_states.php', ...$overrides]);
}

function stateValues(array $overrides = []): array
{
    return [...array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'], 100.0), ...$overrides];
}

test('elapsed time changes individual capacities including slower bond decay and energy recovery', function () {
    $values = stateValues(['energy' => 20.0]);
    $maximums = array_fill_keys(array_keys($values), 200);
    $values = array_map(fn (float $value): float => $value * 2, $values);

    expect(stateBalance()->calculate($values, $maximums, 5400))->toBe([
        'health' => 200.0, 'energy' => 55.0, 'satiety' => 185.0, 'hydration' => 185.0,
        'mood' => 194.0, 'cleanliness' => 194.0, 'bond' => 198.5,
    ]);
});

test('health falls only for time spent below either critical need without doubling the loss', function (float $satiety, float $hydration, int $seconds, float $health) {
    $values = stateValues(['satiety' => $satiety, 'hydration' => $hydration]);

    expect(stateBalance()->calculate($values, array_fill_keys(array_keys($values), 100), $seconds)['health'])->toBe($health);
})->with([
    'hungry only' => [10.0, 100.0, 3600, 95.0],
    'thirsty only' => [100.0, 10.0, 3600, 95.0],
    'both low' => [10.0, 10.0, 3600, 95.0],
    'reaches twenty at end' => [25.0, 100.0, 3600, 100.0],
    'crosses twenty midway' => [25.0, 100.0, 7200, 95.0],
    'starts at twenty and declines' => [20.0, 100.0, 3600, 95.0],
    'unrounded need is below twenty' => [19.99, 100.0, 3600, 95.0],
    'long absence floors health' => [100.0, 100.0, 360000, 0.0],
]);

test('energy requires every configured need to be strictly above fifty', function (string $state, float $value) {
    $values = stateValues(['energy' => 10.0, $state => $value]);

    expect(stateBalance()->calculate($values, array_fill_keys(array_keys($values), 100), 3600)['energy'])->toBe(10.0);
})->with(['satiety', 'hydration', 'mood', 'cleanliness', 'health'])->with([49.0, 50.0]);

test('energy stops recovering when a declining need reaches fifty', function (string $state, float $initial) {
    $values = stateValues(['energy' => 10.0, $state => $initial]);

    expect(stateBalance()->calculate($values, array_fill_keys(array_keys($values), 100), 7200)['energy'])->toBe(15.0);
})->with(['satiety' => ['satiety', 55.0], 'hydration' => ['hydration', 55.0], 'mood' => ['mood', 52.0], 'cleanliness' => ['cleanliness', 52.0]]);

test('bond does not prevent energy recovery and energy cannot exceed capacity', function () {
    $values = stateValues(['energy' => 99.0, 'bond' => 0.0]);

    expect(stateBalance()->calculate($values, array_fill_keys(array_keys($values), 100), 3600)['energy'])->toBe(100.0);
});

test('zero and negative elapsed time do not change the snapshot', function (int $seconds) {
    $values = stateValues();

    expect(stateBalance()->calculate($values, array_fill_keys(array_keys($values), 100), $seconds))->toBe($values);
})->with([0, -60]);

test('zero rates keep an exact critical threshold safe while lower values still damage health', function (float $satiety, float $health) {
    $values = stateValues(['satiety' => $satiety]);

    expect(stateBalance(['decay_per_hour' => []])->calculate($values, array_fill_keys(array_keys($values), 100), 3600)['health'])->toBe($health);
})->with(['exactly twenty' => [20.0, 100.0], 'below twenty' => [19.0, 95.0]]);

test('zero capacities never divide by zero and disable recovery', function () {
    $values = stateValues(['energy' => 10.0]);
    $maximums = [...array_fill_keys(array_keys($values), 100), 'hydration' => 0];

    expect(stateBalance()->calculate($values, $maximums, 3600))->toMatchArray(['hydration' => 0.0, 'health' => 95.0, 'energy' => 10.0]);
});

test('splitting elapsed time at a save does not repeat or lose threshold effects', function () {
    $values = stateValues(['energy' => 0.0, 'satiety' => 55.0]);
    $maximums = array_fill_keys(array_keys($values), 100);
    $calculator = stateBalance();
    $first = $calculator->calculate($values, $maximums, 1800);

    expect($calculator->calculate($first, $maximums, 30600))->toBe($calculator->calculate($values, $maximums, 32400));
});
