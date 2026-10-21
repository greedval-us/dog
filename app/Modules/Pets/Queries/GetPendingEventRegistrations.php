<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class GetPendingEventRegistrations
{
    public function handle(User $owner, CarbonImmutable $at): bool
    {
        $petIds = array_values($owner->pets()->get(['id'])->map(static fn (Pet $pet): int => $pet->id)->all());

        return GameEvent::query()->where('status', 'registration')->where('closes_at', '<=', $at)
            ->where(fn (Builder $ready) => $ready->whereNull('rules->group_freezes_at')->orWhere('rules->group_freezes_at', '<=', $at->utc()->toIso8601String()))
            ->whereHas('entries', fn (Builder $entries): Builder => $entries->where('status', 'registered')
                ->where(fn (Builder $involved) => $involved->where('user_id', $owner->id)->orWhere(fn (Builder $members) => $members->involvingPets($petIds))))->exists();
    }
}
