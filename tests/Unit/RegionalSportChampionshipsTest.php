<?php

use App\Modules\Pets\Calculators\RegionalSportChampionships;

function regionalSportCredential(int $id, string $code = 'REG_CACAg', array $changes = []): array
{
    return array_replace_recursive(['title_id' => $id, 'entry_id' => $id, 'discipline' => 'agility', 'code' => $code,
        'kind' => str_starts_with($code, 'SPORT_') ? 'championship' : 'certificate', 'scope_key' => '', 'regulation_version' => '2',
        'awarded_at' => $id, 'judgeId' => 'judge-'.($id % 2), 'country' => 'RU', 'region' => 'moscow', 'rank' => 'certificate', 'details' => []], $changes);
}

function regionalSportChampionships(): RegionalSportChampionships
{
    return new RegionalSportChampionships(require dirname(__DIR__, 2).'/config/sport_titles.php');
}

function regionalSportProfile(array $changes = []): array
{
    return array_replace(['registered' => true, 'recognized_rkf' => true, 'championships' => []], $changes);
}

test('three regional certificates from two judges produce a region scoped title with exact proof', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), [1, 2, 3, 4]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toHaveCount(1);
    expect($awards[0]['code'])->toBe('SPORT_REGIONAL_CH');
    expect($awards[0]['scope_key'])->toStartWith('region:moscow:issue:');
    expect($awards[0]['details'])->toMatchArray(['region' => 'moscow', 'credential_title_ids' => [1, 2, 3], 'credential_entry_ids' => [1, 2, 3], 'proof_purpose' => 'sport_exchange']);
});

test('regional certificates need three events two judges and one known subject', function (array $changes) {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, changes: $changes[$id] ?? []), [1, 2, 3]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
})->with([
    'one judge' => [[1 => ['judgeId' => 'judge'], 2 => ['judgeId' => 'judge'], 3 => ['judgeId' => 'judge']]],
    'duplicate event' => [[3 => ['entry_id' => 2]]],
    'mixed subjects' => [[3 => ['region' => 'tula']]],
    'missing subject' => [[3 => ['region' => '']]],
    'future certificate' => [[3 => ['awarded_at' => 101]]],
    'unknown source ID' => [[3 => ['title_id' => 0]]],
]);

test('six regional certificates produce two distinct repeatable issues in the same subject', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), range(1, 6));

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(['championships' => ['SPORT_REGIONAL_CH']]), $credentials, 100);

    expect($awards)->toHaveCount(2);
    expect($awards[0]['scope_key'])->not->toBe($awards[1]['scope_key']);
    expect($awards[0]['details']['credential_title_ids'])->toBe([1, 2, 3]);
    expect($awards[1]['details']['credential_title_ids'])->toBe([4, 5, 6]);
});

test('saved regional proof consumes its exact certificates and the following issue uses new ones', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), range(1, 6));
    $saved = regionalSportCredential(20, 'SPORT_REGIONAL_CH', ['details' => ['policy_version' => 2, 'proof_purpose' => 'sport_exchange', 'credential_title_ids' => [1, 2, 3], 'region' => 'moscow']]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), [...$credentials, $saved], 100);

    expect($awards)->toHaveCount(1);
    expect($awards[0]['details']['credential_title_ids'])->toBe([4, 5, 6]);
});

test('the consumption registry prevents reusing primary certificates across exchanges', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, changes: $id === 1 ? ['details' => ['used_for' => ['sport_exchange']]] : []), [1, 2, 3]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
});

test('new regional awards wait for persistent diploma IDs before participating in national exchange', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, changes: $id > 3 ? ['region' => 'tula'] : []), range(1, 6));

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_REGIONAL_CH', 'SPORT_REGIONAL_CH']);
});

test('all eight national exchange paths accept their exact minimum proof', function (array $codes, array $judges, array $regions, int $path) {
    $credentials = [];
    foreach ($codes as $index => $code) {
        $credentials[] = regionalSportCredential($index + 1, $code, ['judgeId' => $judges[$index], 'region' => $regions[$index]]);
    }

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toHaveCount(1);
    expect($awards[0]['code'])->toBe('SPORT_NATIONAL_CH');
    expect($awards[0]['scope_key'])->toBe('');
    expect($awards[0]['details']['exchange_path'])->toBe('national_'.$path);
    expect($awards[0]['details']['credential_title_ids'])->toBe(range(1, count($codes)));
})->with([
    'cup plus RKF' => [['SPORT_CUP_RUS', 'SPORT_CH_RKF'], ['a', 'b'], ['moscow', 'moscow'], 1],
    'two cups' => [['SPORT_CUP_RUS', 'SPORT_CUP_RUS'], ['a', 'b'], ['moscow', 'moscow'], 2],
    'cup plus two ordinary' => [['SPORT_CUP_RUS', 'CACAg', 'CACAg'], ['a', 'b', 'c'], ['moscow', 'moscow', 'moscow'], 3],
    'two RKF' => [['SPORT_CH_RKF', 'SPORT_CH_RKF'], ['a', 'b'], ['moscow', 'moscow'], 4],
    'RKF plus two ordinary' => [['SPORT_CH_RKF', 'CACAg', 'CACAg'], ['a', 'b', 'c'], ['moscow', 'moscow', 'moscow'], 5],
    'five ordinary' => [['CACAg', 'CACAg', 'CACAg', 'CACAg', 'CACAg'], ['a', 'b', 'c', 'd', 'a'], ['moscow', 'moscow', 'tula', 'tula', 'novgorod'], 6],
    'two subjects' => [['SPORT_REGIONAL_CH', 'SPORT_REGIONAL_CH'], ['a', 'a'], ['moscow', 'tula'], 7],
    'regional plus Russian champion' => [['SPORT_REGIONAL_CH', 'SPORT_CH_RUS'], ['a', 'a'], ['moscow', 'moscow'], 8],
]);

test('two regional diplomas of the same subject do not complete a national title', function () {
    $credentials = [regionalSportCredential(1, 'SPORT_REGIONAL_CH'), regionalSportCredential(2, 'SPORT_REGIONAL_CH')];

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
});

test('national proof searches past early certificates to find the required judges and regions', function () {
    $credentials = [];
    foreach (range(1, 5) as $id) {
        $credentials[] = regionalSportCredential($id, 'CACAg', ['judgeId' => 'a', 'region' => 'moscow']);
    }
    $credentials[] = regionalSportCredential(6, 'CACAg', ['judgeId' => 'b', 'region' => 'moscow']);
    $credentials[] = regionalSportCredential(7, 'CACAg', ['judgeId' => 'c', 'region' => 'tula']);
    $credentials[] = regionalSportCredential(8, 'CACAg', ['judgeId' => 'd', 'region' => 'novgorod']);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toHaveCount(1);
    expect($awards[0]['details']['credential_title_ids'])->toBe([1, 2, 6, 7, 8]);
});

test('breed exchanges consume three certificates and preserve unrelated extra proof', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, 'CACAg_BREED'), [1, 2, 3, 4]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toHaveCount(1);
    expect($awards[0]['code'])->toBe('SPORT_BREED_CH');
    expect($awards[0]['details']['credential_title_ids'])->toBe([1, 2, 3]);
});

test('existing national and breed diplomas stop repeat cumulative titles including legacy history', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, 'CACAg_BREED'), [1, 2, 3]);
    $credentials[] = regionalSportCredential(10, 'SPORT_REGIONAL_CH');
    $credentials[] = regionalSportCredential(11, 'SPORT_CH_RKF');
    $credentials[] = regionalSportCredential(12, 'SPORT_BREED_CH', ['regulation_version' => '1']);
    $credentials[] = regionalSportCredential(13, 'SPORT_NATIONAL_CH', ['regulation_version' => '1']);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
});

test('different disciplines and missing pedigrees cannot complete sport exchanges', function (array $profile, string $discipline) {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), [1, 2, 3]);

    $awards = regionalSportChampionships()->calculate($discipline, $profile, $credentials, 100);

    expect($awards)->toBe([]);
})->with(['wrong discipline' => [regionalSportProfile(), 'nosework'], 'unregistered' => [regionalSportProfile(['registered' => false]), 'agility'], 'unrecognized' => [regionalSportProfile(['recognized_rkf' => false]), 'agility']]);

test('certificate IDs are independent from event IDs while one event cannot count twice', function () {
    $credentials = [regionalSportCredential(101, changes: ['entry_id' => 1]), regionalSportCredential(102, changes: ['entry_id' => 2]), regionalSportCredential(103, changes: ['entry_id' => 2])];

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 200);

    expect($awards)->toBe([]);
});

test('national mixed paths require different judges for every selected event', function (array $codes, array $judges, array $regions) {
    $credentials = [];
    foreach ($codes as $index => $code) {
        $credentials[] = regionalSportCredential($index + 1, $code, ['judgeId' => $judges[$index], 'region' => $regions[$index]]);
    }

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
})->with([
    'cup and RKF under one judge' => [['SPORT_CUP_RUS', 'SPORT_CH_RKF'], ['a', 'a'], ['moscow', 'moscow']],
    'cup ordinary without three judges' => [['SPORT_CUP_RUS', 'CACAg', 'CACAg'], ['a', 'b', 'b'], ['moscow', 'moscow', 'moscow']],
    'RKF ordinary without three judges' => [['SPORT_CH_RKF', 'CACAg', 'CACAg'], ['a', 'a', 'b'], ['moscow', 'moscow', 'moscow']],
    'five ordinary without three subjects' => [['CACAg', 'CACAg', 'CACAg', 'CACAg', 'CACAg'], ['a', 'b', 'c', 'd', 'a'], ['moscow', 'moscow', 'tula', 'tula', 'tula']],
]);

test('a global profile title does not suppress a cumulative title in another discipline', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id, 'CACAg_BREED'), [1, 2, 3]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(['championships' => ['SPORT_BREED_CH']]), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_BREED_CH']);
});

test('old aggregate proof lists do not fabricate consumption of unused certificates', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), [1, 2, 3]);
    $credentials[] = regionalSportCredential(10, 'SPORT_REGIONAL_CH', ['regulation_version' => '1', 'details' => ['credential_entry_ids' => [1, 2, 3]]]);

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_REGIONAL_CH']);
});

test('consuming regional source certificates does not consume the resulting saved diploma', function () {
    $credentials = array_map(fn (int $id): array => regionalSportCredential($id), [1, 2, 3]);
    $credentials[] = regionalSportCredential(10, 'SPORT_REGIONAL_CH', ['details' => ['policy_version' => 2, 'proof_purpose' => 'sport_exchange', 'credential_title_ids' => [1, 2, 3], 'region' => 'moscow']]);
    $credentials[] = regionalSportCredential(11, 'SPORT_CUP_RUS');

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_NATIONAL_CH']);
    expect($awards[0]['details']['credential_title_ids'])->toBe([10, 11]);
});

test('certificate kinds cannot impersonate a direct champion diploma in national proof', function () {
    $credentials = [regionalSportCredential(1, 'SPORT_REGIONAL_CH'), regionalSportCredential(2, 'SPORT_CH_RUS', ['kind' => 'certificate'])];

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
});

test('marginal judge and region counts cannot replace a valid five certificate combination', function () {
    $credentials = [];
    foreach (range(1, 60) as $id) {
        $credentials[] = regionalSportCredential($id, 'CACAg', ['judgeId' => 'judge-'.($id % 3), 'region' => '']);
    }
    foreach (['moscow', 'tula', 'novgorod'] as $index => $region) {
        $credentials[] = regionalSportCredential(61 + $index, 'CACAg', ['judgeId' => 'judge-4', 'region' => $region]);
    }

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect($awards)->toBe([]);
});

test('regional diploma exchanges accept distinct diplomas saved on the same triggering entry', function (string $secondCode, string $region, string $path) {
    $credentials = [
        regionalSportCredential(1, 'SPORT_REGIONAL_CH', ['entry_id' => 99, 'scope_key' => 'region:moscow:issue:first']),
        regionalSportCredential(2, $secondCode, ['entry_id' => 99, 'scope_key' => 'region:'.$region.':issue:second', 'region' => $region]),
    ];

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_NATIONAL_CH']);
    expect($awards[0]['details']['exchange_path'])->toBe($path);
    expect($awards[0]['details']['credential_title_ids'])->toBe([1, 2]);
    expect($awards[0]['details']['credential_entry_ids'])->toBe([99]);
})->with(['two regions' => ['SPORT_REGIONAL_CH', 'tula', 'national_7'], 'regional plus direct title' => ['SPORT_CH_RUS', 'moscow', 'national_8']]);

test('joint coverage can reassign a judge region pairing to preserve a valid proof', function () {
    $credentials = [
        regionalSportCredential(1, 'CACAg', ['judgeId' => 'a', 'region' => 'moscow']),
        regionalSportCredential(2, 'CACAg', ['judgeId' => 'a', 'region' => 'tula']),
        regionalSportCredential(3, 'CACAg', ['judgeId' => 'a', 'region' => 'novgorod']),
        regionalSportCredential(4, 'CACAg', ['judgeId' => 'b', 'region' => 'moscow']),
        regionalSportCredential(5, 'CACAg', ['judgeId' => 'c', 'region' => '']),
        regionalSportCredential(6, 'CACAg', ['judgeId' => 'd', 'region' => '']),
    ];

    $awards = regionalSportChampionships()->calculate('agility', regionalSportProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toBe(['SPORT_NATIONAL_CH']);
    expect($awards[0]['details']['credential_title_ids'])->toBe([2, 3, 4, 5, 6]);
});
