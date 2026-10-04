<?php

use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;

/** @return array<string, mixed> */
function competitionAmmunitionFixture(): array
{
    return ['slot' => 'body', 'disciplines' => ['canicross'], 'phase' => 'performance',
        'sizes' => ['medium', 'large'], 'modifiers' => ['stamina' => 0.09, 'pace' => -0.05],
        'description' => ['ru' => 'Ровный бег', 'en' => 'Steady running']];
}

test('ammunition compatibility includes discipline size and phase', function (string $discipline, string $size, string $phase, bool $supported) {
    expect((new CompetitionAmmunitionRules)->supports(competitionAmmunitionFixture(), $discipline, $size, $phase))->toBe($supported);
})->with([
    'matching outfit' => ['canicross', 'medium', 'performance', true],
    'different discipline' => ['agility', 'medium', 'performance', false],
    'wrong size' => ['canicross', 'small', 'performance', false],
    'wrong phase' => ['canicross', 'medium', 'preparation', false],
]);

test('unsafe or incomplete ammunition metadata cannot be equipped', function (array $changes) {
    expect((new CompetitionAmmunitionRules)->metadata(['competition' => array_replace(competitionAmmunitionFixture(), $changes)]))->toBeNull();
})->with([
    'unknown slot' => [['slot' => 'collar']],
    'invented discipline' => [['disciplines' => ['speed_challenge']]],
    'empty discipline' => [['disciplines' => []]],
    'duplicate discipline' => [['disciplines' => ['canicross', 'canicross']]],
    'invalid size' => [['sizes' => ['tiny']]],
    'unknown metric' => [['modifiers' => ['strength' => 0.05]]],
    'too strong bonus' => [['modifiers' => ['pace' => 0.11]]],
    'too strong penalty' => [['modifiers' => ['pace' => -0.09]]],
    'not a numeric bonus' => [['modifiers' => ['pace' => '0.05']]],
    'nonfinite bonus' => [['modifiers' => ['pace' => NAN]]],
    'excess total modifiers' => [['modifiers' => ['pace' => 0.10, 'focus' => 0.10]]],
    'missing description' => [['description' => ['ru' => 'Подготовка']]],
    'blank description' => [['description' => ['ru' => ' ', 'en' => 'Steady running']]],
    'preparation gear on the course' => [['slot' => 'preparation']],
]);

test('ordinary items provide no competition modifiers', function () {
    expect((new CompetitionAmmunitionRules)->metadata(['mood' => 10]))->toBeNull();
});
