<?php

use App\Modules\Pets\Calculators\GameEventAdmissionRules;
use App\Modules\Pets\Enums\GameEventDiscipline;

test('physical admission returns every blocking reason in priority order', function (array $changes, array $expected) {
    $pet = array_replace([
        'active' => true, 'retiresAt' => new DateTimeImmutable('2026-10-05 12:00:00 UTC'),
        'health' => 100.0, 'hasDisease' => false, 'energyPercentage' => 100.0, 'energy' => 100.0,
        'busy' => false, 'activityEndsAt' => null,
    ], $changes);
    $event = [
        'endsAt' => new DateTimeImmutable('2026-10-04 13:10:00 UTC'),
        'closesAt' => new DateTimeImmutable('2026-10-04 12:45:00 UTC'), 'energyCost' => 20.0,
    ];

    $reasons = (new GameEventAdmissionRules)->blockingReasons(GameEventDiscipline::Agility, $pet, $event);

    expect($reasons)->toBe($expected);
})->with([
    'ready' => [[], []],
    'archived' => [['active' => false], ['events.errors.archived']],
    'retires at event end' => [['retiresAt' => new DateTimeImmutable('2026-10-04 13:10:00 UTC')], ['events.errors.archived']],
    'retires before event end' => [['retiresAt' => new DateTimeImmutable('2026-10-04 13:09:59 UTC')], ['events.errors.archived']],
    'retires after event end' => [['retiresAt' => new DateTimeImmutable('2026-10-04 13:10:01 UTC')], []],
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
        'active' => false, 'retiresAt' => new DateTimeImmutable('2026-10-03 12:00:00 UTC'),
        'health' => 0.0, 'hasDisease' => true, 'energyPercentage' => 0.0, 'energy' => 0.0,
        'busy' => true, 'activityEndsAt' => null,
    ];
    $event = [
        'endsAt' => new DateTimeImmutable('2026-10-04 13:10:00 UTC'),
        'closesAt' => new DateTimeImmutable('2026-10-04 12:45:00 UTC'), 'energyCost' => 20.0,
    ];

    $reasons = (new GameEventAdmissionRules)->blockingReasons(GameEventDiscipline::Progeny, $pet, $event);

    expect($reasons)->toBe([]);
});
