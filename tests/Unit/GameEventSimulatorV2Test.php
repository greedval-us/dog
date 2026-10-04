<?php

use App\Modules\Pets\Calculators\GameEventPerformance;
use App\Modules\Pets\Calculators\GameEventSimulator;

function versionTwoEventSnapshot(): array
{
    return [
        'version' => 1,
        'stats' => array_fill_keys(['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'], 70),
        'states' => ['energy' => 100, 'health' => 100, 'satiety' => 100, 'hydration' => 100, 'cleanliness' => 100, 'bond' => 80, 'mood' => 80],
        'modifiers' => [], 'career_experience' => 0, 'skills' => [],
        'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80],
    ];
}

function simulateVersionTwoEvent(array $snapshot, string $discipline = 'agility', array $draws = [0.99, 0.99, 0.99, 0.99, 0.99, 0.99]): array
{
    return (new GameEventSimulator)->simulate($discipline, $snapshot, ['stages' => ['balanced', 'balanced', 'balanced']], ['version' => 2, 'stages' => ['first', 'second', 'third']], $draws);
}

test('version two records the preparation and the factors used before each stage with deterministic replay', function () {
    $snapshot = versionTwoEventSnapshot();

    $result = simulateVersionTwoEvent($snapshot);

    expect($result['version'])->toBe(2);
    expect($result['preparation']['careMultiplier'])->toBe(1.0);
    expect($result['preparation']['initialFatigue'])->toBe(0.0);
    expect($result['preparation']['initialFocus'])->toBe(70.0);
    expect($result['stages'][0]['factors'])->toBe([
        'quality' => 50.0, 'careMultiplier' => 1.0, 'mistakeChance' => 0.2764,
        'startFatigue' => 0.0, 'startFocus' => 70.0, 'exterior' => null,
        'exteriorContribution' => 0.0, 'presentationContribution' => 0.0,
    ]);
    expect($result['stages'][1]['factors']['startFatigue'])->toBe(13.0);
    expect($result['stages'][1]['factors']['startFocus'])->toBe(68.0);
    expect(simulateVersionTwoEvent($snapshot))->toBe($result);
});

test('reported stage error risk includes either independent mistake without changing the individual draw thresholds', function (string $discipline, float $risk, float $firstThreshold, float $secondThreshold) {
    $snapshot = versionTwoEventSnapshot();
    $clear = simulateVersionTwoEvent($snapshot, $discipline);
    $firstError = simulateVersionTwoEvent($snapshot, $discipline, [$firstThreshold - 0.001, 0.99, 0.99, 0.99, 0.99, 0.99]);
    $secondError = simulateVersionTwoEvent($snapshot, $discipline, [0.99, $secondThreshold - 0.001, 0.99, 0.99, 0.99, 0.99]);
    $aboveThresholds = simulateVersionTwoEvent($snapshot, $discipline, [$firstThreshold + 0.001, $secondThreshold + 0.001, 0.99, 0.99, 0.99, 0.99]);

    expect($clear['stages'][0]['factors']['mistakeChance'])->toBe($risk);
    expect($firstError['stages'][0]['penalties'])->toBe(1);
    expect($secondError['stages'][0]['penalties'])->toBe(1);
    expect($aboveThresholds['stages'][0]['penalties'])->toBe(0);
})->with([
    'agility' => ['agility', 0.2764, 0.21, 0.084],
    'canicross' => ['canicross', 0.2764, 0.21, 0.084],
    'conformation' => ['conformation', 0.2764, 0.21, 0.084],
    'nosework' => ['nosework', 0.2515, 0.0525, 0.21],
]);

test('each essential care state affects actual sporting performance', function (string $state, float $multiplier) {
    $snapshot = versionTwoEventSnapshot();
    $rested = simulateVersionTwoEvent($snapshot);
    $snapshot['states'][$state] = 0;

    $depleted = simulateVersionTwoEvent($snapshot);

    expect($depleted['preparation']['careMultiplier'])->toEqualWithDelta($multiplier, 0.00001);
    expect($depleted['stages'][0]['factors']['quality'])->toBeLessThan($rested['stages'][0]['factors']['quality']);
    expect($depleted['stages'][0]['factors']['mistakeChance'])->toBeGreaterThan($rested['stages'][0]['factors']['mistakeChance']);
    expect($depleted['time'])->toBeGreaterThan($rested['time']);
})->with([
    'health' => ['health', 0.902], 'energy' => ['energy', 0.916],
    'hydration' => ['hydration', 0.944], 'satiety' => ['satiety', 0.958],
]);

test('care penalties and equipment modifiers are bounded while statistics keep diminishing returns', function () {
    $performance = new GameEventPerformance;
    $empty = array_fill_keys(['energy', 'health', 'satiety', 'hydration'], -100);
    $overfilled = array_fill_keys(array_keys($empty), 200);

    expect($performance->careMultiplier($empty))->toBe(0.72);
    expect($performance->startingFatigue($empty))->toBe(55.0);
    expect($performance->careMultiplier($overfilled))->toBe(1.0);
    expect($performance->startingFatigue($overfilled))->toBe(0.0);
    expect($performance->normalizedStat(70))->toBe(50.0);
    expect($performance->normalizedStat(0))->toBe(0.0);
    expect($performance->normalizedStat(280) - $performance->normalizedStat(210))
        ->toBeLessThan($performance->normalizedStat(140) - $performance->normalizedStat(70));
    expect($performance->modifiers(['precision' => 100, 'stamina' => -100]))
        ->toBe(['precision' => 0.2, 'stamina' => -0.2, 'pace' => 0.0, 'focus' => 0.0]);
});

test('active scent skills improve their own stages without helping unrelated sports', function () {
    $snapshot = versionTwoEventSnapshot();
    $baseline = simulateVersionTwoEvent($snapshot, 'nosework');
    $agility = simulateVersionTwoEvent($snapshot);
    $snapshot['skills'] = ['keen_nose' => 5];

    $trained = simulateVersionTwoEvent($snapshot, 'nosework');

    expect($trained['stages'][0]['factors']['quality'])->toBe(60.0);
    expect($trained['stages'][1]['factors']['quality'])->toBe($baseline['stages'][1]['factors']['quality']);
    expect($trained['stages'][2]['factors']['quality'])->toBe(60.0);
    expect(simulateVersionTwoEvent($snapshot)['stages'])->toBe($agility['stages']);
});

test('pedigree labels and untrained genetic limits never grant free performance points', function () {
    $snapshot = versionTwoEventSnapshot();
    $ordinary = simulateVersionTwoEvent($snapshot, 'conformation');
    $sport = simulateVersionTwoEvent($snapshot);
    $snapshot['pedigree'] = ['hasAncestors' => true, 'generation' => 10, 'fatherId' => 123, 'motherId' => 456];
    $snapshot['titles'] = array_fill(0, 10, ['code' => 'champion']);
    $snapshot['potentials'] = array_fill_keys(array_keys($snapshot['stats']), 5000);

    expect(simulateVersionTwoEvent($snapshot, 'conformation'))->toBe($ordinary);
    expect(simulateVersionTwoEvent($snapshot))->toBe($sport);
});

test('conformation separates inherited qualities from current presentation and care', function () {
    $snapshot = versionTwoEventSnapshot();
    $baseline = simulateVersionTwoEvent($snapshot, 'conformation');
    $snapshot['exterior']['type'] = 90;

    $inherited = simulateVersionTwoEvent($snapshot, 'conformation');

    expect($inherited['stages'][0]['score'] - $baseline['stages'][0]['score'])->toBe(8.0);
    expect($inherited['stages'][0]['factors']['exteriorContribution'])->toBe(72.0);
    expect($inherited['stages'][0]['factors']['presentationContribution'])->toBe(18.0);
    expect($inherited['stages'][1])->toBe($baseline['stages'][1]);

    $snapshot['states']['cleanliness'] = 0;
    $ungroomed = simulateVersionTwoEvent($snapshot, 'conformation');
    expect($ungroomed['stages'][0]['factors']['presentationContribution'])->toBe(11.3333);
    expect($ungroomed['stages'][0]['factors']['exteriorContribution'])->toBe(72.0);
    expect($ungroomed['score'])->toBeLessThan($inherited['score']);
});

test('equipment changes its actual performance metric and remains capped in the simulator', function (string $metric) {
    $snapshot = versionTwoEventSnapshot();
    $baseline = simulateVersionTwoEvent($snapshot, 'canicross');
    $snapshot['modifiers'][$metric] = 0.2;

    $equipped = simulateVersionTwoEvent($snapshot, 'canicross');

    match ($metric) {
        'precision' => expect($equipped['stages'][0]['factors']['mistakeChance'])->toBeLessThan($baseline['stages'][0]['factors']['mistakeChance']),
        'stamina' => expect($equipped['stages'][2]['fatigue'])->toBeLessThan($baseline['stages'][2]['fatigue']),
        'pace' => expect($equipped['time'])->toBeLessThan($baseline['time']),
        'focus' => expect($equipped['preparation']['initialFocus'])->toBe(90.0),
    };
    $snapshot['modifiers'][$metric] = 100;
    expect(simulateVersionTwoEvent($snapshot, 'canicross'))->toBe($equipped);
})->with(['precision', 'stamina', 'pace', 'focus']);

test('exhibitions never award a tie to faster equipment and obsolete calculation versions are rejected', function (string $discipline) {
    $first = ['eliminated' => false, 'penalties' => 0, 'score' => 250.0, 'time' => 300.0];
    $faster = [...$first, 'time' => 150.0];
    $simulator = new GameEventSimulator;

    expect($simulator->compareResults($discipline, $first, $faster))->toBe(0);
    expect(fn () => $simulator->compareResults($discipline, $first, $faster, 1))->toThrow(InvalidArgumentException::class);
    expect($simulator->compareResults($discipline, $first, [...$faster, 'score' => 251.0]))->toBe(1);
})->with(['conformation', 'progeny']);

test('documentary progeny judging evaluates actual descendants independently of care tactics and equipment', function () {
    $snapshot = versionTwoEventSnapshot();
    $snapshot['offspring'] = [
        ['exterior' => ['type' => 70], 'titles' => []],
        ['exterior' => ['type' => 80], 'titles' => [['code' => 'show_winner']]],
        ['exterior' => ['type' => 90], 'titles' => []],
    ];
    $plan = ['stages' => ['bold', 'careful', 'balanced']];
    $rules = ['version' => 2, 'stages' => ['type', 'uniformity', 'achievements']];
    $draws = array_fill(0, 6, 0.0);
    $simulator = new GameEventSimulator;
    $baseline = $simulator->simulate('progeny', $snapshot, $plan, $rules, $draws);
    $snapshot['states'] = array_fill_keys(array_keys($snapshot['states']), 0);
    $snapshot['modifiers'] = ['focus' => -0.2, 'precision' => -0.2, 'pace' => -0.2, 'stamina' => -0.2];

    $current = $simulator->simulate('progeny', $snapshot, $plan, $rules, $draws);

    expect($current['score'])->toBe(175.0);
    expect($current['time'])->toBe(0.0);
    expect($current['penalties'])->toBe(0);
    expect($current['preparation']['careMultiplier'])->toBe(1.0);
    expect(array_column($current['stages'], 'score'))->toBe([80.0, 86.67, 8.33]);
    expect($current['stages'])->toBe($baseline['stages']);
});

test('version two rejects invalid simulation contracts and nonfinite draws', function (array $snapshotChanges, array $ruleChanges, array $planChanges, array $draws) {
    $snapshot = [...versionTwoEventSnapshot(), ...$snapshotChanges];
    $rules = [...['version' => 2, 'stages' => ['first', 'second', 'third']], ...$ruleChanges];
    $plan = [...['stages' => ['balanced', 'balanced', 'balanced']], ...$planChanges];

    expect(fn () => (new GameEventSimulator)->simulate('agility', $snapshot, $plan, $rules, $draws))->toThrow(InvalidArgumentException::class);
})->with([
    'future snapshot' => [['version' => 2], [], [], array_fill(0, 6, 0.5)],
    'missing states' => [['states' => null], [], [], array_fill(0, 6, 0.5)],
    'too many stages' => [[], ['stages' => ['a', 'b', 'c', 'd']], [], array_fill(0, 6, 0.5)],
    'unknown decision' => [[], [], ['stages' => ['careful', 'rush', 'bold']], array_fill(0, 6, 0.5)],
    'too few draws' => [[], [], [], [0.5]],
    'nan' => [[], [], [], array_fill(0, 6, NAN)],
    'infinite' => [[], [], [], array_fill(0, 6, INF)],
    'negative draw' => [[], [], [], array_fill(0, 6, -0.1)],
    'excess draw' => [[], [], [], array_fill(0, 6, 1.1)],
]);
