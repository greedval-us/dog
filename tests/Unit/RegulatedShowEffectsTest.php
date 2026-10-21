<?php

use App\Modules\Pets\Calculators\RegulatedEventSimulator;

function showEffectSnapshot(): array
{
    return ['version' => 1, 'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 100),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100),
        'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80], 'skills' => [], 'modifiers' => [], 'career_experience' => 0];
}

test('show pace changes only the movement presentation contribution and preserves inherited exterior', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = showEffectSnapshot();
    $rules = ['version' => 3, 'stages' => ['type', 'structure', 'movement']];
    $plan = ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')];
    $run = fn (float $pace): array => $simulator->simulate('conformation', [...$snapshot, 'modifiers' => ['pace' => $pace]], $plan, $rules, array_fill(0, 6, 0.99));

    $plain = $run(0.0);
    $freerMovement = $run(0.1);
    $restrainedMovement = $run(-0.1);

    expect($freerMovement['score'])->toBeGreaterThan($plain['score']);
    expect($restrainedMovement['score'])->toBeLessThan($plain['score']);
    foreach ([0, 1] as $stage) {
        expect($freerMovement['stages'][$stage]['score'])->toBe($plain['stages'][$stage]['score']);
    }
    expect(array_column(array_column($freerMovement['stages'], 'factors'), 'exteriorContribution'))->toBe([64.0, 64.0, 64.0]);
    expect($freerMovement['stages'][2]['factors']['presentationContribution'])->toBeLessThanOrEqual(20.0);
    expect($freerMovement['stages'][2]['factors']['presentationMultiplier'] ?? null)->toBe(1.1);
});

test('bright show presentation can beat cautious handling on a clean run but lose when its extra risk produces a mistake', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $snapshot = showEffectSnapshot();
    $rules = ['version' => 3, 'stages' => ['movement']];
    $run = fn (string $decision, float $draw): array => $simulator->simulate('conformation', $snapshot,
        ['exhibition_class' => 'open', 'stages' => [$decision]], $rules, [$draw, 0.99]);

    $cleanBold = $run('bold', 0.99);
    $cleanBalanced = $run('balanced', 0.99);
    $cleanCareful = $run('careful', 0.99);
    $faultedBold = $run('bold', 0.2);

    expect($cleanBold['score'])->toBeGreaterThan($cleanBalanced['score']);
    expect($cleanBalanced['score'])->toBeGreaterThan($cleanCareful['score']);
    expect($faultedBold['score'])->toBeLessThan($run('careful', 0.2)['score']);
    expect($faultedBold['stages'][0]['penalties'])->toBe(1);
    expect($cleanBold['stages'][0]['factors']['exteriorContribution'])->toBe(64.0);
    expect($cleanBold['stages'][0]['factors']['presentationContribution'])->toBe(20.0);
});

test('a saved show presentation policy is replayed independently of the current balance', function () {
    $balance = require dirname(__DIR__, 2).'/config/event_regulations.php';
    $balance['simulation']['show_presentation'] = ['careful' => 0.5, 'balanced' => 0.5, 'bold' => 0.5];
    $simulator = new RegulatedEventSimulator($balance);
    $snapshot = [...showEffectSnapshot(), 'show_presentation_multipliers' => ['careful' => 0.9, 'balanced' => 1.0, 'bold' => 1.15]];

    $result = $simulator->simulate('conformation', $snapshot, ['exhibition_class' => 'open', 'stages' => ['bold']],
        ['version' => 3, 'stages' => ['movement']], [0.99, 0.99]);

    expect($result['stages'][0]['factors']['presentationMultiplier'] ?? null)->toBe(1.15);
    expect($result['stages'][0]['factors']['presentationContribution'])->toBe(20.0);
});
