<?php

use App\Modules\Pets\Calculators\PetAgeRules;

test('game months advance after complete elapsed days independently of calendar months', function () {
    $rules = new PetAgeRules;
    $birth = new DateTimeImmutable('2026-01-31T12:00:00+00:00');

    expect($rules->monthsAt($birth, new DateTimeImmutable('2026-02-01T11:59:59+00:00')))->toBe(0);
    expect($rules->monthsAt($birth, new DateTimeImmutable('2026-02-01T12:00:00+00:00')))->toBe(1);
    expect($rules->monthsAt($birth, new DateTimeImmutable('2026-05-07T12:00:00+00:00')))->toBe(96);
});

test('age before birth stays at zero', function () {
    $rules = new PetAgeRules;

    expect($rules->monthsAt(new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-01')))->toBe(0);
});

test('lifespan is stable within the breed range', function () {
    $rules = new PetAgeRules;
    $balance = ['lifespans' => ['large' => [144, 168]], 'breeds' => ['akita' => [150, 155]]];

    $lifespan = $rules->lifespanMonths('same-dog', 'large', 'akita', $balance);

    expect($lifespan)->toBeGreaterThanOrEqual(150)->toBeLessThanOrEqual(155);
    expect($rules->lifespanMonths('same-dog', 'large', 'akita', $balance))->toBe($lifespan);
});

test('senior physical decline grows gradually without affecting mental skills', function () {
    $rules = new PetAgeRules;

    expect($rules->agingModifiers(95, 168))->toBe([]);
    $early = $rules->agingModifiers(108, 168);
    $late = $rules->agingModifiers(156, 168);

    expect($early['energy_recovery_percent'])->toBeLessThan(0);
    expect($late['energy_recovery_percent'])->toBeLessThan($early['energy_recovery_percent']);
    expect($late['speed_decay_percent'])->toBeGreaterThan($early['speed_decay_percent']);
    expect($late)->not->toHaveKey('obedience_decay_percent')->not->toHaveKey('intelligence_decay_percent');
});
