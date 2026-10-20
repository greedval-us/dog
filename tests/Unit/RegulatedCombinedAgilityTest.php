<?php

use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;

function combinedAgilityRules(): array
{
    return ['version' => 3, 'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined'],
        'stages' => ['agility_opening', 'agility_technical', 'agility_finish', 'jumping_opening', 'jumping_technical', 'jumping_finish'], 'time_limit' => 360];
}

function combinedAgilitySnapshot(): array
{
    return ['version' => 1, 'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 100),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100), 'skills' => [], 'modifiers' => []];
}

test('national title preparation offers six decisions for two independent agility courses', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $regulation = combinedAgilityRules()['regulation'];
    $plan = ['sport_class' => 'agility_3', 'stages' => array_fill(0, 6, 'careful')];

    expect($rules->stages('agility', $regulation, $plan))->toBe(combinedAgilityRules()['stages']);
    expect($rules->validPlan('agility', $regulation, ['agility_level' => 3], 24, $plan))->toBeTrue();
});

test('a national combined result retains both course protocols and adds their times and faults', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $result = $simulator->simulate('agility', combinedAgilitySnapshot(), ['sport_class' => 'agility_3', 'stages' => array_fill(0, 6, 'careful')], combinedAgilityRules(), array_fill(0, 12, 0.99));

    expect($result['stages'])->toHaveCount(6);
    expect(array_keys($result['legs']))->toBe(['agility', 'jumping']);
    expect($result['time'])->toBe(round($result['legs']['agility']['time'] + $result['legs']['jumping']['time'], 2));
    expect($result['total_faults'])->toBe(0.0);
    expect($result['qualified'])->toBeTrue();
});

test('a clean agility course cannot cancel elimination in the jumping course', function () {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $result = $simulator->simulate('agility', combinedAgilitySnapshot(), ['sport_class' => 'agility_3', 'stages' => array_fill(0, 6, 'balanced')], combinedAgilityRules(), [...array_fill(0, 6, 0.99), ...array_fill(0, 6, 0.0)]);

    expect($result['legs']['agility']['qualified'])->toBeTrue();
    expect($result['legs']['jumping']['eliminated'])->toBeTrue();
    expect($result['qualified'])->toBeFalse();
    expect($result['eliminated'])->toBeTrue();
});
