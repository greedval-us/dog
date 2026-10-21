<?php

use App\Modules\Pets\Calculators\EventChampionshipRules;
use App\Modules\Pets\Calculators\SpecialExhibitionChampionships;

function showProgressBalance(): array
{
    return [...require dirname(__DIR__, 2).'/config/event_regulations.php', 'special_exhibitions' => require dirname(__DIR__, 2).'/config/special_exhibitions.php'];
}

function showProgressProfile(array $changes = []): array
{
    return ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true,
        'working_breed' => false, 'has_nkp' => true, 'championships' => [], ...$changes];
}

function showProgressReceipt(int $id, string $code, array $changes = []): array
{
    return ['title_id' => $id, 'entry_id' => $id, 'code' => $code, 'kind' => 'certificate', 'discipline' => 'conformation',
        'awarded_at' => 100 + $id, 'judgeId' => 'judge-'.$id, 'country' => 'RU', 'rank' => 'cac_rkf', 'regulation_version' => 2, 'details' => [], ...$changes];
}

function showProgressGoal(array $goals, string $code): array
{
    return array_values(array_filter($goals, fn (array $goal): bool => $goal['code'] === $code))[0];
}

function showProgressRules(): SpecialExhibitionChampionships
{
    $balance = showProgressBalance();

    return new SpecialExhibitionChampionships(new EventChampionshipRules($balance), $balance);
}

test('show progress describes the exact junior certificate and judge deficits', function () {
    $receipts = [showProgressReceipt(1, 'JCAC'), showProgressReceipt(2, 'JCAC', ['judgeId' => 'judge-1'])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'JCH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'standard'))[0];

    expect($path['ready'])->toBeFalse();
    expect(array_column($path['requirements'], null, 'key')['certificates'])->toMatchArray(['current' => 2, 'required' => 3, 'met' => false]);
    expect(array_column($path['requirements'], null, 'key')['judges'])->toMatchArray(['current' => 1, 'required' => 3, 'met' => false]);
    expect($path['available_title_ids'])->toBe([1, 2]);
});

test('paired national reserves retain both source documents but count as one certificate', function (string $certificate, string $title, string $pathKey) {
    $receipts = [showProgressReceipt(1, 'R_'.$certificate), showProgressReceipt(2, 'R_'.$certificate)];
    $base = new EventChampionshipRules(showProgressBalance());

    foreach ([$base->progress('conformation', showProgressProfile(), $receipts, 1000), showProgressRules()->progress(showProgressProfile(), $receipts, 1000)] as $goals) {
        $goal = showProgressGoal($goals, $title);
        $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === $pathKey))[0];
        $sourceIds = $path['available_title_ids'];
        sort($sourceIds);

        expect(array_column($path['requirements'], null, 'key')['certificates']['current'])->toBe(1);
        expect($sourceIds)->toBe([1, 2]);
        expect($path['excluded'])->toBe([]);
        expect($path['ready'])->toBeFalse();
    }
})->with([
    'junior' => ['JCAC', 'JCH_RUS', 'standard'],
    'adult' => ['CAC', 'CH_RUS', 'standard_interval'],
    'veteran' => ['VCAC', 'VCH_RUS', 'standard'],
]);

test('special doubled certificates show one missing certificate and another judge', function () {
    $receipt = showProgressReceipt(1, 'CAC', ['rank' => 'cac_rkf_special', 'details' => [
        'specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10',
    ]]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), [$receipt], 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'two_doubled_certificates'))[0];

    expect(array_column($path['requirements'], null, 'key')['certificates'])->toMatchArray(['current' => 1, 'required' => 2]);
    expect($path['targets'][0]['excludeJudges'])->toBe(['judge-1']);
    expect($path['ready'])->toBeFalse();
});

test('a club title with missing specialty certificates targets a specialty show', function () {
    $receipts = array_map(fn (int $id): array => showProgressReceipt($id, 'KCHK', ['rank' => 'specialty_breed']), [1, 2, 3, 4]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'CH_CLUB');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'standard'))[0];

    expect(array_column($path['requirements'], null, 'key')['specialty_certificates'])->toMatchArray(['current' => 0, 'required' => 2, 'met' => false]);
    expect($path['targets'][0])->toMatchArray(['codes' => ['KCHK'], 'rank' => 'specialty']);
});

test('the Russian special certificate path targets only its qualifying show ranks', function () {
    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), [], 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'special_certificates'))[0];

    expect(array_column($path['targets'], 'rank'))->toBe(['cacib', 'cac_rkf_special']);
});

test('absolute winner progress matches editions and points to the missing leg', function () {
    $receipt = showProgressReceipt(1, 'EAW', ['kind' => 'comparison', 'rank' => 'cac_rkf_special', 'details' => [
        'specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10',
    ]]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['pedigree_complete' => false]), [$receipt], 1000), 'EAAW');
    $path = $goal['paths'][0];

    expect($goal['scope_key'])->toBe('edition:2026-10');
    expect($path['ready'])->toBeFalse();
    expect($path['targets'][0])->toMatchArray(['codes' => ['EAW'], 'specialShow' => ['eurasia'], 'showLeg' => ['cis'], 'showEdition' => '2026-10']);
    expect(array_column($path['requirements'], 'showLeg'))->toBe(['president', 'cis']);
});

test('repeatable grand progress excludes the consumed issue and starts a fresh proof set', function () {
    $details = ['specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10'];
    $receipts = [showProgressReceipt(1, 'JCH_RKF_CERT', ['rank' => 'cac_rkf_special', 'details' => $details]),
        showProgressReceipt(2, 'JCH_RKF_CERT', ['rank' => 'cac_rkf_special', 'details' => $details]),
        showProgressReceipt(3, 'JGCH_RKF', ['kind' => 'championship', 'scope_key' => 'issue:old', 'details' => ['proof_purpose' => 'show:JGCH_RKF', 'credential_title_ids' => [1, 2]]]),
        showProgressReceipt(4, 'JCH_RKF_CERT', ['rank' => 'cac_rkf_special', 'details' => $details])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['championships' => ['JGCH_RKF']]), $receipts, 1000), 'JGCH_RKF');

    expect($goal['repeatable'])->toBeTrue();
    expect($goal['achieved'])->toBeFalse();
    expect($goal['paths'][0]['available_title_ids'])->toBe([4]);
    expect(array_column($goal['paths'][0]['excluded'], 'reason', 'title_id'))->toMatchArray([1 => 'used', 2 => 'used']);
    expect($goal['paths'][0]['ready'])->toBeFalse();
});

test('complete international receipt counts still expose a missing pedigree document', function () {
    $receipts = [showProgressReceipt(1, 'CACIB_J', ['country' => 'DE']), showProgressReceipt(2, 'CACIB_J', ['country' => 'FR']), showProgressReceipt(3, 'CACIB_J', ['country' => 'FI'])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['pedigree_complete' => false]), $receipts, 1000), 'CIB_J');

    expect($goal['paths'][0]['ready'])->toBeFalse();
    expect(array_column($goal['paths'][0]['requirements'], null, 'key')['pedigree_complete'])->toMatchArray(['current' => 0, 'required' => 1, 'met' => false]);
});

test('a completed path carries the same saved title identities as the award calculation', function () {
    $receipts = array_map(fn (int $id): array => showProgressReceipt($id, 'JCAC'), [1, 2, 3]);
    $rules = showProgressRules();
    $award = array_values(array_filter($rules->calculate(showProgressProfile(), $receipts, 1000), fn (array $award): bool => $award['code'] === 'JCH_RUS'))[0];

    $goal = showProgressGoal($rules->progress(showProgressProfile(), $receipts, 1000), 'JCH_RUS');
    $ready = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['ready']));

    expect($ready)->not->toBeEmpty();
    expect($ready[0]['proof_title_ids'])->toBe($award['details']['credential_title_ids']);
});

test('the title catalog includes every applicable registry family and no unsupported club alternative', function (bool $hasClub, string $adultTitle, string $excludedTitle) {
    $goals = showProgressRules()->progress(showProgressProfile(['has_nkp' => $hasClub]), [], 1000);

    expect($goals)->toHaveCount(28);
    expect(array_column($goals, 'code'))->toContain($adultTitle, 'CH_RUS', 'CIB', 'CIE', 'EAAW', 'RAW', 'GCH_RKF', 'ACH_RKF')->not->toContain($excludedTitle);
})->with(['NKP' => [true, 'CH_CLUB', 'CH_BREED'], 'breed registry' => [false, 'CH_BREED', 'CH_CLUB']]);

test('expired national evidence is explained and future evidence does not contribute', function () {
    $receipts = [showProgressReceipt(1, 'JCAC', ['awarded_at' => 1]),
        showProgressReceipt(2, 'JCAC', ['awarded_at' => 61 * 86400]),
        showProgressReceipt(3, 'JCAC', ['awarded_at' => 63 * 86400])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 62 * 86400), 'JCH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'standard'))[0];

    expect($path['available_title_ids'])->toBe([2]);
    expect($path['excluded'])->toBe([['title_id' => 1, 'reason' => 'expired']]);
});

test('Russian interval progress asks for a later certificate rather than merely waiting', function () {
    $rules = new EventChampionshipRules(showProgressBalance());
    $receipts = array_map(fn (int $id): array => showProgressReceipt($id, 'CAC'), [1, 2, 3, 4]);

    $goal = showProgressGoal($rules->progress('conformation', showProgressProfile(), $receipts, 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'standard_interval'))[0];

    expect(array_column($path['requirements'], null, 'key')['interval'])->toMatchArray(['current' => 3, 'required' => 1036800, 'met' => false, 'ready_at' => 1036901]);
    expect($path['targets'][0])->toMatchArray(['codes' => ['CAC'], 'notBefore' => 1036901, 'excludeJudges' => []]);
    expect($path['ready'])->toBeFalse();
});

test('an international title allows the fourth certificate in an already represented country', function () {
    $receipts = [showProgressReceipt(1, 'CACIB', ['country' => 'DE']), showProgressReceipt(2, 'CACIB', ['country' => 'FR']), showProgressReceipt(3, 'CACIB', ['country' => 'FI'])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'CIB');

    expect($goal['paths'][0]['targets'][0]['excludeCountries'])->toBe([]);
    expect($goal['paths'][0]['targets'][0]['excludeJudges'])->toBe([]);
    expect(array_column($goal['paths'][0]['requirements'], null, 'key')['certificates'])->toMatchArray(['current' => 3, 'required' => 4, 'met' => false]);
});

test('a later named winner can complete the shortcut even if the earliest winner shares the CAC judge', function () {
    $metadata = ['specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10'];
    $receipts = [showProgressReceipt(1, 'EAW', ['kind' => 'comparison', 'rank' => 'cac_rkf_special', 'judgeId' => 'shared', 'details' => $metadata]),
        showProgressReceipt(2, 'EAW', ['kind' => 'comparison', 'rank' => 'cac_rkf_special', 'details' => $metadata]),
        showProgressReceipt(3, 'CAC', ['judgeId' => 'shared'])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'named_winner_and_other_cac'))[0];

    expect($path['ready'])->toBeTrue();
    expect($path['proof_title_ids'])->toBe([2, 3]);
});

test('a named winner shortcut with an existing CAC targets only the missing named winner', function () {
    $receipt = showProgressReceipt(1, 'CAC');

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), [$receipt], 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'named_winner_and_other_cac'))[0];

    expect(array_column($path['targets'], 'codes'))->toBe([['EAW', 'RW']]);
});

test('an annual club winner shortcut targets only its missing club certificate', function () {
    $receipt = showProgressReceipt(1, 'CLUB_WINNER', ['kind' => 'comparison', 'rank' => 'specialty', 'details' => [
        'specialShow' => 'national_club', 'showLeg' => '', 'showYear' => 2026, 'showEdition' => '2026-10',
    ]]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), [$receipt], 1000), 'CH_CLUB');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'annual_winner_and_other_club_certificate'))[0];

    expect($path['targets'])->toBe([['codes' => ['KCHK'], 'excludeJudges' => ['judge-1']]]);
});

test('the junior champion shortcut targets its missing junior title when adult certificates are complete', function () {
    $receipts = array_map(fn (int $id): array => showProgressReceipt($id, 'CAC'), [1, 2, 3, 4]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'junior_champion'))[0];

    expect($path['targets'])->toBe([['codes' => ['JCH_RUS']]]);
});

test('a new show edition starts without pinning recommendations to an empty or missed edition', function () {
    $receipt = showProgressReceipt(1, 'EAW', ['kind' => 'comparison', 'rank' => 'cac_rkf_special', 'details' => [
        'specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10',
    ]]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['showYear' => 2026, 'showEdition' => '2026-11']), [$receipt], 1000), 'EAAW');

    expect($goal['scope_key'])->toBe('edition:2026-11');
    expect($goal['paths'][0]['available_title_ids'])->toBe([]);
    expect($goal['paths'][0]['targets'][0])->not->toHaveKey('showEdition');
    expect($goal['paths'][0]['targets'][1]['showLeg'])->toBe(['cis']);
});

test('an issued absolute winner is achieved only for its own edition', function () {
    $receipt = showProgressReceipt(1, 'EAAW', ['kind' => 'championship', 'scope_key' => 'edition:2026-10']);
    $profile = showProgressProfile(['championships' => ['EAAW'], 'showYear' => 2026, 'showEdition' => '2026-10']);

    $current = showProgressGoal(showProgressRules()->progress($profile, [$receipt], 1000), 'EAAW');
    $next = showProgressGoal(showProgressRules()->progress([...$profile, 'showEdition' => '2026-11'], [$receipt], 1000), 'EAAW');

    expect($current['achieved'])->toBeTrue();
    expect($next['achieved'])->toBeFalse();
});

test('a consumed document is unavailable for a repeated grand title even when only the ledger is supplied', function () {
    $details = ['specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10'];
    $receipts = [showProgressReceipt(1, 'JCH_RKF_CERT', ['rank' => 'cac_rkf_special', 'details' => [...$details, 'used_for' => ['show:JGCH_RKF']]]),
        showProgressReceipt(2, 'JCH_RKF_CERT', ['rank' => 'cac_rkf_special', 'details' => $details])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(), $receipts, 1000), 'JGCH_RKF');

    expect($goal['paths'][0]['available_title_ids'])->toBe([2]);
    expect($goal['paths'][0]['excluded'])->toBe([['title_id' => 1, 'reason' => 'used']]);
    expect($goal['paths'][0]['ready'])->toBeFalse();
});

test('a breed grand title requires its saved breed and national documents', function () {
    $receipts = [showProgressReceipt(1, 'CH_RUS', ['kind' => 'championship']), showProgressReceipt(2, 'CH_BREED', ['kind' => 'championship'])];

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['has_nkp' => false, 'championships' => ['CH_RUS', 'CH_BREED']]), $receipts, 1000), 'GRAND_CH_RUS');

    expect($goal['paths'][0]['ready'])->toBeTrue();
    expect($goal['paths'][0]['proof_title_ids'])->toBe([1, 2]);
});

test('a composite show title targets only its missing championship component', function (string $goalCode, string $held, string $missing) {
    $receipt = showProgressReceipt(1, $held, ['kind' => 'championship']);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['championships' => [$held]]), [$receipt], 1000), $goalCode);

    expect($goal['paths'][0]['targets'])->toBe([['codes' => [$missing]]]);
})->with([
    'Russian grand champion' => ['GRAND_CH_RUS', 'CH_RUS', 'CH_CLUB'],
    'absolute RKF champion' => ['ACH_RKF', 'EAAW', 'RAW'],
]);

test('international agility progress includes the missing international show assessment', function () {
    $rules = new EventChampionshipRules(showProgressBalance());
    $receipts = [showProgressReceipt(1, 'CACIAG', ['discipline' => 'agility', 'country' => 'DE', 'awarded_at' => 1]),
        showProgressReceipt(2, 'CACIAG', ['discipline' => 'agility', 'country' => 'FR', 'awarded_at' => 13 * 86400])];

    $goal = showProgressGoal($rules->progress('agility', showProgressProfile(), $receipts, 14 * 86400), 'CIAG');

    expect(array_column($goal['paths'][0]['requirements'], null, 'key')['show_grade'])->toMatchArray(['current' => 0, 'required' => 1, 'met' => false]);
    expect($goal['paths'][0]['targets'][1]['codes'])->toBe(['SHOW_GRADE']);
    expect($goal['paths'][0]['ready'])->toBeFalse();
});

test('international agility progress becomes ready only with a saved qualifying assessment', function () {
    $rules = new EventChampionshipRules(showProgressBalance());
    $receipts = [showProgressReceipt(1, 'CACIAG', ['discipline' => 'agility', 'country' => 'DE', 'awarded_at' => 1]),
        showProgressReceipt(2, 'CACIAG', ['discipline' => 'agility', 'country' => 'FR', 'awarded_at' => 13 * 86400]),
        showProgressReceipt(3, 'SHOW_GRADE', ['kind' => 'qualification', 'rank' => 'cacib', 'details' => ['grade' => 'good']])];

    $goal = showProgressGoal($rules->progress('agility', showProgressProfile(), $receipts, 14 * 86400), 'CIAG');

    expect($goal['paths'][0]['ready'])->toBeTrue();
    expect($goal['paths'][0]['proof_title_ids'])->toBe([1, 2, 3]);
});

test('international agility does not recommend another show assessment when it is already recorded', function () {
    $rules = new EventChampionshipRules(showProgressBalance());
    $receipt = showProgressReceipt(1, 'SHOW_GRADE', ['kind' => 'qualification', 'rank' => 'cacib', 'details' => ['grade' => 'good']]);

    $goal = showProgressGoal($rules->progress('agility', showProgressProfile(), [$receipt], 1000), 'CIAG');

    expect(array_column($goal['paths'][0]['targets'], 'codes'))->toBe([['CACIAG']]);
});

test('an already issued one time title stays achieved rather than promising another issue', function () {
    $receipts = array_map(fn (int $id): array => showProgressReceipt($id, 'JCAC'), [1, 2, 3]);

    $goal = showProgressGoal(showProgressRules()->progress(showProgressProfile(['championships' => ['JCH_RUS']]), $receipts, 1000), 'JCH_RUS');

    expect($goal['achieved'])->toBeTrue();
    expect($goal['repeatable'])->toBeFalse();
    expect(array_column($goal['paths'], 'ready'))->not->toContain(true);
});

test('a Russian shortcut through a newly available junior title includes its certificate proof', function (array $juniorCodes) {
    $receipts = [];
    foreach ($juniorCodes as $index => $code) {
        $receipts[] = showProgressReceipt($index + 1, $code);
    }
    foreach (range(count($juniorCodes) + 1, count($juniorCodes) + 4) as $id) {
        $receipts[] = showProgressReceipt($id, 'CAC', ['rank' => 'cac_cf']);
    }
    $rules = showProgressRules();

    $goal = showProgressGoal($rules->progress(showProgressProfile(), $receipts, 1000), 'CH_RUS');
    $path = array_values(array_filter($goal['paths'], fn (array $path): bool => $path['key'] === 'junior_champion'))[0];
    $proofIds = $path['proof_title_ids'];
    sort($proofIds);
    $proof = array_values(array_filter($receipts, fn (array $receipt): bool => in_array($receipt['title_id'], $proofIds, true)));

    expect($path['ready'])->toBeTrue();
    expect($proofIds)->toBe(range(1, count($receipts)));
    expect(array_column($rules->calculate(showProgressProfile(), $proof, 1000), 'code'))->toContain('JCH_RUS', 'CH_RUS');
})->with([
    'direct certificates' => [['JCAC', 'JCAC', 'JCAC']],
    'paired reserve certificates' => [['JCAC', 'JCAC', 'R_JCAC', 'R_JCAC']],
]);
