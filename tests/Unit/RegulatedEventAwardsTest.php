<?php

use App\Modules\Pets\Calculators\RegulatedExhibitionAwards;
use App\Modules\Pets\Calculators\RegulatedSportAwards;

function regulatedShowEntry(int $id, string $class, string $sex, float $score, string $grade = 'excellent', int $breed = 7): array
{
    return ['id' => $id, 'division' => $class.':breed-'.$breed.':'.$sex, 'plan' => ['exhibition_class' => $class], 'snapshot' => ['profile' => ['sex' => $sex, 'breed_id' => $breed, 'fci_group' => 6, 'recognized_fci' => true, 'recognized_rkf' => true, 'registered' => true, 'pedigree_complete' => true]], 'result' => ['score' => $score, 'grade' => $grade, 'qualified' => in_array($grade, ['excellent', 'very_good']), 'eliminated' => false]];
}

test('ordinary shows award one adult CAC per sex and international shows award each excellent class winner', function () {
    $awards = new RegulatedExhibitionAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $entries = [regulatedShowEntry(1, 'open', 'male', 90), regulatedShowEntry(2, 'intermediate', 'male', 95), regulatedShowEntry(3, 'open', 'female', 91)];
    $national = $awards->calculate(['system' => 'rkf', 'rank' => 'cac_rkf'], $entries);
    $international = $awards->calculate(['system' => 'fci', 'rank' => 'cacib'], $entries);
    expect(array_column(array_filter($national, fn (array $award): bool => $award['code'] === 'CAC'), 'entry_id'))->toBe([2, 3]);
    expect(array_column(array_filter($international, fn (array $award): bool => $award['code'] === 'CAC'), 'entry_id'))->toEqualCanonicalizing([1, 2, 3]);
    expect(array_column(array_filter($international, fn (array $award): bool => $award['code'] === 'CACIB'), 'entry_id'))->toBe([2, 3]);
});

test('adult show grades are recorded for international agility eligibility without unearned places', function () {
    $awards = new RegulatedExhibitionAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $good = $awards->calculate(['system' => 'rkf', 'rank' => 'qualifying'], [regulatedShowEntry(1, 'open', 'male', 70, 'good')]);
    $poor = $awards->calculate(['system' => 'rkf', 'rank' => 'qualifying'], [regulatedShowEntry(2, 'open', 'male', 40, 'satisfactory')]);
    expect(array_column($good, 'code'))->toBe(['SHOW_GRADE']);
    expect($poor)->toBe([]);
});

test('foreign international shows issue FCI certificates without Russian national certificates', function () {
    $awards = new RegulatedExhibitionAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $result = $awards->calculate(['system' => 'fci', 'rank' => 'cacib', 'country' => 'DE'], [regulatedShowEntry(1, 'open', 'male', 90), regulatedShowEntry(2, 'junior', 'female', 95)]);
    expect(array_column($result, 'code'))->toContain('CACIB', 'CACIB_J');
    expect(array_column($result, 'code'))->not->toContain('CAC', 'JCAC', 'CH_RKF_CERT', 'JCH_RKF_CERT');
});

test('club champions compete for breed comparisons while club certificates stay with eligible classes', function () {
    $awards = new RegulatedExhibitionAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $entries = [regulatedShowEntry(1, 'club_champion', 'male', 99), regulatedShowEntry(2, 'club_champion', 'male', 98), regulatedShowEntry(3, 'open', 'male', 95), regulatedShowEntry(4, 'intermediate', 'male', 94)];
    $result = $awards->calculate(['system' => 'rkf', 'rank' => 'specialty', 'country' => 'RU'], $entries);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'BOB'), 'entry_id'))->toBe([1]);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'KCHK'), 'entry_id'))->toBe([3]);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'R_KCHK'), 'entry_id'))->toBe([4]);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['kind'] === 'certificate'), 'entry_id'))->not->toContain(1, 2);
});

test('failed starts still prevent competitors returning to a lower earned class', function () {
    $awards = new RegulatedSportAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $entry = ['id' => 1, 'division' => 'agility_2:M', 'rank' => null, 'plan' => ['sport_class' => 'agility_2'], 'snapshot' => ['profile' => []], 'result' => ['qualified' => false, 'eliminated' => true]];
    $result = $awards->calculate('agility', ['system' => 'rkf', 'rank' => 'qualifying', 'format' => 'agility'], [$entry]);
    expect(array_column($result, 'code'))->toBe(['AGILITY_START']);
    expect($result[0]['details'])->toBe(['level' => 2, 'format' => 'agility']);
});

test('show places require minimum grades and best of breed compares juniors adults and veterans', function () {
    $awards = new RegulatedExhibitionAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $entries = [regulatedShowEntry(1, 'open', 'male', 70, 'good'), regulatedShowEntry(2, 'junior', 'male', 96), regulatedShowEntry(3, 'veteran', 'female', 98), regulatedShowEntry(4, 'open', 'female', 90)];
    $result = $awards->calculate(['system' => 'rkf', 'rank' => 'cac_rkf'], $entries);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['kind'] === 'placement'), 'entry_id'))->not->toContain(1);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'BOB'), 'entry_id'))->toBe([3]);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'BOS'), 'entry_id'))->toBe([2]);
});

test('sports title events give ordinary certificates to second place instead of the winner', function () {
    $awards = new RegulatedSportAwards(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $entries = [];
    foreach ([1, 2, 3] as $id) {
        $entries[] = ['id' => $id, 'division' => 'agility_3:M', 'rank' => $id, 'plan' => ['sport_class' => 'agility_3'], 'snapshot' => ['profile' => ['registered' => $id !== 3, 'recognized_rkf' => true]], 'result' => ['qualified' => true, 'eliminated' => false, 'total_faults' => 0.0, 'time' => 30.0 + $id]];
    }
    $result = $awards->calculate('agility', ['system' => 'rkf', 'rank' => 'championship', 'format' => 'agility'], $entries);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'SPORT_CH_RUS'), 'entry_id'))->toBe([1]);
    expect(array_column(array_filter($result, fn (array $award): bool => $award['code'] === 'CACAg'), 'entry_id'))->toBe([2]);
});
