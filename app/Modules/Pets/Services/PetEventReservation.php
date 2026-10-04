<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Carbon\CarbonImmutable;

final class PetEventReservation
{
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
}
