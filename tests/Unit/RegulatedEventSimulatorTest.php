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

test('discipline career experience reduces regulated mistakes without changing inherited quality', function (string $discipline, array $classification, array $stages) {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = regulatedSimulationSnapshot();
    $plan = [...$classification, 'stages' => array_fill(0, count($stages), 'balanced')];
    $rules = ['version' => 3, 'regulation' => ['format' => $discipline], 'stages' => $stages];
    $draws = array_fill(0, count($stages) * 2, 0.99);
    $draws[0] = 0.16;

    $novice = $simulator->simulate($discipline, $snapshot, $plan, $rules, $draws);
    $experienced = $simulator->simulate($discipline, [...$snapshot, 'career_experience' => 80], $plan, $rules, $draws);

    expect($novice['stages'][0]['penalties'])->toBe(1);
    expect($experienced['stages'][0]['penalties'])->toBe(0);
    expect($experienced['stages'][0]['factors']['mistakeChance'])->toBeLessThan($novice['stages'][0]['factors']['mistakeChance']);
    expect($experienced['stages'][0]['factors']['quality'])->toBe($novice['stages'][0]['factors']['quality']);
    expect($experienced['preparation']['consistencyBonus'] ?? null)->toBe(0.08);
})->with([
    'agility' => ['agility', ['sport_class' => 'agility_1'], ['opening', 'technical', 'finish']],
    'nosework' => ['nosework', ['sport_class' => 'nw_0', 'program' => 'pr1'], ['interior', 'exterior', 'objects', 'transport']],
    'canicross' => ['canicross', ['sport_class' => 'canicross_open'], ['start', 'distance', 'finish']],
    'conformation' => ['conformation', ['exhibition_class' => 'open'], ['type', 'structure', 'movement']],
]);

test('regulated career consistency has a bounded effect and defaults safely for old snapshots', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = regulatedSimulationSnapshot();
    $rules = ['version' => 3, 'stages' => ['opening', 'technical', 'finish']];
    $plan = ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced')];
    $run = fn (array $changes): array => $simulator->simulate('agility', [...$snapshot, ...$changes], $plan, $rules, array_fill(0, 6, 0.99));

    $old = $run([]);
    $capped = $run(['career_experience' => 80]);

    expect($old['preparation']['consistencyBonus'] ?? null)->toBe(0.0);
    expect($run(['career_experience' => -10]))->toBe($run(['career_experience' => 0]));
    expect($run(['career_experience' => 1000000])['stages'])->toBe($capped['stages']);
    expect($run(['career_experience' => 40])['preparation']['consistencyBonus'] ?? null)->toBe(0.04);
    expect($capped['time'])->toBe($old['time']);
    expect($run(['career_experience' => 80]))->toBe($capped);
});

test('agility uses saved warm up effects and ignores performance gear and unverified aggregate modifiers', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = regulatedSimulationSnapshot();
    $snapshot['states']['bond'] = 50;
    $snapshot['states']['mood'] = 50;
    $rules = ['version' => 3, 'stages' => ['opening', 'technical', 'finish']];
    $plan = ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced')];
    $draws = array_fill(0, 6, 0.99);
    $bare = $simulator->simulate('agility', $snapshot, $plan, $rules, $draws);
    $gear = [
        ['phase' => 'preparation', 'modifiers' => ['focus' => 0.1, 'precision' => 0.05]],
        ['phase' => 'preparation', 'modifiers' => ['pace' => 0.04, 'stamina' => -0.02]],
        ['phase' => 'performance', 'modifiers' => ['focus' => 0.2, 'precision' => 0.2, 'pace' => 0.2]],
    ];

    $equipped = $simulator->simulate('agility', [...$snapshot, 'gear' => $gear, 'modifiers' => ['focus' => -0.2]], $plan, $rules, $draws);

    expect($equipped['preparation']['modifiers'])->toBe(['precision' => 0.05, 'stamina' => -0.02, 'pace' => 0.04, 'focus' => 0.1]);
    expect($equipped['preparation']['initialFocus'])->toBeGreaterThan($bare['preparation']['initialFocus']);
    expect($equipped['stages'][0]['factors']['mistakeChance'])->toBeLessThan($bare['stages'][0]['factors']['mistakeChance']);
    expect($equipped['stages'][0]['time'])->toBeLessThan($bare['stages'][0]['time']);
    expect($equipped['stages'][1]['fatigue'])->toBeGreaterThan($bare['stages'][1]['fatigue']);
    expect($simulator->simulate('agility', [...$snapshot, 'gear' => array_slice($gear, 0, 2)], $plan, $rules, $draws))->toBe($equipped);
});

test('agility caps the sum of warm up effects after combining positive and negative items', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = [...regulatedSimulationSnapshot(), 'gear' => array_fill(0, 4,
        ['phase' => 'preparation', 'modifiers' => ['focus' => 0.08, 'precision' => -0.06]])];

    $result = $simulator->simulate('agility', $snapshot, ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced')],
        ['version' => 3, 'stages' => ['opening', 'technical', 'finish']], array_fill(0, 6, 0.99));

    expect($result['preparation']['modifiers'])->toBe(['precision' => -0.2, 'stamina' => 0.0, 'pace' => 0.0, 'focus' => 0.2]);
});

test('career consistency cannot remove the minimum chance of a mistake', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = [...regulatedSimulationSnapshot(), 'career_experience' => 80,
        'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 1000000000)];

    $result = $simulator->simulate('agility', $snapshot, ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced')],
        ['version' => 3, 'stages' => ['opening', 'technical', 'finish']], array_fill(0, 6, 0.99));

    expect($result['stages'][0]['factors']['mistakeChance'])->toBe(0.0278);
});

test('documentary progeny judging does not gain a handling bonus from the parents career experience', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = [...regulatedSimulationSnapshot(), 'career_experience' => 80, 'offspring' => array_fill(0, 3,
        ['exterior' => ['type' => 85], 'titles' => []])];
    $plan = ['stages' => array_fill(0, 3, 'balanced')];
    $rules = ['version' => 3, 'stages' => ['type', 'uniformity', 'achievements']];

    $result = $simulator->simulate('progeny', $snapshot, $plan, $rules, array_fill(0, 6, 0.1));
    $novice = $simulator->simulate('progeny', [...$snapshot, 'career_experience' => 0], $plan, $rules, array_fill(0, 6, 0.1));

    expect($result['preparation']['consistencyBonus'])->toBe(0.0);
    expect($result['stages'])->toBe($novice['stages']);
    expect($result['score'])->toBe($novice['score']);
});
