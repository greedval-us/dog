<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class GetPendingEventRegistrations
{
    public function handle(User $owner, CarbonImmutable $at): bool
    {
        return GameEvent::query()->where('status', 'registration')->where('closes_at', '<=', $at)
            ->whereHas('entries', fn (Builder $entries): Builder => $entries->where('user_id', $owner->id)->where('status', 'registered'))->exists();
    }
}
