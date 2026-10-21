<?php

use App\Modules\Pets\Calculators\EventChampionshipRules;

function regulatedCredential(int $id, string $code, string $country = 'RU', int $at = 0): array
{
    return ['entry_id' => $id, 'code' => $code, 'discipline' => 'conformation', 'awarded_at' => $at, 'judgeId' => 'judge-'.$id, 'country' => $country, 'region' => 'region-'.$id, 'rank' => 'cac_rkf', 'details' => []];
}

test('Russian adult championships require distinct judges and a game year', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true, 'working_breed' => false, 'championships' => []];
    $credentials = array_map(fn (int $id): array => regulatedCredential($id, 'CAC', at: $id * 100), [1, 2, 3, 4]);
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 1000), 'code'))->not->toContain('CH_RUS');
    $credentials[3]['awarded_at'] = 12 * 86400 + 100;
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 13 * 86400), 'code'))->toContain('CH_RUS');
    $credentials[3]['judgeId'] = 'judge-1';
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 13 * 86400), 'code'))->not->toContain('CH_RUS');
});

test('optional agility advancement preserves the lower class until the first higher start', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $credentials = [];
    foreach ([1, 2, 3] as $id) {
        $credentials[] = [...regulatedCredential($id, 'AGILITY_QUALIFICATION'), 'discipline' => 'agility', 'details' => ['level' => 1, 'faults' => $id === 3 ? 0.0 : 5.0, 'format' => 'agility']];
    }
    $progress = $rules->progression('agility', ['agility_level' => 1], $credentials);
    expect($progress['agility_level'])->toBe(1);
    expect($progress['agility_available_levels'])->toBe([1, 2]);
    $credentials[] = [...regulatedCredential(4, 'AGILITY_START'), 'discipline' => 'agility', 'details' => ['level' => 2, 'format' => 'agility']];
    expect($rules->progression('agility', $progress, $credentials)['agility_available_levels'])->toBe([2]);
});

test('international junior titles require three countries and judges without a yearly wait', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true, 'working_breed' => false, 'championships' => []];
    $credentials = [regulatedCredential(1, 'CACIB_J', 'DE'), regulatedCredential(2, 'CACIB_J', 'FR'), regulatedCredential(3, 'CACIB_J', 'ES')];
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 100), 'code'))->toContain('CIB_J');
    $credentials[2]['country'] = 'DE';
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 100), 'code'))->not->toContain('CIB_J');
});

test('international certificates remain valid beyond the Russian five year national exchange limit', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true, 'working_breed' => false, 'championships' => []];
    $credentials = [regulatedCredential(1, 'CACIB', 'DE'), regulatedCredential(2, 'CACIB', 'FR'), regulatedCredential(3, 'CACIB', 'ES'), regulatedCredential(4, 'CACIB', 'DE', 72 * 86400)];
    expect(array_column($rules->calculate('conformation', $profile, $credentials, 72 * 86400), 'code'))->toContain('CIB');
});

test('agility promotion accepts three clear agility runs under two judges and ignores jumping', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $credentials = [];
    foreach ([1, 2, 3] as $id) {
        $credentials[] = [...regulatedCredential($id, 'AGILITY_QUALIFICATION'), 'discipline' => 'agility', 'details' => ['level' => 1, 'faults' => 0.0, 'format' => 'agility']];
    }
    expect($rules->progression('agility', ['agility_level' => 1], $credentials)['agility_level'])->toBe(2);
    $credentials[2]['details']['format'] = 'jumping';
    expect($rules->progression('agility', ['agility_level' => 1], $credentials)['agility_level'])->toBe(1);
});

test('international agility titles require a documented FCI international show grade and complete registration', function () {
    $rules = new EventChampionshipRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = ['registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true, 'championships' => []];
    $credentials = [
        [...regulatedCredential(1, 'CACIAG', 'DE'), 'discipline' => 'agility'],
        [...regulatedCredential(2, 'CACIAG', 'FR', 12 * 86400), 'discipline' => 'agility'],
        [...regulatedCredential(3, 'SHOW_GRADE', 'RU'), 'details' => ['grade' => 'good']],
    ];
    expect(array_column($rules->calculate('agility', $profile, $credentials, 13 * 86400), 'code'))->not->toContain('CIAG');
    $credentials[2]['rank'] = 'cacib';
    expect(array_column($rules->calculate('agility', $profile, $credentials, 13 * 86400), 'code'))->toContain('CIAG');
    expect(array_column($rules->calculate('agility', [...$profile, 'registered' => false], $credentials, 13 * 86400), 'code'))->not->toContain('CIAG');
    expect(array_column($rules->calculate('agility', [...$profile, 'pedigree_complete' => false], $credentials, 13 * 86400), 'code'))->not->toContain('CIAG');
    $credentials[2]['awarded_at'] = 14 * 86400;
    expect(array_column($rules->calculate('agility', $profile, $credentials, 13 * 86400), 'code'))->not->toContain('CIAG');
});
