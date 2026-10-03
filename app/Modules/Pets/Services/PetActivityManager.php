<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\DTO\PetActivityData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PetActivityManager
{
    public function start(User $owner, int $petId, PetActivity $activity, CarbonImmutable $endsAt, int $energyCost): PetActivityData
    {
        if ($energyCost < 0) {
            throw new InvalidArgumentException('The energy cost must not be negative.');
        }

        $startedAt = CarbonImmutable::now()->startOfSecond();
        $endsAt = $endsAt->utc()->startOfSecond();

        if ($endsAt->lessThanOrEqualTo($startedAt)) {
            throw new InvalidArgumentException('The activity must end after it starts.');
        }

        $data = new PetActivityData((string) Str::uuid(), $activity, $startedAt, $endsAt);
        $claimed = Pet::query()->whereKey($petId)->whereBelongsTo($owner, 'user')->availableForActivity()
            ->where('energy', '>=', $energyCost)->decrement('energy', $energyCost, [
                'activity' => $activity->value,
                'activity_token' => $data->token,
                'activity_started_at' => $startedAt,
                'activity_ends_at' => $endsAt,
                'last_activity_at' => $startedAt,
            ]);

        if ($claimed !== 1) {
            throw new PetUnavailable;
        }

        return $data;
    }

    /**
     * Apply gameplay results in the caller's transaction only when this returns true.
     */
    public function complete(User $owner, int $petId, string $token, ?CarbonImmutable $completedAt = null): bool
    {
        if (! Str::isUuid($token)) {
            return false;
        }

        $completedAt = ($completedAt ?? CarbonImmutable::now())->startOfSecond();

        return Pet::query()->whereKey($petId)->whereBelongsTo($owner, 'user')
            ->active()
            ->whereNotNull('activity')->where('activity_token', $token)
            ->where('activity_ends_at', '<=', $completedAt)
            ->update([
                'activity' => null,
                'activity_token' => null,
                'activity_started_at' => null,
                'activity_ends_at' => null,
                'last_activity_at' => $completedAt,
            ]) === 1;
    }
}
