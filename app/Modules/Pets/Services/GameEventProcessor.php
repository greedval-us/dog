<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GameEventProcessor
{
    public function __construct(private GameEventFreezing $freezing, private GameEventSettlement $settlement) {}

    public function processDue(?CarbonImmutable $at = null, ?User $owner = null): int
    {
        $at = ($at ?? CarbonImmutable::now())->startOfSecond();
        $query = GameEvent::query()->whereIn('status', ['registration', 'frozen'])->where('closes_at', '<=', $at);
        if ($owner !== null) {
            $query->whereHas('entries', fn ($entries) => $entries->where('user_id', $owner->id));
        }
        $count = 0;
        foreach ($query->orderBy('starts_at')->orderBy('id')->limit(100)->get() as $event) {
            $count += $this->process($event->id, $at) ? 1 : 0;
        }

        return $count;
    }

    public function processForOwner(User $user, ?CarbonImmutable $at = null): int
    {
        return $this->processDue($at, $user);
    }

    private function process(int $eventId, CarbonImmutable $at): bool
    {
        $thresholds = [];

        return DB::transaction(function () use ($eventId, $at, &$thresholds): bool {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (! in_array($event->status, ['registration', 'frozen'], true)) {
                return false;
            }
            $entries = $event->entries()->orderBy('id')->get();
            $owners = User::query()->whereIn('id', $entries->pluck('user_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $pets = Pet::query()->whereIn('id', $entries->pluck('pet_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($event->status === 'registration') {
                $this->freezing->freeze($event, $entries, $owners, $pets, $at, $thresholds);
            }
            if ($event->status === 'frozen' && $at->greaterThanOrEqualTo($event->ends_at)) {
                $this->settlement->settle($event, $owners, $pets, $at);
            }

            return true;
        }, attempts: 3);
    }
}
