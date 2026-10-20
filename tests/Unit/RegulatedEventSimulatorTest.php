<?php

use App\Modules\Pets\Calculators\RegulatedEventSimulator;

function regulatedSimulationSnapshot(): array
{
    return ['version' => 1, 'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 100), 'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100), 'skills' => [], 'modifiers' => [], 'exterior' => ['type' => 90, 'structure' => 90, 'movement' => 90]];
}

test('one novice nosework false alert costs fifteen points without elimination', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $rules = ['version' => 3, 'regulation' => ['format' => 'nosework', 'rank' => 'qualifying'], 'stages' => ['interior', 'exterior', 'objects']];
    $plan = ['sport_class' => 'nw_0', 'program' => 'pr1', 'stages' => ['balanced', 'balanced', 'balanced']];
    $result = $simulator->simulate('nosework', regulatedSimulationSnapshot(), $plan, $rules, [0.0, 0.99, 0.99, 0.99, 0.99, 0.99]);
    expect($result['stages'][0]['score'])->toBe(85.0);
    expect($result['qualified'])->toBeTrue();
    expect($result['eliminated'])->toBeFalse();
});

test('agility runoffs resolve only a complete tie in the original run', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $first = ['qualified' => true, 'eliminated' => false, 'total_faults' => 0.0, 'time' => 40.0, 'runoff' => ['total_faults' => 5.0, 'time' => 30.0]];
    $second = ['qualified' => true, 'eliminated' => false, 'total_faults' => 0.0, 'time' => 40.0, 'runoff' => ['total_faults' => 0.0, 'time' => 35.0]];
    expect($simulator->compareResults('agility', $first, $second))->toBeGreaterThan(0);
    $first['time'] = 39.0;
    expect($simulator->compareResults('agility', $first, $second))->toBeLessThan(0);
});

test('invalid replay inputs are rejected instead of producing partial protocols', function (array $draws, array $decisions) {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    expect(fn () => $simulator->simulate('agility', regulatedSimulationSnapshot(), ['sport_class' => 'agility_1', 'stages' => $decisions], ['version' => 3, 'stages' => ['opening', 'technical', 'finish']], $draws))->toThrow(InvalidArgumentException::class);
})->with([
    'too few draws' => [[0.5], ['balanced', 'balanced', 'balanced']],
    'nan draw' => [[NAN, 0.5, 0.5, 0.5, 0.5, 0.5], ['balanced', 'balanced', 'balanced']],
    'unknown decision' => [array_fill(0, 6, 0.5), ['balanced', 'rush', 'balanced']],
]);

test('a second novice false alert zeros its category and one advanced missed hide can still qualify', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $rules = ['version' => 3, 'regulation' => ['format' => 'nosework'], 'stages' => ['interior', 'exterior', 'objects']];
    $plan = ['sport_class' => 'nw_0', 'program' => 'pr1', 'stages' => array_fill(0, 3, 'balanced')];
    $novice = $simulator->simulate('nosework', regulatedSimulationSnapshot(), $plan, $rules, [0.0, 0.0, 0.99, 0.99, 0.99, 0.99]);
    $advanced = $simulator->simulate('nosework', regulatedSimulationSnapshot(), [...$plan, 'sport_class' => 'nw_2'], $rules, [0.99, 0.0, 0.99, 0.99, 0.99, 0.99]);
    expect($novice['stages'][0]['false_alerts'])->toBe(2);
    expect($novice['stages'][0]['score'])->toBe(0.0);
    expect($advanced['stages'][0]['score'])->toBe(85.0);
    expect($advanced['qualified'])->toBeTrue();
    expect($advanced['found_all'])->toBeFalse();
});

test('advanced nosework false alerts fail the category and preserve the other qualifications', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $result = $simulator->simulate('nosework', regulatedSimulationSnapshot(), ['sport_class' => 'nw_3', 'program' => 'pr1', 'stages' => array_fill(0, 4, 'balanced')], ['version' => 3, 'regulation' => ['format' => 'nosework', 'rank' => 'certificate'], 'stages' => ['interior', 'exterior', 'objects', 'transport']], [0.0, 0.99, 0.99, 0.99, 0.99, 0.99, 0.99, 0.99]);
    expect($result['qualified'])->toBeFalse();
    expect($result['qualified_categories'])->toBe(3);
    expect($result['stages'][0]['score'])->toBe(0.0);
});

test('agility records five fault obstacles and ignores prohibited run equipment', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = regulatedSimulationSnapshot();
    $plan = ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced')];
    $rules = ['version' => 3, 'regulation' => ['format' => 'agility'], 'stages' => ['opening', 'technical', 'finish']];
    $draws = [0.0, 0.99, 0.99, 0.99, 0.99, 0.99];
    $result = $simulator->simulate('agility', $snapshot, $plan, $rules, $draws);
    $snapshot['modifiers'] = ['pace' => 0.2, 'precision' => 0.2, 'stamina' => 0.2, 'focus' => 0.2];
    expect($result['total_faults'])->toBe(5.0);
    expect($result['grade'])->toBe('excellent');
    expect($simulator->simulate('agility', $snapshot, $plan, $rules, $draws))->toBe($result);
});
