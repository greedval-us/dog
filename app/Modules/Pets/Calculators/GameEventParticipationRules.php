<?php

namespace App\Modules\Pets\Calculators;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * @phpstan-type Event array{id:int,discipline:string,status:string,startsAt:DateTimeInterface,closesAt:DateTimeInterface,endsAt:DateTimeInterface,documentary?:bool,attendanceId?:int}
 * @phpstan-type Participation array{userId:int|null,petId:int|null,status:string,isNpc:bool,event:Event}
 */
final class GameEventParticipationRules
{
    /** @param array{daily_limit:int,pet_daily_limit:int,pet_rest_hours:int} $balance */
    public function __construct(private array $balance, private string $timezone) {}

    /** @param Event $event
     * @param  list<Participation>  $entries
     */
    public function reason(int $ownerId, int $petId, array $event, array $entries): ?string
    {
        return $this->playerReason($ownerId, $event, $entries) ?? $this->petReason($petId, $event, $entries);
    }

    /** @param Event $event
     * @param  list<Participation>  $entries
     */
    public function playerReason(int $ownerId, array $event, array $entries): ?string
    {
        [$from, $until] = $this->day($event['startsAt']);
        $eventIds = [];
        foreach ($entries as $entry) {
            if ($entry['userId'] !== $ownerId || in_array($entry['status'], ['cancelled', 'withdrawn'], true)
                || $entry['event']['startsAt'] < $from || $entry['event']['startsAt'] >= $until) {
                continue;
            }
            if (($entry['event']['attendanceId'] ?? $entry['event']['id']) === ($event['attendanceId'] ?? $event['id'])) {
                return null;
            }
            $eventIds[$entry['event']['attendanceId'] ?? $entry['event']['id']] = true;
        }

        return count($eventIds) >= $this->balance['daily_limit'] ? 'events.errors.daily_limit' : null;
    }

    /** @param Event $event
     * @param  list<Participation>  $entries
     */
    public function petReason(int $petId, array $event, array $entries): ?string
    {
        if ($event['documentary'] ?? $event['discipline'] === 'progeny') {
            return null;
        }
        $alreadyAttending = count(array_filter($entries, fn (array $entry): bool => $entry['petId'] === $petId && ! $entry['isNpc']
            && ($entry['event']['attendanceId'] ?? $entry['event']['id']) === ($event['attendanceId'] ?? $event['id'])
            && in_array($entry['status'], ['registered', 'frozen', 'completed'], true)
            && in_array($entry['event']['status'], ['registration', 'frozen', 'settled'], true))) > 0;
        $physical = array_values(array_filter($entries, fn (array $entry): bool => $entry['petId'] === $petId && ! $entry['isNpc']
            && ($entry['event']['attendanceId'] ?? $entry['event']['id']) !== ($event['attendanceId'] ?? $event['id'])
            && ! ($entry['event']['documentary'] ?? $entry['event']['discipline'] === 'progeny')
            && in_array($entry['status'], ['registered', 'frozen', 'completed'], true)
            && in_array($entry['event']['status'], ['registration', 'frozen', 'settled'], true)));
        [$from, $until] = $this->day($event['startsAt']);
        $eventIds = [];
        foreach ($physical as $entry) {
            if ($entry['event']['startsAt'] >= $from && $entry['event']['startsAt'] < $until) {
                $eventIds[$entry['event']['attendanceId'] ?? $entry['event']['id']] = true;
            }
        }
        $count = count($eventIds);
        if (! $alreadyAttending && $count >= $this->balance['pet_daily_limit']) {
            return 'events.errors.pet_daily_limit';
        }
        $hours = $this->balance['pet_rest_hours'];
        $earliest = DateTimeImmutable::createFromInterface($event['closesAt'])->modify('-'.$hours.' hours');
        $latest = DateTimeImmutable::createFromInterface($event['endsAt'])->modify('+'.$hours.' hours');
        foreach ($physical as $entry) {
            if ($entry['event']['closesAt'] < $latest && $entry['event']['endsAt'] > $earliest) {
                return 'events.errors.rest_period';
            }
        }

        return null;
    }

    /** @return array{DateTimeImmutable,DateTimeImmutable} */
    private function day(DateTimeInterface $at): array
    {
        $from = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone($this->timezone))->setTime(0, 0);

        return [$from, $from->modify('+1 day')];
    }
}
