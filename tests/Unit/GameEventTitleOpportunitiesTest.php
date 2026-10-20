<?php

use App\Modules\Pets\Calculators\GameEventTitleOpportunities;
use Tests\TestCase;

uses(TestCase::class);

function progressOpportunityProfile(array $changes = []): array
{
    return [...[
        'id' => 1, 'breed_id' => 1, 'breed_code' => 'german_shepherd', 'sex' => 'male',
        'registered' => true, 'recognized_rkf' => true, 'recognized_fci' => true, 'pedigree_complete' => true,
        'working_breed' => true, 'working_certificate' => false, 'has_nkp' => true, 'club_system' => 'nkp',
        'height_category' => 'M', 'race_category' => '3', 'fci_group' => 1, 'championships' => [],
    ], ...$changes];
}

test('title opportunities respect the actual special show metadata and breed exceptions', function () {
    $regulation = ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'cac_rkf_special',
        'specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2050, 'showEdition' => '2050-05'];
    $planner = app(GameEventTitleOpportunities::class);

    $codes = $planner->codes('conformation', $regulation, progressOpportunityProfile(), ['exhibition_class' => 'open']);

    expect($codes)->toHaveKeys(['CAC', 'EAW', 'CH_RKF_CERT']);
    expect($codes)->not->toHaveKey('KCHK');
    expect($planner->codes('conformation', [...$regulation, 'showLeg' => 'invented'], progressOpportunityProfile(), ['exhibition_class' => 'open']))
        ->not->toHaveKey('EAW');
});

test('international and national agility opportunities use their own real course formats', function () {
    $planner = app(GameEventTitleOpportunities::class);
    $regulation = ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'certificate', 'format' => 'agility_combined'];

    $national = $planner->codes('agility', $regulation, progressOpportunityProfile(), ['sport_class' => 'agility_3']);
    $international = $planner->codes('agility', [...$regulation, 'system' => 'fci', 'country' => 'DE', 'format' => 'agility'], progressOpportunityProfile(), ['sport_class' => 'agility_3']);

    expect($national['CACAg'])->toMatchArray(['rank' => 1, 'minimumParticipants' => 6]);
    expect($national['REG_CACAg']['rank'])->toBe(3);
    expect($national)->not->toHaveKey('CACIAG');
    expect($international['CACIAG']['minimumParticipants'])->toBe(15);
    expect($planner->codes('agility', [...$regulation, 'format' => 'agility'], progressOpportunityProfile(), ['sport_class' => 'agility_3']))
        ->not->toHaveKey('CACAg');
    expect($planner->codes('agility', $regulation, progressOpportunityProfile(), ['sport_class' => 'agility_1']))
        ->not->toHaveKey('CACAg');
});

test('legacy sport opportunities do not inherit expanded participant minimums', function (string $system, string $code) {
    $regulation = ['version' => 1, 'system' => $system, 'country' => 'RU', 'rank' => 'certificate', 'format' => 'agility'];

    $codes = app(GameEventTitleOpportunities::class)->codes('agility', $regulation, progressOpportunityProfile(), ['sport_class' => 'agility_3']);

    expect($codes[$code])->toMatchArray(['rank' => 1, 'minimumParticipants' => 0]);
})->with([
    'national certificate' => ['rkf', 'CACAg'],
    'international certificate' => ['fci', 'CACIAG'],
]);

test('breed sport certificates do not imply regional or general certificates', function () {
    $codes = app(GameEventTitleOpportunities::class)->codes('agility', [
        'version' => 2, 'system' => 'rkf', 'country' => 'RU', 'rank' => 'certificate',
        'format' => 'agility_combined', 'sportScope' => 'breed', 'breedId' => 1,
    ], progressOpportunityProfile(), ['sport_class' => 'agility_3']);

    expect($codes)->toHaveKey('CACAg_BREED');
    expect($codes)->not->toHaveKeys(['CACAg', 'REG_CACAg']);
});
