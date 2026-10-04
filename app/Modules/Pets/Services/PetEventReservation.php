<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class PetEventReservation
{
    /** The caller holds the pet lock so simultaneous registrations cannot exceed its participation limits. */
    public function assertCanRegister(Pet $pet, GameEvent $event): void
    {
        if (GameEventDiscipline::from($event->discipline)->isDocumentary()) {
            return;
        }
        $day = $event->starts_at->setTimezone(config('game-events.timezone', 'Europe/Moscow'))->startOfDay();
        $from = $day->utc();
        $until = $day->addDay()->utc();
        $entries = $this->physicalParticipations($pet);
        $dailyCount = (clone $entries)->whereHas('event', fn (Builder $query): Builder => $query
            ->where('starts_at', '>=', $from)->where('starts_at', '<', $until))->count();
        if ($dailyCount >= config('game-events.pet_daily_limit', 2)) {
            throw new GameEventUnavailable('events.errors.pet_daily_limit');
        }
        $restHours = (int) config('game-events.pet_rest_hours', 2);
        if ($entries->whereHas('event', fn (Builder $query): Builder => $query
            ->where('closes_at', '<', $event->ends_at->addHours($restHours))
            ->where('ends_at', '>', $event->closes_at->subHours($restHours)))->exists()) {
            throw new GameEventUnavailable('events.errors.rest_period');
        }
    }

    public function assertAvailable(Pet $pet, CarbonImmutable $from, CarbonImmutable $until): void
    {
        if (GameEventEntry::query()->where('pet_id', $pet->id)->whereIn('status', ['registered', 'frozen'])
            ->whereHas('event', fn ($query) => $query->whereIn('status', ['registration', 'frozen'])
                ->where('discipline', '!=', GameEventDiscipline::Progeny->value)->where('closes_at', '<=', $until)->where('ends_at', '>', $from))->exists()) {
            throw new PetUnavailable('events.errors.reserved');
        }
    }

    public function nextStartsAt(Pet $pet, CarbonImmutable $at): ?CarbonImmutable
    {
        $entry = GameEventEntry::query()->with('event')->where('pet_id', $pet->id)->whereIn('status', ['registered', 'frozen'])
            ->whereHas('event', fn ($query) => $query->whereIn('status', ['registration', 'frozen'])
                ->where('discipline', '!=', GameEventDiscipline::Progeny->value)->where('ends_at', '>', $at))
            ->get()->sortBy(fn (GameEventEntry $entry): int => $entry->event->closes_at->getTimestamp())->first();

        return $entry?->event->closes_at;
    }

    /** @return Builder<GameEventEntry> */
    private function physicalParticipations(Pet $pet): Builder
    {
        return GameEventEntry::query()->where('pet_id', $pet->id)->where('is_npc', false)
            ->whereIn('status', ['registered', 'frozen', 'completed'])
            ->whereHas('event', fn (Builder $query): Builder => $query
                ->whereIn('status', ['registration', 'frozen', 'settled'])
                ->where('discipline', '!=', GameEventDiscipline::Progeny->value));
    }
}
