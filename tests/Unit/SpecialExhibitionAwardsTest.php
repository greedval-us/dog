<?php

use App\Modules\Pets\Calculators\RegulatedExhibitionAwards;
use App\Modules\Pets\Calculators\SpecialExhibitionAwards;

function specialExhibitionBalance(): array
{
    return [...require dirname(__DIR__, 2).'/config/event_regulations.php', 'special_exhibitions' => require dirname(__DIR__, 2).'/config/special_exhibitions.php'];
}

function specialExhibitionRegulation(string $show = 'eurasia', string $leg = 'president'): array
{
    return ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'cac_rkf_special', 'specialShow' => $show, 'showLeg' => $leg, 'showYear' => 2026, 'showEdition' => '2026-10'];
}

function specialExhibitionEntry(int $id, string $class, string $sex = 'male', int $score = 95, array $profile = []): array
{
    return ['id' => $id, 'rank' => $id, 'plan' => ['exhibition_class' => $class], 'snapshot' => ['profile' => ['breed_id' => 1, 'breed_code' => 'labrador', 'sex' => $sex, 'registered' => true, 'recognized_rkf' => true, 'has_nkp' => true, 'fci_group' => 8, ...$profile]], 'result' => ['qualified' => true, 'grade' => 'excellent', 'score' => $score]];
}

test('Eurasia awards winners to each sex while every adult class can earn a doubled CAC', function () {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);
    $entries = [specialExhibitionEntry(1, 'open'), specialExhibitionEntry(2, 'champion', score: 90), specialExhibitionEntry(3, 'open', 'female'), specialExhibitionEntry(4, 'junior'), specialExhibitionEntry(5, 'junior', 'female')];

    $awards = $rules->calculate(specialExhibitionRegulation(), $entries);

    expect(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === 'EAW'), 'entry_id'))->toBe([1, 3]);
    expect(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === 'EAJW'), 'entry_id'))->toBe([4, 5]);
    $cac = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === 'CAC'));
    expect(array_column($cac, 'entry_id'))->toBe([1, 2, 3]);
    expect($cac[0]['details']['national_certificate_weight'])->toBe(2);
    expect(array_column($awards, 'code'))->not->toContain('CACIB');
});

test('special rights require trusted national metadata', function (array $changes) {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);

    $awards = $rules->calculate([...specialExhibitionRegulation(), ...$changes], [specialExhibitionEntry(1, 'open')]);

    expect(array_column($awards, 'code'))->not->toContain('EAW', 'KCHK');
})->with(['foreign' => [['country' => 'DE']], 'international' => [['system' => 'fci']], 'wrong rank' => [['rank' => 'cac_rkf']], 'unknown leg' => [['showLeg' => 'invented']], 'legacy' => [['version' => 1]], 'missing edition' => [['showEdition' => null]]]);

test('specialties keep breed certificates separate and exclude German shepherds', function () {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);

    $breed = $rules->calculate(specialExhibitionRegulation(), [specialExhibitionEntry(1, 'open', profile: ['has_nkp' => false])]);
    $excluded = $rules->calculate(specialExhibitionRegulation(), [specialExhibitionEntry(1, 'open', profile: ['breed_code' => 'german_shepherd'])]);

    expect(array_column($breed, 'code'))->toContain('KCHP')->not->toContain('KCHK');
    expect(array_column($excluded, 'code'))->toContain('EAW')->not->toContain('KCHK', 'KCHP');
});

test('annual club winners include club champions without giving their class candidate certificates', function () {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);
    $regulation = [...specialExhibitionRegulation('national_club', ''), 'rank' => 'specialty', 'breedId' => 1];

    $awards = $rules->calculate($regulation, [specialExhibitionEntry(1, 'club_champion'), specialExhibitionEntry(2, 'open', score: 90)]);

    expect(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === 'CLUB_WINNER'), 'entry_id'))->toBe([1]);
    expect(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === 'KCHK'), 'entry_id'))->toBe([2]);
});

test('annual club shows award candidate certificates in each adult class and reject unrelated breeds', function () {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);
    $regulation = [...specialExhibitionRegulation('national_club', ''), 'rank' => 'specialty', 'breedId' => 1];

    $awards = $rules->calculate($regulation, [specialExhibitionEntry(1, 'open'), specialExhibitionEntry(2, 'champion', score: 90), specialExhibitionEntry(3, 'open', profile: ['breed_id' => 2])]);

    expect(array_column(array_filter($awards, fn (array $award): bool => $award['code'] === 'KCHK'), 'entry_id'))->toBe([1, 2]);
    expect(array_column($awards, 'entry_id'))->not->toContain(3);
    expect(array_column($awards, 'code'))->not->toContain('CAC', 'CH_RKF_CERT');
});

test('Russia Moscow leg and CIS leg have their own winner identities', function (string $series, string $leg, array $expected, string $absent) {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);

    $awards = $rules->calculate(specialExhibitionRegulation($series, $leg), [specialExhibitionEntry(1, 'open')]);

    expect(array_column($awards, 'code'))->toContain(...$expected)->not->toContain($absent);
})->with([['russia', 'moscow_cup', ['MW'], 'RW'], ['eurasia', 'cis', ['EAW', 'CIS_W'], 'RW']]);

test('ordinary breed rings distinguish breed candidate certificates from club certificates', function (string $class, string $candidate, string $reserve) {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);
    $regulation = ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'specialty', 'breedId' => 1];
    $entries = [specialExhibitionEntry(1, $class, profile: ['has_nkp' => false]), specialExhibitionEntry(2, $class, score: 90, profile: ['has_nkp' => false])];

    $awards = $rules->calculate($regulation, $entries);

    expect(array_column($awards, 'code'))->toContain($candidate, $reserve)->not->toContain('KCHK', 'JKCHK', 'VKCHK', 'R_KCHK', 'R_JKCHK', 'R_VKCHK');
})->with([['open', 'KCHP', 'CC_BREED'], ['junior', 'JKCHP', 'JCC_BREED'], ['veteran', 'VKCHP', 'VCC_BREED']]);

test('ordinary legacy breed rings retain their frozen club certificate protocol', function () {
    $balance = specialExhibitionBalance();
    $rules = new SpecialExhibitionAwards(new RegulatedExhibitionAwards($balance), $balance);

    $awards = $rules->calculate(['version' => 1, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'specialty'], [specialExhibitionEntry(1, 'open', profile: ['has_nkp' => false])]);

    expect(array_column($awards, 'code'))->toContain('KCHK')->not->toContain('KCHP');
});
