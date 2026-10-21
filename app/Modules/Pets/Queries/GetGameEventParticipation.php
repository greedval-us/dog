<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventParticipationRules;
use App\Modules\Pets\DTO\EventRegulationData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-import-type Event from GameEventParticipationRules
 * @phpstan-import-type Participation from GameEventParticipationRules
 */
final class GetGameEventParticipation
{
    public function __construct(private GameEventParticipationRules $rules) {}

    /** @param list<int> $petIds
     * @param  list<GameEvent>  $events
     * @return list<Participation>
     */
    public function entries(int $ownerId, array $petIds, array $events): array
    {
        if ($events === []) {
            return [];
        }
        $starts = array_map(fn (GameEvent $event): int => $event->starts_at->getTimestamp(), $events);
        $closes = array_map(fn (GameEvent $event): int => $event->closes_at->getTimestamp(), $events);
        $ends = array_map(fn (GameEvent $event): int => $event->ends_at->getTimestamp(), $events);
        $timezone = config('game-events.timezone', 'Europe/Moscow');
        $hours = (int) config('game-events.pet_rest_hours', 2);
        $from = CarbonImmutable::createFromTimestampUTC(min($starts))->setTimezone($timezone)->startOfDay()->utc();
        $until = CarbonImmutable::createFromTimestampUTC(max($starts))->setTimezone($timezone)->startOfDay()->addDay()->utc();
        $restFrom = CarbonImmutable::createFromTimestampUTC(min($closes))->subHours($hours);
        $restUntil = CarbonImmutable::createFromTimestampUTC(max($ends))->addHours($hours);
        if ($restFrom->isBefore($from)) {
            $from = $restFrom;
        }
        if ($restUntil->isAfter($until)) {
            $until = $restUntil;
        }

        return array_values(GameEventEntry::query()->where(fn (Builder $query): Builder => $query->where('user_id', $ownerId)->orWhere(fn (Builder $members) => $members->involvingPets($petIds)))
            ->whereNotIn('status', ['cancelled', 'withdrawn'])
            ->whereHas('event', fn (Builder $query): Builder => $query->where('ends_at', '>=', $from)->where('closes_at', '<', $until))
            ->with('event:id,discipline,status,starts_at,closes_at,ends_at,rules')->orderBy('id')->get()
            ->flatMap(function (GameEventEntry $entry): array {
                $members = [$entry->pet_id];
                if (EventRegulationData::isGroupRing($entry->event)) {
                    $members = EventRegulationData::memberIds($entry->event, $entry->plan, $entry->pet_id);
                }

                return array_map(fn (?int $petId): array => [
                    'userId' => $entry->user_id, 'petId' => $petId, 'status' => $entry->status, 'isNpc' => $entry->is_npc,
                    'event' => $this->event($entry->event),
                ], $members);
            })->all());
    }

    /** @param list<Participation> $entries */
    public function reason(int $ownerId, int $petId, GameEvent $event, array $entries): ?string
    {
        foreach ($entries as $entry) {
            if ($entry['userId'] === $ownerId && $entry['petId'] === $petId && $entry['event']['id'] === $event->id && $entry['status'] === 'registered') {
                return null;
            }
        }

        return $this->rules->reason($ownerId, $petId, $this->event($event), $entries);
    }

    public function playerReason(User $owner, GameEvent $event): ?string
    {
        return $this->rules->playerReason($owner->id, $this->event($event), $this->entries($owner->id, [], [$event]));
    }

    public function petReason(Pet $pet, GameEvent $event): ?string
    {
        return $this->rules->petReason($pet->id, $this->event($event), $this->entries(0, [$pet->id], [$event]));
    }

    /** @return Event */
    private function event(GameEvent $event): array
    {
        return ['id' => $event->id, 'discipline' => $event->discipline, 'status' => $event->status,
            'startsAt' => $event->starts_at, 'closesAt' => $event->closes_at, 'endsAt' => $event->ends_at,
            'documentary' => EventRegulationData::isDocumentary($event),
            'attendanceId' => EventRegulationData::isGroupRing($event) ? (int) ($event->rules['linked_show_id'] ?? $event->id) : $event->id];
    }
}
