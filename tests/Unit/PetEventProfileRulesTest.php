<?php

use App\Modules\Pets\Calculators\PetEventProfileRules;

test('breed club registry requires explicit metadata and supports the RSPU registry', function (array $metadata, string $expected) {
    $profile = (new PetEventProfileRules)->breedProfile($metadata, 'large');

    expect($profile['club_system'])->toBe($expected);
})->with([
    'known national breed club' => [['recognized_rkf' => true, 'club_system' => 'nkp'], 'nkp'],
    'recognized breed without a known club' => [['recognized_rkf' => true], 'breed'],
    'unrecognized breed' => [['recognized_rkf' => false], 'none'],
    'explicit RSPU registry' => [['recognized_rkf' => true, 'club_system' => 'rspu'], 'rspu'],
    'explicit no club registry' => [['recognized_rkf' => true, 'club_system' => 'none'], 'none'],
    'unsupported registry does not invent a club' => [['recognized_rkf' => true, 'club_system' => 'unknown'], 'breed'],
]);

test('new pet profiles preserve their own registry when breed defaults later change', function () {
    $rules = new PetEventProfileRules;
    $breed = $rules->breedProfile(['recognized_rkf' => true, 'club_system' => 'nkp'], 'large');

    $profile = $rules->initialSnapshot('pet-one', 'male', true, $breed, ['club_system' => 'rspu']);

    expect($profile['club_system'])->toBe('rspu');
});
