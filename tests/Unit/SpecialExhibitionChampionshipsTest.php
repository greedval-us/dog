<?php

use App\Modules\Pets\Calculators\EventChampionshipRules;
use App\Modules\Pets\Calculators\SpecialExhibitionChampionships;

function specialChampionshipBalance(): array
{
    return [...require dirname(__DIR__, 2).'/config/event_regulations.php', 'special_exhibitions' => require dirname(__DIR__, 2).'/config/special_exhibitions.php'];
}

function specialChampionshipProfile(array $changes = []): array
{
    return ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true, 'working_breed' => false, 'has_nkp' => true, 'championships' => [], ...$changes];
}

function specialChampionshipCredential(int $id, string $code, string $show = 'eurasia', string $leg = 'president', string $edition = '2026-10', array $changes = []): array
{
    return ['title_id' => $id, 'entry_id' => $id, 'code' => $code, 'kind' => 'certificate', 'scope_key' => '', 'regulation_version' => 2, 'discipline' => 'conformation', 'awarded_at' => $id * 10, 'judgeId' => 'judge-'.$id, 'country' => 'RU', 'region' => 'moscow', 'rank' => 'cac_rkf_special', 'details' => ['specialShow' => $show, 'showLeg' => $leg, 'showYear' => 2026, 'showEdition' => $edition], ...$changes];
}

function specialChampionshipRules(): SpecialExhibitionChampionships
{
    $balance = specialChampionshipBalance();

    return new SpecialExhibitionChampionships(new EventChampionshipRules($balance), $balance);
}

test('two doubled certificates close national championships with minimal proof', function (string $certificate, string $title) {
    $credentials = [specialChampionshipCredential(1, $certificate), specialChampionshipCredential(2, $certificate, leg: 'cis'), specialChampionshipCredential(3, 'SHOW_GRADE')];

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100);

    $award = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === $title))[0] ?? null;
    expect($award)->not->toBeNull();
    expect($award['details']['credential_title_ids'])->toBe([1, 2]);
    expect($award['scope_key'])->toBe('');
})->with([['CAC', 'CH_RUS'], ['JCAC', 'JCH_RUS'], ['VCAC', 'VCH_RUS']]);

test('winner exchange requires a CAC under another judge and does not grant a junior title', function () {
    $credentials = [specialChampionshipCredential(1, 'EAW'), specialChampionshipCredential(2, 'CAC', changes: ['judgeId' => 'judge-1'])];

    expect(array_column(specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100), 'code'))->not->toContain('CH_RUS');

    $credentials[1]['judgeId'] = 'judge-2';
    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toContain('CH_RUS')->not->toContain('JCH_RUS');
});

test('absolute winners require both legs of the same monthly edition', function (string $leg, string $edition, bool $eligible) {
    $credentials = [specialChampionshipCredential(1, 'EAW'), specialChampionshipCredential(2, 'EAW', leg: $leg, edition: $edition)];

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(['pedigree_complete' => false]), $credentials, 100);

    expect(in_array('EAAW', array_column($awards, 'code'), true))->toBe($eligible);
})->with(['both legs' => ['cis', '2026-10', true], 'same leg' => ['president', '2026-10', false], 'different edition' => ['cis', '2026-11', false]]);

test('RKF grand championships use named shows and do not reuse their own consumed proof', function () {
    $credentials = [specialChampionshipCredential(1, 'CH_RKF_CERT'), specialChampionshipCredential(2, 'CH_RKF_CERT', leg: 'cis'), specialChampionshipCredential(3, 'CH_RKF_CERT', 'russia', 'rkf_cup'), specialChampionshipCredential(4, 'CH_RKF_CERT', 'mazover', '')];
    $rules = specialChampionshipRules();

    $awards = $rules->calculate(specialChampionshipProfile(), $credentials, 100);
    $grand = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'GCH_RKF'))[0] ?? null;

    expect($grand)->not->toBeNull();
    expect($grand['details']['credential_title_ids'])->toBe([1, 2, 3]);
    $credentials[] = ['title_id' => 20, 'entry_id' => 3, 'code' => 'GCH_RKF', 'kind' => 'championship', 'discipline' => 'conformation', 'awarded_at' => 40, 'scope_key' => $grand['scope_key'], 'details' => $grand['details']];

    expect(array_column($rules->calculate(specialChampionshipProfile(['championships' => ['GCH_RKF']]), $credentials, 100), 'code'))->not->toContain('GCH_RKF');
});

test('ordinary championships keep exact minimal evidence', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'JCAC', '', '', changes: ['rank' => 'cac_rkf']), [1, 2, 3, 4]);

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100);
    $junior = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'JCH_RUS'))[0];

    expect($junior['details']['credential_title_ids'])->toBe([1, 2, 3]);
});

test('expired and future certificates cannot complete a special exchange', function () {
    $credentials = [specialChampionshipCredential(1, 'EAW'), specialChampionshipCredential(2, 'CAC')];

    expect(array_column(specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 61 * 86400), 'code'))->not->toContain('CH_RUS');
    expect(array_column(specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 15), 'code'))->not->toContain('CH_RUS');
});

test('special reserves count once without needing a champion ahead of them', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'R_JCAC'), [1, 2, 3]);

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toContain('JCH_RUS');
    expect(array_column(specialChampionshipRules()->calculate(specialChampionshipProfile(), array_slice($credentials, 0, 2), 100), 'code'))->not->toContain('JCH_RUS');
});

test('foreign national CACs cannot close a Russian championship', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'JCAC', changes: ['country' => 'DE']), [1, 2, 3]);

    expect(array_column(specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100), 'code'))->not->toContain('JCH_RUS');
});

test('absolute RKF championships consume matching issued absolute winner documents once per purpose', function () {
    $credentials = [specialChampionshipCredential(1, 'EAAW', changes: ['kind' => 'championship']), specialChampionshipCredential(2, 'RAW', 'russia', 'rkf_cup', changes: ['kind' => 'championship'])];
    $rules = specialChampionshipRules();

    $awards = $rules->calculate(specialChampionshipProfile(['pedigree_complete' => false]), $credentials, 100);
    $absolute = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'ACH_RKF'))[0];

    expect($absolute['details']['credential_title_ids'])->toBe([1, 2]);
    expect($absolute['details']['proof_purpose'])->toBe('show:ACH_RKF');
    $credentials[] = specialChampionshipCredential(3, 'ACH_RKF', changes: ['kind' => 'championship', 'scope_key' => $absolute['scope_key'], 'details' => $absolute['details']]);

    expect(array_column($rules->calculate(specialChampionshipProfile(['championships' => ['ACH_RKF']]), $credentials, 100), 'code'))->not->toContain('ACH_RKF');
});

test('annual club winner shortcuts require the other judge and adult working evidence', function () {
    $credentials = [specialChampionshipCredential(1, 'CLUB_WINNER', 'national_club', '', changes: ['rank' => 'specialty']), specialChampionshipCredential(2, 'KCHK')];
    $rules = specialChampionshipRules();

    expect(array_column($rules->calculate(specialChampionshipProfile(), $credentials, 100), 'code'))->toContain('CH_CLUB');
    expect(array_column($rules->calculate(specialChampionshipProfile(['working_breed' => true]), $credentials, 100), 'code'))->not->toContain('CH_CLUB');
    expect(array_column($rules->calculate(specialChampionshipProfile(['has_nkp' => false]), $credentials, 100), 'code'))->not->toContain('CH_BREED', 'CH_CLUB');
});

test('a second RKF grand championship uses a fresh minimum set even when the code is already held', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'JCH_RKF_CERT'), [1, 2]);
    $rules = specialChampionshipRules();
    $first = array_values(array_filter($rules->calculate(specialChampionshipProfile(), $credentials, 100), fn (array $award): bool => $award['code'] === 'JGCH_RKF'))[0];
    $credentials[] = specialChampionshipCredential(10, 'JGCH_RKF', changes: ['kind' => 'championship', 'scope_key' => $first['scope_key'], 'details' => $first['details']]);
    $credentials[] = specialChampionshipCredential(3, 'JCH_RKF_CERT');
    $credentials[] = specialChampionshipCredential(4, 'JCH_RKF_CERT');

    $awards = $rules->calculate(specialChampionshipProfile(['championships' => ['JGCH_RKF']]), $credentials, 100);
    $second = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'JGCH_RKF'))[0];

    expect($second['details']['credential_title_ids'])->toBe([3, 4]);
    expect($second['scope_key'])->not->toBe($first['scope_key']);
});

test('breed championships require breed proof and feed the corresponding Russian grand title', function () {
    $credentials = [specialChampionshipCredential(1, 'CH_RUS', changes: ['kind' => 'championship']), specialChampionshipCredential(2, 'CH_BREED', changes: ['kind' => 'championship'])];

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(['has_nkp' => false, 'championships' => ['CH_RUS', 'CH_BREED']]), $credentials, 100);
    $grand = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'GRAND_CH_RUS'))[0] ?? null;

    expect($grand)->not->toBeNull();
    expect($grand['details']['credential_title_ids'])->toBe([1, 2]);
});

test('double certificates still need two judges and a complete pedigree', function () {
    $credentials = [specialChampionshipCredential(1, 'CAC'), specialChampionshipCredential(2, 'CAC', changes: ['judgeId' => 'judge-1'])];
    $rules = specialChampionshipRules();

    expect(array_column($rules->calculate(specialChampionshipProfile(), $credentials, 100), 'code'))->not->toContain('CH_RUS');
    $credentials[1]['judgeId'] = 'judge-2';
    expect(array_column($rules->calculate(specialChampionshipProfile(['pedigree_complete' => false]), $credentials, 100), 'code'))->not->toContain('CH_RUS');
});

test('a breed without an NKP cannot turn frozen club certificates into a club championship', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'JKCHK', changes: ['rank' => 'specialty']), [1, 2, 3]);

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(['has_nkp' => false]), $credentials, 100);

    expect(array_column($awards, 'code'))->not->toContain('JCH_CLUB');
});

test('a breed without an NKP requires its breed championship for a grand title even when it holds a legacy club title', function () {
    $credentials = [specialChampionshipCredential(1, 'CH_RUS', changes: ['kind' => 'championship']), specialChampionshipCredential(2, 'CH_CLUB', changes: ['kind' => 'championship'])];

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(['has_nkp' => false, 'championships' => ['CH_RUS', 'CH_CLUB']]), $credentials, 100);

    expect(array_column($awards, 'code'))->not->toContain('GRAND_CH_RUS');
});

test('Russian grand titles cite their two saved championship documents instead of unrelated certificates', function (string $national, string $club, string $grand) {
    $credentials = [specialChampionshipCredential(1, 'CAC'),
        specialChampionshipCredential(2, $national, changes: ['kind' => 'championship']),
        specialChampionshipCredential(3, $club, changes: ['kind' => 'championship'])];

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(['championships' => [$national, $club]]), $credentials, 100);
    $title = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === $grand))[0] ?? null;

    expect($title)->not->toBeNull();
    expect($title['details']['credential_title_ids'])->toBe([2, 3]);
})->with([['CH_RUS', 'CH_CLUB', 'GRAND_CH_RUS'], ['JCH_RUS', 'JCH_CLUB', 'JGRAND_CH_RUS'], ['VCH_RUS', 'VCH_CLUB', 'VGRAND_CH_RUS']]);

test('grand title calculation waits for newly earned component championships to receive saved identities', function () {
    $credentials = array_map(fn (int $id): array => specialChampionshipCredential($id, 'CAC'), range(1, 4));
    foreach (range(5, 8) as $id) {
        $credentials[] = specialChampionshipCredential($id, 'KCHK', changes: ['rank' => 'specialty']);
    }

    $awards = specialChampionshipRules()->calculate(specialChampionshipProfile(), $credentials, 100);

    expect(array_column($awards, 'code'))->toContain('CH_RUS', 'CH_CLUB')->not->toContain('GRAND_CH_RUS');
});
