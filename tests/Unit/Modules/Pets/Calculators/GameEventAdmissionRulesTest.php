<?php

use App\Modules\Pets\Calculators\GameEventAdmissionRules;
use App\Modules\Pets\Enums\GameEventDiscipline;

test('physical admission returns every blocking reason in priority order', function (array $changes, array $expected) {
    $pet = array_replace([
        'active' => true, 'retired' => false, 'ageMonths' => 18, 'retiresAt' => new DateTimeImmutable('2026-10-05 12:00:00 UTC'),
        'health' => 100.0, 'hasDisease' => false, 'energyPercentage' => 100.0, 'energy' => 100.0,
        'busy' => false, 'activityEndsAt' => null,
    ], $changes);
    $event = [
        'startsAt' => new DateTimeImmutable('2026-10-04 13:00:00 UTC'),
        'closesAt' => new DateTimeImmutable('2026-10-04 12:45:00 UTC'), 'energyCost' => 20.0,
    ];

    $reasons = (new GameEventAdmissionRules)->blockingReasons(GameEventDiscipline::Agility, $pet, $event);

    expect($reasons)->toBe($expected);
})->with([
    'ready' => [[], []],
    'archived' => [['active' => false], ['events.errors.archived']],
    'legacy automatic retirement at event start does not end an active career' => [['retiresAt' => new DateTimeImmutable('2026-10-04 13:00:00 UTC')], []],
    'legacy automatic retirement before event start does not end an active career' => [['retiresAt' => new DateTimeImmutable('2026-10-04 12:59:59 UTC')], []],
    'retires during event' => [['retiresAt' => new DateTimeImmutable('2026-10-04 13:00:01 UTC')], []],
    'too young for sport' => [['ageMonths' => 17], ['events.errors.young']],
    'senior dog keeps an active career' => [['ageMonths' => 96], []],
    'voluntary career retirement' => [['retired' => true], ['events.errors.archived']],
    'health below minimum' => [['health' => 59.99], ['events.errors.health']],
    'health at minimum' => [['health' => 60.0], []],
    'active disease despite full health' => [['hasDisease' => true], ['events.errors.health']],
    'energy percentage below minimum' => [['energyPercentage' => 34.99], ['events.errors.energy']],
    'energy below performance cost' => [['energy' => 19.99], ['events.errors.energy']],
    'energy at both minimums' => [['energyPercentage' => 35.0, 'energy' => 20.0], []],
    'activity without end' => [['busy' => true], ['events.errors.busy']],
    'activity after registration closes' => [['busy' => true, 'activityEndsAt' => new DateTimeImmutable('2026-10-04 12:45:01 UTC')], ['events.errors.busy']],
    'activity at registration close' => [['busy' => true, 'activityEndsAt' => new DateTimeImmutable('2026-10-04 12:45:00 UTC')], []],
    'activity before registration close' => [['busy' => true, 'activityEndsAt' => new DateTimeImmutable('2026-10-04 12:44:59 UTC')], []],
    'all reasons retain their priority' => [['active' => false, 'health' => 50.0, 'energyPercentage' => 10.0, 'busy' => true], [
        'events.errors.archived', 'events.errors.health', 'events.errors.energy', 'events.errors.busy',
    ]],
]);

test('documentary admission ignores the physical condition and activity of the parent', function () {
    $pet = [
        'active' => true, 'retired' => false, 'ageMonths' => 30, 'retiresAt' => new DateTimeImmutable('2026-10-05 12:00:00 UTC'),
        'health' => 0.0, 'hasDisease' => true, 'energyPercentage' => 0.0, 'energy' => 0.0,
        'busy' => true, 'activityEndsAt' => null,
    ];
    $event = [
        'startsAt' => new DateTimeImmutable('2026-10-04 13:00:00 UTC'),
        'closesAt' => new DateTimeImmutable('2026-10-04 12:45:00 UTC'), 'energyCost' => 20.0,
    ];

    $reasons = (new GameEventAdmissionRules)->blockingReasons(GameEventDiscipline::Progeny, $pet, $event);

    expect($reasons)->toBe([]);
});
