<?php

use App\Modules\Pets\Calculators\ProgenyRingRules;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;

test('progeny ring judges current group quality and movement rather than accumulated offspring titles', function () {
    $member = ['id' => 1, 'name' => 'Parent', 'grade' => 'excellent', 'exhibition_class' => 'open',
        'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 100),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100),
        'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80], 'modifiers' => [], 'career_experience' => 0];
    $children = array_map(static fn (int $id): array => [...$member, 'id' => $id, 'name' => 'Child '.$id, 'titles' => []], [2, 3, 4]);
    $snapshot = ['version' => 1, 'members' => [$member, ...$children], 'offspring' => $children];
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $rules = ['version' => 3, 'regulation' => ['format' => 'progeny_ring'], 'stages' => ['type', 'uniformity', 'movement']];
    $plan = ['stages' => array_fill(0, 3, 'balanced')];

    $result = $simulator->simulate('progeny', $snapshot, $plan, $rules, array_fill(0, 6, 0.99));
    $titled = [...$snapshot, 'offspring' => array_map(static fn (array $child): array => [...$child, 'titles' => array_fill(0, 20, ['code' => 'CAC'])], $children)];

    expect($simulator->simulate('progeny', $titled, $plan, $rules, array_fill(0, 6, 0.99))['score'])->toBe($result['score']);
    expect($result['format'])->toBe('progeny_ring');
    expect($result['members'])->toHaveCount(4);
    expect(array_column($result['stages'], 'key'))->toBe(['type', 'uniformity', 'movement']);
    expect($result['preparation']['careMultiplier'])->toBe(1.0);
    $neglected = [...$snapshot, 'members' => array_map(static fn (array $dog): array => [...$dog, 'states' => array_fill_keys(array_keys($dog['states']), 60)], $snapshot['members'])];
    expect($simulator->simulate('progeny', $neglected, $plan, $rules, array_fill(0, 6, 0.99))['score'])->toBeLessThan($result['score']);
});

test('group uniformity compares each inherited trait rather than hiding unlike dogs behind equal averages', function () {
    $rules = new ProgenyRingRules;
    $uniform = array_fill(0, 3, ['exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80]]);
    $different = [
        ['exterior' => ['type' => 60, 'structure' => 100, 'movement' => 80]],
        ['exterior' => ['type' => 100, 'structure' => 60, 'movement' => 80]],
        ['exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80]],
    ];

    expect($rules->criteria($uniform)['uniformity'])->toBe(100.0);
    expect($rules->criteria($different)['uniformity'])->toBeLessThan(100.0);
    expect($rules->criteria($different)['type'])->toBe($rules->criteria($uniform)['type']);
});

test('only the best group of each breed reaches the shared final and receives its separate group awards', function () {
    $protocol = [];
    foreach ([[1, 7, 99], [2, 7, 98], [3, 8, 90], [4, 9, 85], [5, 10, 80]] as [$id, $breed, $score]) {
        $protocol[] = ['id' => $id, 'division' => 'progeny:breed-'.$breed, 'snapshot' => ['breed_id' => $breed],
            'result' => ['score' => $score, 'qualified' => true]];
    }

    $result = (new ProgenyRingRules)->placements($protocol);

    expect($result['rankings'][1])->toBe(['breed' => 1, 'final' => 1]);
    expect($result['rankings'][2])->toBe(['breed' => 2, 'final' => null]);
    expect($result['rankings'][3]['final'])->toBe(2);
    expect($result['rankings'][4]['final'])->toBe(3);
    expect($result['rankings'][5]['final'])->toBeNull();
    expect(array_column($result['awards'], 'code'))->toContain('BEST_PROGENY_GROUP', 'PROGENY_PLACE_1', 'PROGENY_PLACE_2', 'PROGENY_PLACE_3');
    expect(array_filter($result['awards'], static fn (array $award): bool => $award['entry_id'] === 2))->toBe([]);
    expect(array_unique(array_column($result['awards'], 'kind')))->not->toContain('certificate', 'championship');
});

test('group admission distinguishes pending individual entries and national judging requirements', function (string $system, string $status, string $class, ?string $grade, bool $final, ?string $reason) {
    expect((new ProgenyRingRules)->memberReason(['status' => $status, 'class' => $class, 'grade' => $grade], $system, $final))->toBe($reason);
})->with([
    'RKF pending adult' => ['rkf', 'registered', 'open', null, false, null],
    'RKF pending not final admission' => ['rkf', 'registered', 'open', null, true, 'events.errors.progeny_entry'],
    'RKF cancelled entry' => ['rkf', 'cancelled', 'open', null, false, 'events.errors.progeny_entry'],
    'RKF baby excluded' => ['rkf', 'registered', 'baby', null, false, 'events.errors.progeny_class'],
    'RKF puppy excluded' => ['rkf', 'completed', 'puppy', 'very_promising', true, 'events.errors.progeny_class'],
    'RKF good insufficient' => ['rkf', 'completed', 'open', 'good', true, 'events.errors.progeny_grade'],
    'RKF very good admitted' => ['rkf', 'completed', 'junior', 'very_good', true, null],
    'RKF excellent admitted' => ['rkf', 'completed', 'veteran', 'excellent', true, null],
    'FCI pending puppy' => ['fci', 'registered', 'puppy', null, false, null],
    'FCI good admitted' => ['fci', 'completed', 'open', 'good', true, null],
    'FCI judged puppy admitted' => ['fci', 'completed', 'puppy', 'promising', true, null],
    'FCI disqualified excluded' => ['fci', 'completed', 'open', 'disqualified', true, 'events.errors.progeny_judging'],
    'FCI missing assessment excluded' => ['fci', 'completed', 'open', null, true, 'events.errors.progeny_judging'],
]);
