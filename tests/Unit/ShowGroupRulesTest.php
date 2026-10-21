<?php

use App\Modules\Pets\Calculators\ProgenyRingRules;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;
use App\Modules\Pets\Calculators\ShowGroupRules;

function showGroupMember(int $id, array $changes = []): array
{
    return array_replace(['id' => $id, 'breed_id' => 7, 'sex' => $id === 1 ? 'male' : 'female',
        'owner_id' => 11, 'breeder_key' => 'breeder:11', 'kennel_key' => 'kennel:11',
        'name' => 'Dog '.$id, 'grade' => 'excellent', 'exhibition_class' => 'open',
        'stats' => array_fill_keys(['speed', 'strength', 'agility', 'endurance', 'obedience', 'intelligence'], 100),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'bond', 'mood'], 100),
        'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80], 'modifiers' => [], 'career_experience' => 0], $changes);
}

test('a couple requires exactly one male and female of the same breed and owner', function (array $members, ?string $reason) {
    expect((new ShowGroupRules)->compositionReason('couple', $members))->toBe($reason);
})->with([
    'valid couple' => [[showGroupMember(1), showGroupMember(2)], null],
    'one dog' => [[showGroupMember(1)], 'events.errors.group_members'],
    'three dogs' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3)], 'events.errors.group_members'],
    'same dog twice' => [[showGroupMember(1), showGroupMember(1)], 'events.errors.group_members'],
    'same sex' => [[showGroupMember(1), showGroupMember(2, ['sex' => 'male'])], 'events.errors.couple'],
    'different breed' => [[showGroupMember(1), showGroupMember(2, ['breed_id' => 8])], 'events.errors.group_members'],
    'different owner' => [[showGroupMember(1), showGroupMember(2, ['owner_id' => 12])], 'events.errors.couple'],
    'unknown owner' => [[showGroupMember(1, ['owner_id' => null]), showGroupMember(2, ['owner_id' => null])], 'events.errors.couple'],
]);

test('collective candidate choices use the same composition rules before the full group is selected', function (string $discipline, array $candidate, ?string $reason) {
    expect((new ShowGroupRules)->candidateReason($discipline, showGroupMember(1), $candidate))->toBe($reason);
})->with([
    'compatible couple' => ['couple', showGroupMember(2), null],
    'candidate repeats anchor' => ['couple', showGroupMember(1), 'events.errors.group_members'],
    'couple candidate is same sex' => ['couple', showGroupMember(2, ['sex' => 'male']), 'events.errors.couple'],
    'couple candidate has another owner' => ['couple', showGroupMember(2, ['owner_id' => 12]), 'events.errors.couple'],
    'couple candidate has another breed' => ['couple', showGroupMember(2, ['breed_id' => 8]), 'events.errors.group_members'],
    'breeders candidate may have another owner' => ['breeders', showGroupMember(2, ['owner_id' => 12]), null],
    'breeders candidate has another kennel' => ['breeders', showGroupMember(2, ['kennel_key' => 'kennel:12']), 'events.errors.kennel_origin'],
    'breeders candidate has unknown origin' => ['breeders', showGroupMember(2, ['breeder_key' => null]), 'events.errors.kennel_origin'],
]);

test('a breeders group proves its common breeder and kennel independently of current owners', function (array $members, ?string $reason) {
    expect((new ShowGroupRules)->compositionReason('breeders', $members))->toBe($reason);
})->with([
    'different owners and one sex' => [[showGroupMember(1, ['sex' => 'female']), showGroupMember(2, ['owner_id' => 12]), showGroupMember(3, ['owner_id' => 13])], null],
    'five dogs' => [array_map(showGroupMember(...), [1, 2, 3, 4, 5]), null],
    'only two' => [[showGroupMember(1), showGroupMember(2)], 'events.errors.group_members'],
    'six dogs' => [array_map(showGroupMember(...), [1, 2, 3, 4, 5, 6]), 'events.errors.group_members'],
    'duplicate member' => [[showGroupMember(1), showGroupMember(2), showGroupMember(2)], 'events.errors.group_members'],
    'mixed breeds' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3, ['breed_id' => 8])], 'events.errors.group_members'],
    'mixed breeders' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3, ['breeder_key' => 'breeder:12'])], 'events.errors.kennel_origin'],
    'mixed kennels' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3, ['kennel_key' => 'kennel:12'])], 'events.errors.kennel_origin'],
    'unknown breeder' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3, ['breeder_key' => null])], 'events.errors.kennel_origin'],
    'empty kennel' => [[showGroupMember(1), showGroupMember(2), showGroupMember(3, ['kennel_key' => ''])], 'events.errors.kennel_origin'],
]);

test('group judging keeps national grade and puppy restrictions separate from FCI', function (array $member, string $system, ?string $reason) {
    expect((new ShowGroupRules)->memberReason($member, $system, true))->toBe($reason);
})->with([
    'RKF sufficient adult' => [['status' => 'completed', 'class' => 'junior', 'grade' => 'very_good'], 'rkf', null],
    'RKF insufficient adult' => [['status' => 'completed', 'class' => 'open', 'grade' => 'good'], 'rkf', 'events.errors.group_grade'],
    'RKF puppy' => [['status' => 'completed', 'class' => 'puppy', 'grade' => 'very_promising'], 'rkf', 'events.errors.group_class'],
    'FCI puppy' => [['status' => 'completed', 'class' => 'puppy', 'grade' => 'promising'], 'fci', null],
    'FCI good adult' => [['status' => 'completed', 'class' => 'open', 'grade' => 'good'], 'fci', null],
    'FCI disqualified' => [['status' => 'completed', 'class' => 'open', 'grade' => 'disqualified'], 'fci', 'events.errors.group_judging'],
]);

test('only a breed winner reaches the collective final and group awards cannot become certificates', function (string $format, string $prefix, string $winner) {
    $entries = [];
    foreach ([[1, 7, 99], [2, 7, 98], [3, 8, 90], [4, 9, 85], [5, 10, 80]] as [$id, $breed, $score]) {
        $entries[] = ['id' => $id, 'snapshot' => ['breed_id' => $breed], 'result' => ['qualified' => true, 'score' => $score]];
    }
    $entries[] = ['id' => 6, 'snapshot' => ['breed_id' => 11], 'result' => ['qualified' => false, 'score' => 100]];

    $placements = (new ShowGroupRules)->placements($entries, $format);

    expect($placements['rankings'])->toBe([
        1 => ['breed' => 1, 'final' => 1], 2 => ['breed' => 2, 'final' => null],
        3 => ['breed' => 1, 'final' => 2], 4 => ['breed' => 1, 'final' => 3], 5 => ['breed' => 1, 'final' => null],
    ]);
    expect(array_column($placements['awards'], 'code'))->toBe([
        $prefix.'_BREED_WINNER', $prefix.'_BREED_WINNER', $prefix.'_BREED_WINNER', $prefix.'_BREED_WINNER',
        $prefix.'_PLACE_1', $winner, $prefix.'_PLACE_2', $prefix.'_PLACE_3',
    ]);
    expect(array_unique(array_column($placements['awards'], 'kind')))->not->toContain('certificate', 'championship');
})->with([
    'couples' => ['couple_ring', 'COUPLE', 'BEST_COUPLE'],
    'breeders groups' => ['breeders_ring', 'BREEDERS', 'BEST_BREEDERS_GROUP'],
]);

test('new collective formats judge every dog and ignore accumulated titles', function (string $discipline, int $size) {
    $members = array_map(showGroupMember(...), range(1, $size));
    $snapshot = ['version' => 1, 'members' => $members];
    $format = $discipline.'_ring';
    $rules = ['version' => 3, 'regulation' => ['format' => $format], 'stages' => ['type', 'uniformity', 'movement']];
    $plan = ['stages' => array_fill(0, 3, 'balanced')];
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');

    $result = $simulator->simulate($discipline, $snapshot, $plan, $rules, array_fill(0, 6, 0.99));
    $weaker = $snapshot;
    $weaker['members'][0]['exterior'] = ['type' => 40, 'structure' => 40, 'movement' => 40];
    $titled = [...$snapshot, 'members' => array_map(static fn (array $member): array => [...$member, 'titles' => array_fill(0, 10, ['code' => 'CAC'])], $members)];

    expect($result['format'])->toBe($format);
    expect($result['grade'])->toBe($format);
    expect($result['members'])->toHaveCount($size);
    expect(array_column($result['stages'], 'key'))->toBe(['type', 'uniformity', 'movement']);
    expect($simulator->simulate($discipline, $weaker, $plan, $rules, array_fill(0, 6, 0.99))['score'])->toBeLessThan($result['score']);
    expect($simulator->simulate($discipline, $titled, $plan, $rules, array_fill(0, 6, 0.99))['score'])->toBe($result['score']);
    expect($simulator->compareResults($discipline, $result, [...$result, 'score' => $result['score'] - 1]))->toBeLessThan(0);
})->with(['couple' => ['couple', 2], 'breeders' => ['breeders', 3]]);

test('collective simulation rejects invalid member counts and unbalanced decisions', function (string $discipline, int $size, array $stages) {
    $simulator = new RegulatedEventSimulator(require dirname(__DIR__, 2).'/config/event_regulations.php');

    expect(fn () => $simulator->simulate($discipline, ['version' => 1, 'members' => array_map(showGroupMember(...), range(1, $size))],
        ['stages' => $stages], ['version' => 3, 'regulation' => ['format' => $discipline.'_ring']], array_fill(0, 6, 0.99)))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'one dog cannot form a couple' => ['couple', 1, ['balanced', 'balanced', 'balanced']],
    'three dogs cannot form a couple' => ['couple', 3, ['balanced', 'balanced', 'balanced']],
    'two dogs cannot form a breeders group' => ['breeders', 2, ['balanced', 'balanced', 'balanced']],
    'six dogs cannot form a breeders group' => ['breeders', 6, ['balanced', 'balanced', 'balanced']],
    'bold couple decision' => ['couple', 2, ['bold', 'balanced', 'balanced']],
]);

test('collective plans select companions and enforce national age and balanced presentation', function (string $discipline, string $system, int $age, array $companions, array $stages, bool $valid) {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = ['id' => 1, 'breed_id' => 7, 'recognized_rkf' => true, 'recognized_fci' => true, 'registered' => true];

    expect($rules->validPlan($discipline, ['system' => $system, 'format' => $discipline.'_ring'], $profile, $age,
        ['member_ids' => $companions, 'stages' => $stages]))->toBe($valid);
})->with([
    'RKF junior couple' => ['couple', 'rkf', 9, [2], ['balanced', 'balanced', 'balanced'], true],
    'FCI baby couple' => ['couple', 'fci', 3, [2], ['balanced', 'balanced', 'balanced'], true],
    'RKF puppy couple' => ['couple', 'rkf', 8, [2], ['balanced', 'balanced', 'balanced'], false],
    'missing couple companion' => ['couple', 'rkf', 24, [], ['balanced', 'balanced', 'balanced'], false],
    'extra couple companion' => ['couple', 'rkf', 24, [2, 3], ['balanced', 'balanced', 'balanced'], false],
    'anchor repeated' => ['couple', 'rkf', 24, [1], ['balanced', 'balanced', 'balanced'], false],
    'three breeders' => ['breeders', 'rkf', 24, [2, 3], ['balanced', 'balanced', 'balanced'], true],
    'five breeders' => ['breeders', 'rkf', 24, [2, 3, 4, 5], ['balanced', 'balanced', 'balanced'], true],
    'duplicate breeder' => ['breeders', 'rkf', 24, [2, 2], ['balanced', 'balanced', 'balanced'], false],
    'six breeders' => ['breeders', 'rkf', 24, [2, 3, 4, 5, 6], ['balanced', 'balanced', 'balanced'], false],
    'string companion' => ['couple', 'rkf', 24, ['2'], ['balanced', 'balanced', 'balanced'], false],
    'bold stage prohibited' => ['couple', 'rkf', 24, [2], ['bold', 'balanced', 'balanced'], false],
]);

test('new collective options expose physical judging stages and retain their own breed division', function (string $discipline) {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $options = $rules->entryOptions($discipline, ['system' => 'rkf', 'format' => $discipline.'_ring'],
        ['breed_id' => 7, 'recognized_rkf' => true, 'registered' => true], 24);

    expect($options)->toHaveCount(1);
    expect($options[0]['key'])->toBe($discipline);
    expect($options[0]['labelKey'])->toBe('events.regulations.classes.'.$discipline);
    expect($options[0]['categoryKey'])->toBe($discipline.':breed-7');
    expect($options[0]['eligible'])->toBeTrue();
    expect($rules->stages($discipline, [], []))->toBe(['type', 'uniformity', 'movement']);
})->with(['couple', 'breeders']);

test('the group rules keep historical progeny criteria and awards unchanged', function () {
    $members = array_map(showGroupMember(...), [1, 2, 3]);
    $entries = [['id' => 1, 'snapshot' => ['breed_id' => 7], 'result' => ['qualified' => true, 'score' => 80]]];
    $historical = new ProgenyRingRules;
    $groups = new ShowGroupRules;

    expect($groups->criteria($members, 'progeny_ring'))->toBe($historical->criteria($members));
    expect($groups->placements($entries, 'progeny_ring'))->toBe($historical->placements($entries));
});
