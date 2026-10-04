<?php

use App\Modules\Inventory\Calculators\AmmunitionSupplyRules;

test('deliveries preserve their phase and skip missed periods without accumulating supplies', function (string $at, string $anchor, int $hours, string $current, string $next) {
    $schedule = (new AmmunitionSupplyRules)->schedule(strtotime($at), strtotime($anchor), $hours);

    expect($schedule)->toBe(['current' => strtotime($current), 'next' => strtotime($next)]);
})->with([
    'before boundary' => ['2026-10-05 05:59:59 UTC', '2026-10-05 00:00:00 UTC', 6, '2026-10-05 00:00:00 UTC', '2026-10-05 06:00:00 UTC'],
    'exact boundary' => ['2026-10-05 06:00:00 UTC', '2026-10-05 00:00:00 UTC', 6, '2026-10-05 06:00:00 UTC', '2026-10-05 12:00:00 UTC'],
    'missed six hour deliveries' => ['2026-10-07 13:00:00 UTC', '2026-10-05 06:00:00 UTC', 6, '2026-10-07 12:00:00 UTC', '2026-10-07 18:00:00 UTC'],
    'missed weekly deliveries' => ['2026-10-19 13:00:00 UTC', '2026-10-05 00:00:00 UTC', 168, '2026-10-19 00:00:00 UTC', '2026-10-26 00:00:00 UTC'],
    'non-midnight anchor' => ['2026-10-07 13:00:00 UTC', '2026-10-05 08:00:00 UTC', 6, '2026-10-07 08:00:00 UTC', '2026-10-07 14:00:00 UTC'],
]);

test('invalid delivery intervals and future anchors are rejected', function (int $at, int $anchor, int $hours) {
    expect(fn () => (new AmmunitionSupplyRules)->schedule($at, $anchor, $hours))->toThrow(InvalidArgumentException::class);
})->with([
    'zero interval' => [100, 0, 0],
    'unsupported interval' => [100000, 0, 24],
    'future anchor' => [10, 20, 6],
]);
