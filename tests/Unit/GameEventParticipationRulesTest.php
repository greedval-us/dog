<?php

use App\Modules\Pets\Calculators\GameEventParticipationRules;

function participationPolicyEvent(int $id = 100, string $startsAt = '2026-10-21T15:00:00+03:00', string $discipline = 'agility'): array
{
    $start = new DateTimeImmutable($startsAt);

    return ['id' => $id, 'discipline' => $discipline, 'status' => 'registration',
        'startsAt' => $start, 'closesAt' => $start->modify('-15 minutes'), 'endsAt' => $start->modify('+10 minutes')];
}

function participationPolicyEntry(int $eventId, string $startsAt, array $changes = []): array
{
    return ['userId' => 1, 'petId' => 10, 'status' => 'registered', 'isNpc' => false,
        'event' => participationPolicyEvent($eventId, $startsAt), ...$changes];
}

function participationPolicyRules(): GameEventParticipationRules
{
    return new GameEventParticipationRules(['daily_limit' => 3, 'pet_daily_limit' => 2, 'pet_rest_hours' => 2], 'Europe/Moscow');
}

test('the player limit includes documentary events and uses the Moscow calendar day', function () {
    $entries = [participationPolicyEntry(1, '2026-10-20T21:00:00+00:00', ['petId' => 11]),
        participationPolicyEntry(2, '2026-10-21T07:00:00+03:00', ['petId' => 12]),
        participationPolicyEntry(3, '2026-10-21T10:00:00+03:00', ['event' => participationPolicyEvent(3, '2026-10-21T10:00:00+03:00', 'progeny')])];

    expect(participationPolicyRules()->reason(1, 20, participationPolicyEvent(), $entries))->toBe('events.errors.daily_limit');
    $entries[0]['event'] = participationPolicyEvent(1, '2026-10-20T20:59:59+00:00');
    expect(participationPolicyRules()->reason(1, 20, participationPolicyEvent(), $entries))->toBeNull();
});

test('several owned dogs in one show count as one player event and joining it again uses no additional daily start', function () {
    $entries = [
        participationPolicyEntry(1, '2026-10-21T07:00:00+03:00', ['petId' => 11]),
        participationPolicyEntry(1, '2026-10-21T07:00:00+03:00', ['petId' => 12]),
        participationPolicyEntry(1, '2026-10-21T07:00:00+03:00', ['petId' => 13]),
    ];

    expect(participationPolicyRules()->playerReason(1, participationPolicyEvent(), $entries))->toBeNull();
    $entries[] = participationPolicyEntry(2, '2026-10-21T09:00:00+03:00');
    $entries[] = participationPolicyEntry(3, '2026-10-21T11:00:00+03:00');
    expect(participationPolicyRules()->playerReason(1, participationPolicyEvent(), $entries))->toBe('events.errors.daily_limit');
    $entries[] = participationPolicyEntry(100, '2026-10-21T15:00:00+03:00', ['petId' => 14]);
    expect(participationPolicyRules()->playerReason(1, participationPolicyEvent(), $entries))->toBeNull();
});

test('physical participation follows the dog across owners but ignores documentary results', function () {
    $entries = [participationPolicyEntry(1, '2026-10-21T05:00:00+03:00', ['userId' => 2]),
        participationPolicyEntry(2, '2026-10-21T08:00:00+03:00', ['userId' => 2])];

    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(), $entries))->toBe('events.errors.pet_daily_limit');
    $entries[1]['event']['discipline'] = 'progeny';
    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(), $entries))->toBeNull();
});

test('exactly two hours between reserved windows is allowed but one second less is blocked', function () {
    $event = participationPolicyEvent();
    $entry = participationPolicyEntry(1, '2026-10-21T12:35:00+03:00');

    expect(participationPolicyRules()->reason(1, 10, $event, [$entry]))->toBeNull();
    $entry['event']['endsAt'] = $entry['event']['endsAt']->modify('+1 second');
    expect(participationPolicyRules()->reason(1, 10, $event, [$entry]))->toBe('events.errors.rest_period');
    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(discipline: 'progeny'), [$entry]))->toBeNull();
});

test('existing target entries and cancelled or withdrawn entries do not block their own preparation', function () {
    $entries = [participationPolicyEntry(100, '2026-10-21T15:00:00+03:00'),
        participationPolicyEntry(1, '2026-10-21T15:00:00+03:00', ['status' => 'cancelled']),
        participationPolicyEntry(2, '2026-10-21T15:00:00+03:00', ['status' => 'withdrawn'])];

    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(), $entries))->toBeNull();
});

test('another dogs reservation never blocks this dog and a cancelled event is not physical participation', function () {
    $entries = [participationPolicyEntry(1, '2026-10-21T15:00:00+03:00', ['petId' => 11])];
    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(), $entries))->toBeNull();
    $entries[0]['petId'] = 10;
    $entries[0]['event']['status'] = 'cancelled';
    expect(participationPolicyRules()->reason(1, 10, participationPolicyEvent(), $entries))->toBeNull();
});

test('a physical progeny ring respects another events rest window', function () {
    $ring = [...participationPolicyEvent(discipline: 'progeny'), 'documentary' => false, 'attendanceId' => 90];
    $entry = participationPolicyEntry(1, '2026-10-21T14:00:00+03:00');

    expect(participationPolicyRules()->petReason(10, $ring, [$entry]))->toBe('events.errors.rest_period');
});

test('the linked individual show and its progeny ring are one attendance for the dog and player', function () {
    $show = [...participationPolicyEvent(1, '2026-10-21T07:00:00+03:00', 'conformation'), 'attendanceId' => 1, 'documentary' => false];
    $ring = [...participationPolicyEvent(2, '2026-10-21T07:10:00+03:00', 'progeny'), 'attendanceId' => 1, 'documentary' => false];
    $entries = [participationPolicyEntry(1, '2026-10-21T07:00:00+03:00', ['event' => $show]),
        participationPolicyEntry(2, '2026-10-21T07:10:00+03:00', ['event' => $ring])];

    expect(participationPolicyRules()->petReason(10, participationPolicyEvent(), $entries))->toBeNull();
    $entries[] = participationPolicyEntry(3, '2026-10-21T10:00:00+03:00');
    expect(participationPolicyRules()->playerReason(1, participationPolicyEvent(), $entries))->toBeNull();
    $entries[] = participationPolicyEntry(4, '2026-10-21T11:00:00+03:00');
    expect(participationPolicyRules()->playerReason(1, participationPolicyEvent(), $entries))->toBe('events.errors.daily_limit');
});

test('a progeny ring can join its linked show even when other attendance limits are already reached', function () {
    $show = [...participationPolicyEvent(1, '2026-10-21T15:00:00+03:00', 'conformation'), 'attendanceId' => 1, 'documentary' => false];
    $ring = [...participationPolicyEvent(2, '2026-10-21T15:10:00+03:00', 'progeny'), 'attendanceId' => 1, 'documentary' => false];
    $entries = [participationPolicyEntry(1, '2026-10-21T15:00:00+03:00', ['event' => $show]),
        participationPolicyEntry(3, '2026-10-21T07:00:00+03:00'), participationPolicyEntry(4, '2026-10-21T10:00:00+03:00')];

    expect(participationPolicyRules()->reason(1, 10, $ring, $entries))->toBeNull();
});
