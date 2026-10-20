<?php

use App\Modules\Pets\Calculators\RegionalSportChampionships;

function sportProgressRules(): RegionalSportChampionships
{
    return new RegionalSportChampionships(require dirname(__DIR__, 2).'/config/sport_titles.php');
}

function sportProgressProfile(array $changes = []): array
{
    return ['registered' => true, 'recognized_rkf' => true, 'championships' => [], ...$changes];
}

function sportProgressCredential(int $id, string $code = 'REG_CACAg', array $changes = []): array
{
    return array_replace_recursive([
        'title_id' => $id, 'entry_id' => $id, 'discipline' => 'agility', 'code' => $code,
        'kind' => str_starts_with($code, 'SPORT_') ? 'championship' : 'certificate',
        'scope_key' => '', 'regulation_version' => '2', 'awarded_at' => $id,
        'judgeId' => 'judge-'.($id % 2), 'country' => 'RU', 'region' => 'moscow', 'rank' => 'certificate', 'details' => [],
    ], $changes);
}

function sportProgressGoal(array $goals, string $code, string $scope = ''): array
{
    return array_values(array_filter($goals, fn (array $goal): bool => $goal['code'] === $code && $goal['scope_key'] === $scope))[0];
}

function sportProgressPath(array $goal, string $key): array
{
    return array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === $key))[0];
}

function sportProgressRequirement(array $path, string $key): array
{
    return array_values(array_filter($path['requirements'], fn (array $requirement): bool => $requirement['key'] === $key))[0];
}

test('an empty sports career explains all national alternatives without fabricating available certificates', function () {
    $goals = sportProgressRules()->progress('agility', sportProgressProfile(), [], 100);
    $national = sportProgressGoal($goals, 'SPORT_NATIONAL_CH');

    expect(array_column($goals, 'code'))->toBe(['SPORT_REGIONAL_CH', 'SPORT_NATIONAL_CH', 'SPORT_BREED_CH']);
    expect(array_column($national['paths'], 'key'))->toBe(['national_1', 'national_2', 'national_3', 'national_4', 'national_5', 'national_6', 'national_7', 'national_8']);
    $ordinary = sportProgressPath($national, 'national_6');
    expect(sportProgressRequirement($ordinary, 'count:CACAg'))->toMatchArray(['current' => 0, 'required' => 5, 'met' => false, 'codes' => ['CACAg']]);
    expect(sportProgressRequirement($ordinary, 'judges'))->toMatchArray(['current' => 0, 'required' => 4, 'met' => false]);
    expect($ordinary['available_title_ids'])->toBe([]);
    expect($ordinary['ready'])->toBeFalse();
});

test('a regional path explains the missing certificate and requests another judge in the same subject', function () {
    $credentials = [sportProgressCredential(1, changes: ['judgeId' => 'same']), sportProgressCredential(2, changes: ['judgeId' => 'same'])];

    $goals = sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100);
    $path = sportProgressGoal($goals, 'SPORT_REGIONAL_CH', 'region:moscow')['paths'][0];

    expect(sportProgressRequirement($path, 'count:REG_CACAg'))->toMatchArray(['current' => 2, 'required' => 3, 'met' => false]);
    expect(sportProgressRequirement($path, 'judges'))->toMatchArray(['current' => 1, 'required' => 2, 'met' => false]);
    expect($path['available_title_ids'])->toBe([1, 2]);
    expect($path['targets'])->toBe([['codes' => ['REG_CACAg'], 'region' => 'moscow', 'excludeJudges' => ['same']]]);
    expect($path['proof_title_ids'])->toBe([]);
});

test('regional progress is ready only when the same exact evidence can issue a diploma', function () {
    $credentials = array_map(sportProgressCredential(...), [1, 2, 3]);

    $rules = sportProgressRules();
    $goals = $rules->progress('agility', sportProgressProfile(), $credentials, 100);
    $path = sportProgressGoal($goals, 'SPORT_REGIONAL_CH', 'region:moscow')['paths'][0];
    $award = $rules->calculate('agility', sportProgressProfile(), $credentials, 100)[0];

    expect($path['ready'])->toBeTrue();
    expect($path['proof_title_ids'])->toBe([1, 2, 3]);
    expect($path['proof_title_ids'])->toBe($award['details']['credential_title_ids']);
    expect($path['targets'])->toBe([]);
});

test('used old future and invalid regional documents are explained without crediting them', function () {
    $credentials = [
        sportProgressCredential(1),
        sportProgressCredential(2, changes: ['details' => ['used_for' => ['sport_exchange']]]),
        sportProgressCredential(3, changes: ['regulation_version' => '1']),
        sportProgressCredential(4, changes: ['awarded_at' => 101]),
        sportProgressCredential(5, changes: ['kind' => 'placement']),
    ];

    $path = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_REGIONAL_CH', 'region:moscow')['paths'][0];

    expect($path['available_title_ids'])->toBe([1]);
    expect($path['excluded'])->toBe([
        ['title_id' => 2, 'reason' => 'used'], ['title_id' => 3, 'reason' => 'ineligible'],
        ['title_id' => 4, 'reason' => 'ineligible'], ['title_id' => 5, 'reason' => 'ineligible'],
    ]);
});

test('national progress accepts distinct regional diplomas saved on one triggering entry', function () {
    $credentials = [
        sportProgressCredential(1, 'SPORT_REGIONAL_CH', ['entry_id' => 99]),
        sportProgressCredential(2, 'SPORT_REGIONAL_CH', ['entry_id' => 99, 'region' => 'tula']),
    ];

    $goal = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_NATIONAL_CH');
    $path = sportProgressPath($goal, 'national_7');

    expect($path['ready'])->toBeTrue();
    expect($path['proof_title_ids'])->toBe([1, 2]);
});

test('a second diploma from one subject still needs another subject for national progress', function () {
    $credentials = [sportProgressCredential(1, 'SPORT_REGIONAL_CH'), sportProgressCredential(2, 'SPORT_REGIONAL_CH')];

    $goal = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_NATIONAL_CH');
    $path = sportProgressPath($goal, 'national_7');

    expect($path['ready'])->toBeFalse();
    expect(sportProgressRequirement($path, 'count:SPORT_REGIONAL_CH')['met'])->toBeTrue();
    expect(sportProgressRequirement($path, 'regions'))->toMatchArray(['current' => 1, 'required' => 2, 'met' => false]);
    expect($path['targets'])->toBe([['codes' => ['SPORT_REGIONAL_CH'], 'excludeRegions' => ['moscow']]]);
});

test('marginal counts cannot claim ready progress when no compatible national proof exists', function () {
    $credentials = [
        sportProgressCredential(1, 'CACAg', ['judgeId' => 'a', 'region' => '']),
        sportProgressCredential(2, 'CACAg', ['judgeId' => 'b', 'region' => '']),
        sportProgressCredential(3, 'CACAg', ['judgeId' => 'c', 'region' => '']),
        sportProgressCredential(4, 'CACAg', ['judgeId' => 'd', 'region' => 'moscow']),
        sportProgressCredential(5, 'CACAg', ['judgeId' => 'd', 'region' => 'tula']),
        sportProgressCredential(6, 'CACAg', ['judgeId' => 'd', 'region' => 'novgorod']),
    ];

    $goal = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_NATIONAL_CH');
    $path = sportProgressPath($goal, 'national_6');

    expect($path['ready'])->toBeFalse();
    expect(sportProgressRequirement($path, 'count:CACAg')['met'])->toBeTrue();
    expect(sportProgressRequirement($path, 'judges')['met'])->toBeTrue();
    expect(sportProgressRequirement($path, 'regions')['met'])->toBeTrue();
    expect(sportProgressRequirement($path, 'compatible_evidence'))->toMatchArray(['kind' => 'compatible', 'current' => 0, 'required' => 1, 'met' => false]);
    expect($path['targets'])->toBe([['codes' => ['CACAg']]]);
});

test('registry requirements explain why a sufficient certificate set cannot grant a title', function (array $changes, string $key) {
    $credentials = array_map(sportProgressCredential(...), [1, 2, 3]);

    $path = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile($changes), $credentials, 100), 'SPORT_REGIONAL_CH', 'region:moscow')['paths'][0];

    expect($path['ready'])->toBeFalse();
    expect(sportProgressRequirement($path, $key))->toMatchArray(['kind' => 'document', 'current' => 0, 'required' => 1, 'met' => false]);
})->with(['registry' => [['registered' => false], 'registered'], 'recognition' => [['recognized_rkf' => false], 'recognized_rkf']]);

test('the next regional issue starts with fresh evidence and preserves the achieved subject', function () {
    $credentials = [
        sportProgressCredential(1),
        sportProgressCredential(2),
        sportProgressCredential(3),
        sportProgressCredential(10, 'SPORT_REGIONAL_CH', ['details' => ['proof_purpose' => 'sport_exchange', 'policy_version' => 2, 'credential_title_ids' => [1, 2, 3], 'region' => 'moscow']]),
    ];

    $goals = sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100);
    $regional = sportProgressGoal($goals, 'SPORT_REGIONAL_CH', 'region:moscow');

    expect($regional['repeatable'])->toBeTrue();
    expect($regional['achieved'])->toBeTrue();
    expect($regional['paths'][0]['available_title_ids'])->toBe([]);
    expect($regional['paths'][0]['excluded'])->toHaveCount(3);
    expect(sportProgressGoal($goals, 'SPORT_REGIONAL_CH', 'region:any')['paths'][0]['targets'])->toBe([['codes' => ['REG_CACAg'], 'excludeRegions' => ['moscow']]]);
});

test('achieved national and breed titles belong to their discipline rather than the flat profile', function () {
    $credentials = [
        sportProgressCredential(1, 'SPORT_NATIONAL_CH', ['discipline' => 'nosework']),
        sportProgressCredential(2, 'SPORT_BREED_CH'),
    ];

    $goals = sportProgressRules()->progress('agility', sportProgressProfile(['championships' => ['SPORT_NATIONAL_CH']]), $credentials, 100);

    expect(sportProgressGoal($goals, 'SPORT_NATIONAL_CH')['achieved'])->toBeFalse();
    expect(sportProgressGoal($goals, 'SPORT_BREED_CH'))->toMatchArray(['achieved' => true, 'repeatable' => false, 'paths' => []]);
});

test('a breed path counts distinct events and does not reuse two documents from one event', function () {
    $credentials = [
        sportProgressCredential(1, 'CACAg_BREED'),
        sportProgressCredential(2, 'CACAg_BREED'),
        sportProgressCredential(3, 'CACAg_BREED', ['entry_id' => 2]),
    ];

    $path = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_BREED_CH')['paths'][0];

    expect(sportProgressRequirement($path, 'count:CACAg_BREED'))->toMatchArray(['current' => 2, 'required' => 3, 'met' => false]);
    expect($path['ready'])->toBeFalse();
});

test('old direct diplomas remain valid national proof but a fake certificate kind does not', function () {
    $credentials = [
        sportProgressCredential(1, 'SPORT_CUP_RUS', ['regulation_version' => '1']),
        sportProgressCredential(2, 'SPORT_CUP_RUS', ['regulation_version' => '1']),
        sportProgressCredential(3, 'SPORT_CH_RKF', ['kind' => 'certificate']),
    ];

    $goal = sportProgressGoal(sportProgressRules()->progress('agility', sportProgressProfile(), $credentials, 100), 'SPORT_NATIONAL_CH');

    expect(sportProgressPath($goal, 'national_2')['ready'])->toBeTrue();
    expect(sportProgressPath($goal, 'national_4')['excluded'])->toBe([['title_id' => 3, 'reason' => 'ineligible']]);
});

test('the shared progress contract uses the discipline specific certificate code', function (string $discipline, string $certificate) {
    $credentials = [sportProgressCredential(1, $certificate.'_BREED', ['discipline' => $discipline])];

    $path = sportProgressGoal(sportProgressRules()->progress($discipline, sportProgressProfile(), $credentials, 100), 'SPORT_BREED_CH')['paths'][0];

    expect(sportProgressRequirement($path, 'count:'.$certificate.'_BREED'))->toMatchArray(['codes' => [$certificate.'_BREED'], 'current' => 1]);
})->with(['nosework' => ['nosework', 'CACNW'], 'canicross' => ['canicross', 'CACT']]);

test('a documentary or unknown discipline has no sports title targets', function (string $discipline) {
    expect(sportProgressRules()->progress($discipline, sportProgressProfile(), [], 100))->toBe([]);
})->with(['progeny', 'conformation', 'unknown']);
