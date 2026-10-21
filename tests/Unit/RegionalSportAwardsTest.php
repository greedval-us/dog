<?php

use App\Modules\Pets\Calculators\RegionalSportAwards;
use App\Modules\Pets\Calculators\RegulatedSportAwards;

function regionalSportEntry(int $id, string $discipline = 'agility', array $changes = []): array
{
    $profile = ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'breed_id' => 7, 'height_category' => 'M', 'race_category' => '3', 'championships' => []];
    $class = ['agility' => 'agility_3', 'nosework' => 'nw_3', 'canicross' => 'canicross_open'][$discipline];
    $result = ['qualified' => true, 'eliminated' => false, 'total_faults' => 0.0, 'time' => 60.0 + $id, 'qualified_categories' => 4,
        'legs' => ['agility' => ['total_faults' => 0.0, 'eliminated' => false], 'jumping' => ['total_faults' => 0.0, 'eliminated' => false]],
        'stages' => array_fill(0, 4, ['score' => 90, 'qualified' => true]), 'standings' => []];

    return array_replace_recursive(['id' => $id, 'division' => $class.':M', 'rank' => $id, 'status' => 'completed', 'is_npc' => false,
        'plan' => ['sport_class' => $class, 'program' => 'pr1'], 'snapshot' => ['profile' => $profile], 'result' => $result], $changes);
}

function regionalSportAwards(): RegionalSportAwards
{
    return new RegionalSportAwards(new RegulatedSportAwards(require dirname(__DIR__, 2).'/config/event_regulations.php'), require dirname(__DIR__, 2).'/config/sport_titles.php');
}

function regionalSportCertificates(array $awards, string $code): array
{
    return array_values(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === $code), 'entry_id'));
}

test('combined national agility awards ordinary and regional certificates to their distinct placements', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 6));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined', 'region' => 'moscow'], $entries);

    expect(regionalSportCertificates($awards, 'CACAg'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([1, 2, 3]);
    expect(regionalSportCertificates($awards, 'PLACE_3'))->toBe([3]);
});

test('national agility rejects a fault or elimination on either required leg', function (string $leg, array $changes) {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 6));
    $entries[0]['result']['legs'][$leg] = array_replace($entries[0]['result']['legs'][$leg], $changes);

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined'], $entries);

    expect(regionalSportCertificates($awards, 'CACAg'))->toBe([]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([2, 3]);
})->with(['agility time fault' => ['agility', ['total_faults' => 0.01]], 'jumping time fault' => ['jumping', ['total_faults' => 0.01]], 'jumping elimination' => ['jumping', ['eliminated' => true]]]);

test('national agility needs both legs and does not award certificates on a single agility track', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 6));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility'], $entries);

    expect(array_filter($awards, fn (array $award): bool => in_array($award['kind'], ['certificate', 'championship'], true)))->toBe([]);
});

test('a national championship winner receives a title and the runner up the ordinary certificate', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 15));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'championship', 'format' => 'agility_combined'], $entries);

    expect(regionalSportCertificates($awards, 'SPORT_CH_RUS'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'CACAg'))->toBe([2]);
    expect(regionalSportCertificates($awards, 'CACAg_BREED'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([1, 2, 3]);
});

test('breed certificates belong to the breed winner and never transfer past a failed norm', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 15));
    $entries[0]['result']['legs']['agility']['total_faults'] = 0.01;
    $entries[2]['snapshot']['profile']['breed_id'] = 8;

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'cup', 'format' => 'agility_combined'], $entries);

    expect(regionalSportCertificates($awards, 'CACAg_BREED'))->toBe([3]);
});

test('different agility height fields cannot combine to meet a certificate minimum', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, changes: $id > 3 ? ['snapshot' => ['profile' => ['height_category' => 'L']], 'division' => 'agility_3:L', 'rank' => $id - 3] : []), range(1, 6));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined'], $entries);

    expect(regionalSportCertificates($awards, 'CACAg'))->toBe([]);
});

test('npc starts and eliminated starters count but withdrawn entries do not meet field minimums', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, changes: $id > 1 ? ['is_npc' => true, 'result' => ['qualified' => false, 'eliminated' => true]] : []), range(1, 6));
    $regulation = ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined'];

    $enough = regionalSportAwards()->calculate('agility', $regulation, $entries);
    $entries[5]['status'] = 'withdrawn';
    $short = regionalSportAwards()->calculate('agility', $regulation, $entries);

    expect(regionalSportCertificates($enough, 'CACAg'))->toBe([1]);
    expect(regionalSportCertificates($short, 'CACAg'))->toBe([]);
});

test('monobreed agility combines heights for its field and issues only breed credentials', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, changes: ['snapshot' => ['profile' => ['height_category' => $id === 1 ? 'M' : 'L']], 'division' => $id === 1 ? 'agility_3:M' : 'agility_3:L', 'rank' => $id === 1 ? 1 : $id - 1]), range(1, 3));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined', 'sportScope' => 'breed', 'breedId' => 7], $entries);

    expect(regionalSportCertificates($awards, 'CACAg_BREED'))->toBe([1, 2]);
    expect(regionalSportCertificates($awards, 'CACAg'))->toBe([]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([]);
});

test('a monobreed RKF championship issues its breed title and second place breed certificate', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 5));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'cac_rkf', 'format' => 'agility_combined', 'sportScope' => 'breed', 'breedId' => 7], $entries);

    expect(regionalSportCertificates($awards, 'SPORT_BREED_RKF'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'CACAg_BREED'))->toBe([2]);
    expect(regionalSportCertificates($awards, 'SPORT_CH_RKF'))->toBe([]);
});

test('nosework strict certificate policy requires first place and four qualifying categories', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, 'nosework'), range(1, 3));
    $regulation = ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'nosework'];

    $awards = regionalSportAwards()->calculate('nosework', $regulation, $entries);
    $entries[0]['result']['stages'][3]['score'] = 79;
    $failed = regionalSportAwards()->calculate('nosework', $regulation, $entries);

    expect(regionalSportCertificates($awards, 'REG_CACNW'))->toBe([1]);
    expect(regionalSportCertificates($failed, 'CACNW'))->toBe([]);
    expect(regionalSportCertificates($failed, 'REG_CACNW'))->toBe([]);
    $certificate = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'REG_CACNW'))[0];
    expect($certificate['details']['nw_strict_first_place'])->toBeTrue();
});

test('canicross certificate fields and qualifying times stay within their speed category', function () {
    $entries = [regionalSportEntry(1, 'canicross'), regionalSportEntry(2, 'canicross'), regionalSportEntry(3, 'canicross', ['result' => ['time' => 1000.0]])];
    $regulation = ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'canicross'];

    $awards = regionalSportAwards()->calculate('canicross', $regulation, $entries);
    $entries[2]['snapshot']['profile']['race_category'] = '4';
    $short = regionalSportAwards()->calculate('canicross', $regulation, $entries);

    expect(regionalSportCertificates($awards, 'CACT'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'REG_CACT'))->toBe([1, 2]);
    expect(regionalSportCertificates($short, 'CACT'))->toBe([]);
});

test('canicross qualification needs three actual starters in its category', function (int $entryCount, array $lastEntryChanges) {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, 'canicross', ['division' => 'canicross_open:3:open']), range(1, $entryCount));
    $entries[$entryCount - 1] = array_replace_recursive($entries[$entryCount - 1], $lastEntryChanges);

    $awards = regionalSportAwards()->calculate('canicross', ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'canicross'], $entries);

    expect(regionalSportCertificates($awards, 'RACE_QUALIFICATION'))->toBe([]);
})->with([
    'one starter' => [1, []],
    'two starters' => [2, []],
    'withdrawn third entry' => [3, ['status' => 'withdrawn']],
    'third entry never started' => [3, ['result' => ['started' => false]]],
    'third starter belongs to another category' => [3, ['division' => 'canicross_open:4:open', 'snapshot' => ['profile' => ['race_category' => '4']]]],
]);

test('three canicross starters may earn a qualification with only one finisher', function () {
    $entries = [regionalSportEntry(1, 'canicross'),
        regionalSportEntry(2, 'canicross', ['rank' => null, 'result' => ['eliminated' => true, 'qualified' => false]]),
        regionalSportEntry(3, 'canicross', ['rank' => null, 'result' => ['eliminated' => true, 'qualified' => false]])];

    $awards = regionalSportAwards()->calculate('canicross', ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'canicross'], $entries);

    expect(regionalSportCertificates($awards, 'RACE_QUALIFICATION'))->toBe([1]);
});

test('historical canicross regulations retain their saved qualification policy', function () {
    $entries = [regionalSportEntry(1, 'canicross')];

    $awards = regionalSportAwards()->calculate('canicross', ['version' => 1, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'canicross'], $entries);

    expect(regionalSportCertificates($awards, 'RACE_QUALIFICATION'))->toBe([1]);
});

test('FCI agility keeps the individual clear round certificate and has no national certificates', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 15));

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'fci', 'rank' => 'certificate', 'format' => 'agility'], $entries);

    expect(regionalSportCertificates($awards, 'CACIAG'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'R_CACIAG'))->toBe([2]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([]);
});

test('v1 sport snapshots retain the original award output', function () {
    $balance = require dirname(__DIR__, 2).'/config/event_regulations.php';
    $base = new RegulatedSportAwards($balance);
    $entries = [regionalSportEntry(1)];
    $regulation = ['version' => 1, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility'];

    $awards = regionalSportAwards()->calculate('agility', $regulation, $entries);

    expect($awards)->toBe($base->calculate('agility', $regulation, $entries));
});

test('an absent required agility leg cannot earn a regional certificate', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id), range(1, 6));
    unset($entries[0]['result']['legs']['jumping']);

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'format' => 'agility_combined'], $entries);

    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([2, 3]);
});

test('canicross cups require four actual starters within the title field', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, 'canicross'), range(1, 4));
    $regulation = ['version' => 2, 'system' => 'rkf', 'rank' => 'cup', 'format' => 'canicross'];

    $enough = regionalSportAwards()->calculate('canicross', $regulation, $entries);
    $short = regionalSportAwards()->calculate('canicross', $regulation, array_slice($entries, 0, 3));

    expect(regionalSportCertificates($enough, 'SPORT_CUP_RUS'))->toBe([1]);
    expect(regionalSportCertificates($enough, 'CACT'))->toBe([2]);
    expect(regionalSportCertificates($short, 'SPORT_CUP_RUS'))->toBe([]);
    expect(regionalSportCertificates($short, 'REG_CACT'))->toBe([]);
});

test('unregistered dogs may win Russian championships but receive no title certificates', function () {
    $entries = array_map(fn (int $id): array => regionalSportEntry($id, changes: $id === 1 ? ['snapshot' => ['profile' => ['registered' => false]]] : []), range(1, 15));
    $regulation = ['version' => 2, 'system' => 'rkf', 'rank' => 'championship', 'format' => 'agility_combined'];

    $awards = regionalSportAwards()->calculate('agility', $regulation, $entries);
    $rkf = regionalSportAwards()->calculate('agility', [...$regulation, 'rank' => 'cac_rkf'], $entries);

    expect(regionalSportCertificates($awards, 'SPORT_CH_RUS'))->toBe([1]);
    expect(regionalSportCertificates($awards, 'REG_CACAg'))->toBe([2, 3]);
    expect(regionalSportCertificates($awards, 'CACAg_BREED'))->toBe([]);
    expect(regionalSportCertificates($rkf, 'SPORT_CH_RKF'))->toBe([]);
});

test('combined agility qualifications use the agility leg even when jumping fails', function (array $result) {
    $entry = regionalSportEntry(1, changes: ['rank' => null, 'result' => $result]);

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'agility_combined'], [$entry]);

    $qualifications = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'AGILITY_QUALIFICATION'));
    expect($qualifications)->toHaveCount(1);
    expect($qualifications[0]['details'])->toBe(['level' => 3, 'faults' => 5.99, 'format' => 'agility']);
    expect(regionalSportCertificates($awards, 'PLACE_1'))->toBe([]);
})->with([
    'jumping time faults' => [['total_faults' => 25.99, 'legs' => ['agility' => ['total_faults' => 5.99, 'eliminated' => false], 'jumping' => ['total_faults' => 20.0, 'eliminated' => false]]]],
    'jumping elimination' => [['total_faults' => 25.99, 'eliminated' => true, 'qualified' => false, 'legs' => ['agility' => ['total_faults' => 5.99, 'eliminated' => false], 'jumping' => ['total_faults' => 20.0, 'eliminated' => true]]]],
]);

test('combined agility qualifications reject an insufficient or eliminated agility leg', function (array $leg) {
    $entry = regionalSportEntry(1, changes: ['result' => ['legs' => ['agility' => $leg]]]);

    $awards = regionalSportAwards()->calculate('agility', ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'agility_combined'], [$entry]);

    expect(regionalSportCertificates($awards, 'AGILITY_QUALIFICATION'))->toBe([]);
    expect(regionalSportCertificates($awards, 'AGILITY_START'))->toBe([1]);
})->with(['insufficient grade' => [['total_faults' => 6.0, 'eliminated' => false]], 'elimination' => [['total_faults' => 0.0, 'eliminated' => true]]]);
