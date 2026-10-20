<?php

use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Calculators\PetEventProfileRules;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Generators\GameEventNpcGenerator;

function regulatedProfile(array $changes = []): array
{
    return array_replace(['id' => 1, 'name' => 'Dog', 'breed_id' => 7, 'breed_code' => 'beagle', 'sex' => 'male', 'size' => 'medium', 'height_cm' => 40, 'height_category' => 'M', 'recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 6, 'registered' => true, 'pedigree_complete' => true, 'working_breed' => true, 'working_certificate' => false, 'championships' => [], 'agility_level' => 1, 'nosework_levels' => ['pr1' => 0, 'pr2' => 0], 'nosework_tests' => ['pr1' => [], 'pr2' => []], 'nosework_nw0_qualifications' => ['pr1' => 0, 'pr2' => 0], 'race_category' => '3'], $changes);
}

test('new recognized registered pedigree dogs enter the pedigree canicross category', function (bool $purebred, bool $recognized, string $expected) {
    $rules = new PetEventProfileRules;
    $breed = $rules->breedProfile(['recognized_rkf' => $recognized], 'large');
    $profile = $rules->initialSnapshot('runner', 'male', $purebred, $breed);

    expect($profile['race_category'])->toBe($expected);
})->with(['recognized pedigree' => [true, true, '3'], 'unrecognized breed' => [true, false, '4'], 'unregistered mixed dog' => [false, true, '4']]);

test('club opponents hold the credentials of their selected show ring without inheriting player titles', function (string $class, array $expected) {
    $profile = regulatedProfile(['championships' => ['CH_RUS', 'CH_CLUB', 'CIB', 'CIAG']]);
    $reference = ['breed' => 'beagle', 'breed_id' => 7, 'size' => 'medium', 'age_months' => 30, 'sex' => 'male', 'exhibition_class' => $class, 'profile' => $profile];
    $snapshot = (new GameEventNpcGenerator(new GameEventRandomness))->generate($reference, $class.':breed-7:male', 'npc-credentials', 1);

    expect($snapshot['profile']['championships'])->toBe($expected);
    expect($snapshot['profile']['id'])->toBeNull();
    expect((new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php'))->validPlan(
        'conformation', ['system' => 'rkf', 'rank' => 'specialty'], $snapshot['profile'], 30,
        ['exhibition_class' => $class, 'stages' => ['balanced', 'balanced', 'balanced']],
    ))->toBeTrue();
})->with(['open' => ['open', []], 'champion' => ['champion', ['CH_RUS']], 'club champion' => ['club_champion', ['CH_CLUB']]]);

test('adult show choices require their documents while veterans remain eligible beyond ten years', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $options = $rules->entryOptions('conformation', ['system' => 'rkf', 'rank' => 'cac_rkf', 'format' => 'conformation'], regulatedProfile(), 130);
    $eligible = array_column(array_filter($options, fn (array $option): bool => $option['eligible']), 'key');
    expect($eligible)->toBe(['open', 'veteran']);
    expect($rules->validPlan('conformation', ['system' => 'rkf'], regulatedProfile(), 20, ['exhibition_class' => 'working', 'stages' => ['balanced', 'balanced', 'balanced']]))->toBeFalse();
});

test('certificate nosework events keep lower classes with their own standings', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = regulatedProfile(['nosework_tests' => ['pr1' => ['birch'], 'pr2' => []]]);
    $regulation = ['system' => 'rkf', 'rank' => 'certificate', 'format' => 'nosework'];
    expect($rules->validPlan('nosework', $regulation, $profile, 6, ['sport_class' => 'nw_0', 'program' => 'pr1', 'stages' => array_fill(0, 4, 'balanced')]))->toBeTrue();
});

test('agility uses heights and earned levels instead of dog age bands', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $regulation = ['system' => 'rkf', 'rank' => 'qualifying', 'format' => 'agility'];
    $profile = regulatedProfile(['agility_level' => 2]);
    expect($rules->division('agility', $regulation, $profile, ['sport_class' => 'agility_2']))->toBe('agility_2:M');
    expect($rules->minimumMonths('agility', $regulation, []))->toBe(18);
    expect($rules->minimumMonths('agility', [...$regulation, 'system' => 'fci'], []))->toBe(24);
    expect($rules->validPlan('agility', $regulation, $profile, 130, ['sport_class' => 'agility_1', 'stages' => ['balanced', 'balanced', 'balanced']]))->toBeFalse();
});

test('nosework veterans require all program odors and may return to their regular class', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = regulatedProfile(['nosework_levels' => ['pr1' => 1, 'pr2' => 0], 'nosework_tests' => ['pr1' => ['birch', 'lavender', 'anise'], 'pr2' => []], 'nosework_nw0_qualifications' => ['pr1' => 2, 'pr2' => 0]]);
    $regulation = ['system' => 'rkf', 'rank' => 'qualifying', 'format' => 'nosework'];
    $plan = ['sport_class' => 'nw_v', 'program' => 'pr1', 'stages' => ['balanced', 'balanced', 'balanced', 'balanced']];
    expect($rules->validPlan('nosework', $regulation, $profile, 96, $plan))->toBeTrue();
    expect($rules->validPlan('nosework', $regulation, $profile, 95, $plan))->toBeFalse();
    expect($rules->validPlan('nosework', $regulation, $profile, 100, [...$plan, 'sport_class' => 'nw_1']))->toBeTrue();
    expect($rules->validPlan('nosework', $regulation, $profile, 100, [...$plan, 'program' => 'pr2']))->toBeFalse();
    expect($rules->stages('nosework', [...$regulation, 'rank' => 'certificate'], ['sport_class' => 'nw_3', 'program' => 'pr1']))->toHaveCount(4);
});

test('canicross splits by breed registry category and admits fifteen month dogs', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    expect($rules->minimumMonths('canicross', ['system' => 'rkf'], []))->toBe(15);
    expect($rules->division('canicross', ['system' => 'rkf'], regulatedProfile(['race_category' => '4']), ['sport_class' => 'canicross_open']))->toBe('canicross_open:4:open');
});

test('plans reject foreign discipline fields and odor choices outside a recognition test', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $profile = regulatedProfile(['nosework_tests' => ['pr1' => ['birch'], 'pr2' => []]]);
    $agility = ['sport_class' => 'agility_1', 'stages' => array_fill(0, 3, 'balanced'), 'exhibition_class' => 'baby'];
    expect($rules->validPlan('agility', ['format' => 'agility'], $profile, 18, $agility))->toBeFalse();
    $nosework = ['sport_class' => 'nw_0', 'program' => 'pr1', 'test_type' => 'birch', 'stages' => array_fill(0, 4, 'balanced')];
    expect($rules->validPlan('nosework', ['format' => 'nosework'], $profile, 6, $nosework))->toBeFalse();
    expect($rules->validPlan('conformation', [], $profile, 18, ['exhibition_class' => ['open'], 'stages' => array_fill(0, 3, 'balanced')]))->toBeFalse();
});

test('progeny plans select three to five distinct first generation candidates', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $plan = ['offspring_ids' => [1, 2, 3], 'stages' => array_fill(0, 3, 'balanced')];
    expect($rules->validPlan('progeny', [], regulatedProfile(), 24, $plan))->toBeTrue();
    expect($rules->validPlan('progeny', [], regulatedProfile(), 24, [...$plan, 'offspring_ids' => [1, 1, 2]]))->toBeFalse();
    expect($rules->validPlan('progeny', [], regulatedProfile(), 24, [...$plan, 'offspring_ids' => [1, 2]]))->toBeFalse();
});

test('champion rings require national or FCI titles and club champions have a separate specialty ring', function () {
    $rules = new RegulatedEventRules(require dirname(__DIR__, 2).'/config/event_regulations.php');
    $plan = ['exhibition_class' => 'champion', 'stages' => array_fill(0, 3, 'balanced')];
    foreach (['CH_RKF', 'CH_CLUB'] as $title) {
        expect($rules->validPlan('conformation', ['rank' => 'cac_rkf'], regulatedProfile(['championships' => [$title]]), 24, $plan))->toBeFalse();
    }
    foreach (['CH_RUS', 'CIB', 'CIE'] as $title) {
        expect($rules->validPlan('conformation', ['rank' => 'cacib'], regulatedProfile(['championships' => [$title]]), 24, $plan))->toBeTrue();
    }
    $club = [...$plan, 'exhibition_class' => 'club_champion'];
    expect($rules->validPlan('conformation', ['rank' => 'specialty'], regulatedProfile(['championships' => ['CH_CLUB']]), 24, $club))->toBeTrue();
    expect($rules->validPlan('conformation', ['rank' => 'cac_rkf'], regulatedProfile(['championships' => ['CH_CLUB']]), 24, $club))->toBeFalse();
    expect($rules->validPlan('conformation', ['rank' => 'specialty'], regulatedProfile(['championships' => ['CH_RUS']]), 24, $club))->toBeFalse();
});
