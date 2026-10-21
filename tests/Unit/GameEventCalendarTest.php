<?php

use App\Modules\Pets\Calculators\GameEventCalendar;

function titleGameCalendar(): GameEventCalendar
{
    return new GameEventCalendar(['epoch' => '2026-01-01', 'first_year' => 2026, 'months_per_year' => 12], 'Europe/Moscow');
}

test('game editions advance at Moscow midnight and roll over after twelve real days', function (string $at, int $monthIndex, int $year, string $edition) {
    $result = titleGameCalendar()->editionAt(new DateTimeImmutable($at));

    expect($result)->toBe(['monthIndex' => $monthIndex, 'showYear' => $year, 'showEdition' => $edition]);
})->with([
    'epoch begins before UTC midnight' => ['2025-12-31T21:00:00+00:00', 0, 2026, '2026-01'],
    'eleven days complete the first game year' => ['2026-01-11T21:00:00+00:00', 11, 2026, '2026-12'],
    'last second of a Moscow day' => ['2026-01-12T20:59:59+00:00', 11, 2026, '2026-12'],
    'twelve days begin the next game year' => ['2026-01-12T21:00:00+00:00', 12, 2027, '2027-01'],
    'the current edition matches the existing schedule' => ['2026-10-20T00:00:00+03:00', 292, 2050, '2050-05'],
    'historical dates stay at the initial edition' => ['2025-12-30T00:00:00+03:00', 0, 2026, '2026-01'],
]);

test('reading an edition does not change a mutable callers date or timezone', function () {
    $at = new DateTime('2026-01-12T21:00:00+00:00');

    $edition = titleGameCalendar()->editionAt($at);

    expect($edition['showEdition'])->toBe('2027-01');
    expect($at->format(DateTimeInterface::ATOM))->toBe('2026-01-12T21:00:00+00:00');
});

test('a configured calendar epoch and year length remain the sole edition settings', function () {
    $calendar = new GameEventCalendar(['epoch' => '2030-05-15', 'first_year' => 100, 'months_per_year' => 6], 'Europe/Moscow');

    $edition = $calendar->editionAt(new DateTimeImmutable('2030-05-21T12:00:00+03:00'));

    expect($edition)->toBe(['monthIndex' => 6, 'showYear' => 101, 'showEdition' => '0101-01']);
});
