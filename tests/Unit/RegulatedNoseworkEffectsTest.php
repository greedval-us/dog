<?php

use App\Modules\Pets\Calculators\RegulatedEventSimulator;

/**
 * @param  array<string, mixed>  $snapshotChanges
 * @param  list<string>  $stageKeys
 * @return array<string, mixed>
 */
function noseworkEffectsResult(float $baseSeconds, string $class = 'nw_0', string $format = 'nosework', array $snapshotChanges = [], array $stageKeys = ['interior']): array
{
    $balance = require dirname(__DIR__, 2).'/config/event_regulations.php';
    $balance['simulation']['base_stage_seconds'] = $baseSeconds;
    $snapshot = [
        'version' => 1,
        'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 0),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100),
        'skills' => [], 'modifiers' => [],
        ...$snapshotChanges,
    ];

    return (new RegulatedEventSimulator($balance))->simulate('nosework', $snapshot, [
        'sport_class' => $class, 'program' => 'pr1', 'stages' => array_fill(0, count($stageKeys), 'balanced'),
    ], [
        'version' => 3, 'regulation' => ['format' => $format, 'rank' => 'qualifying'], 'stages' => $stageKeys,
    ], array_fill(0, count($stageKeys) * 2, 0.99));
}

dataset('nosework search deadlines', [
    'odor recognition test' => ['nw_test', 'odor_test', 120],
    'class zero' => ['nw_0', 'nosework', 120],
    'class one' => ['nw_1', 'nosework', 240],
    'class two' => ['nw_2', 'nosework', 360],
    'class three' => ['nw_3', 'nosework', 480],
    'veterans' => ['nw_v', 'nosework', 240],
]);

test('a clear nosework search qualifies exactly at its class or test deadline', function (string $class, string $format, int $timeLimit) {
    $result = noseworkEffectsResult($timeLimit, $class, $format);
    $stage = $result['stages'][0];

    expect($stage['time'])->toBe((float) $timeLimit);
    expect($stage['timeLimit'] ?? null)->toEqual($timeLimit);
    expect($stage['timedOut'] ?? null)->toBeFalse();
    expect($stage['score'])->toBe(100.0);
    expect($stage['qualified'])->toBeTrue();
    expect($result['qualified'])->toBeTrue();
})->with('nosework search deadlines');

test('a clear nosework search loses category qualification immediately after its deadline', function (string $class, string $format, int $timeLimit) {
    $result = noseworkEffectsResult($timeLimit + 0.01, $class, $format);
    $stage = $result['stages'][0];

    expect($stage['time'])->toBe($timeLimit + 0.01);
    expect($stage['score'])->toBe(0.0);
    expect($stage['qualified'])->toBeFalse();
    expect($result['qualified'])->toBeFalse();
    expect($stage['timeLimit'] ?? null)->toEqual($timeLimit);
    expect($stage['timedOut'] ?? null)->toBeTrue();
    expect($stage['note'])->toBe('timeout');
    expect($result['eliminated'])->toBeFalse();
    expect($result['qualified_categories'])->toBe(0);
    expect($stage['found'])->toBe($stage['hides']);
    expect($stage['false_alerts'])->toBe(0);
    expect($stage['penalties'])->toBe(0);
})->with('nosework search deadlines');

test('the nosework deadline applies to each category rather than the sum of search times', function () {
    $result = noseworkEffectsResult(110, stageKeys: ['interior', 'exterior']);

    expect($result['time'])->toBe(225.2);
    expect($result['qualified_categories'])->toBe(2);
    expect($result['qualified'])->toBeTrue();
    expect(array_column($result['stages'], 'timedOut'))->toBe([false, false]);
});

test('a timed out nosework category preserves qualification in the other category', function () {
    $result = noseworkEffectsResult(118, stageKeys: ['interior', 'exterior']);

    expect($result['stages'][0]['time'])->toBe(118.0);
    expect($result['stages'][0]['score'])->toBe(100.0);
    expect($result['stages'][0]['qualified'])->toBeTrue();
    expect($result['stages'][1]['time'])->toBe(123.2);
    expect($result['stages'][1]['score'])->toBe(0.0);
    expect($result['stages'][1]['note'])->toBe('timeout');
    expect($result['stages'][1]['qualified'])->toBeFalse();
    expect($result['qualified_categories'])->toBe(1);
    expect($result['qualified'])->toBeFalse();
});

test('nosework pace equipment can cross the search deadline without changing physical finds', function (string $class, string $format) {
    $fast = noseworkEffectsResult(120, $class, $format, ['modifiers' => ['pace' => 0.1]]);
    $slow = noseworkEffectsResult(120, $class, $format, ['modifiers' => ['pace' => -0.1]]);

    expect($fast['stages'][0]['time'])->toBe(109.09);
    expect($fast['qualified'])->toBeTrue();
    expect($slow['stages'][0]['time'])->toBe(133.33);
    expect($slow['stages'][0]['score'])->toBe(0.0);
    expect($slow['qualified'])->toBeFalse();
    expect($fast['stages'][0]['timedOut'] ?? null)->toBeFalse();
    expect($slow['stages'][0]['timedOut'] ?? null)->toBeTrue();
    expect($slow['stages'][0]['found'])->toBe($fast['stages'][0]['found']);
    expect($slow['stages'][0]['false_alerts'])->toBe($fast['stages'][0]['false_alerts']);
})->with([
    'odor recognition test' => ['nw_test', 'odor_test'],
    'class zero category' => ['nw_0', 'nosework'],
]);

test('a frozen nosework deadline takes precedence over the current class limit', function () {
    $frozen = noseworkEffectsResult(180, snapshotChanges: ['nosework_time_limit' => 240]);
    $legacy = noseworkEffectsResult(180);

    expect($frozen['qualified'])->toBeTrue();
    expect($legacy['qualified'])->toBeFalse();
    expect($frozen['stages'][0]['timeLimit'] ?? null)->toEqual(240);
    expect($frozen['stages'][0]['timedOut'] ?? null)->toBeFalse();
    expect($legacy['stages'][0]['timeLimit'] ?? null)->toEqual(120);
    expect($legacy['stages'][0]['timedOut'] ?? null)->toBeTrue();
});
