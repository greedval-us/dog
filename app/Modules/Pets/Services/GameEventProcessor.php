<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GameEventProcessor
{
    public function __construct(private GameEventFreezing $freezing, private GameEventSettlement $settlement) {}

    public function processDue(?CarbonImmutable $at = null, ?User $owner = null, ?int $limit = null): int
    {
        $at = ($at ?? CarbonImmutable::now())->startOfSecond();
        $limit ??= (int) config('game-events.processing.background_batch_size', 100);
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Event processing batch size must be between 1 and 1000.');
        }
        $count = 0;
        foreach ($this->dueEvents($at, $owner)->select('id')->orderBy('starts_at')->orderBy('id')->limit($limit)->get() as $event) {
            $count += $this->process($event->id, $at) ? 1 : 0;
        }

        return $count;
    }

    public function processForOwner(User $user, ?CarbonImmutable $at = null): int
    {
        return $this->processDue($at, $user);
    }

    public function hasDueRegistrations(User $owner, ?CarbonImmutable $at = null): bool
    {
        return GameEvent::query()->where('status', 'registration')->where('closes_at', '<=', $at ?? CarbonImmutable::now())
            ->whereHas('entries', fn (Builder $entries) => $entries->where('user_id', $owner->id)->where('status', 'registered'))->exists();
    }

    public function hasDueEvents(?CarbonImmutable $at = null, ?User $owner = null): bool
    {
        return $this->dueEvents($at ?? CarbonImmutable::now(), $owner)->exists();
    }

    /** @return Builder<GameEvent> */
    private function dueEvents(CarbonImmutable $at, ?User $owner): Builder
    {
        $query = GameEvent::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $registration) => $registration->where('status', 'registration')->where('closes_at', '<=', $at))
            ->orWhere(fn (Builder $frozen) => $frozen->where('status', 'frozen')->where('ends_at', '<=', $at)));
        if ($owner !== null) {
            $query->whereHas('entries', fn (Builder $entries) => $entries->where('user_id', $owner->id)->whereIn('status', ['registered', 'frozen']));
        }

        return $query;
    }

    private function process(int $eventId, CarbonImmutable $at): bool
    {
        $thresholds = [];

        return DB::transaction(function () use ($eventId, $at, &$thresholds): bool {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (! in_array($event->status, ['registration', 'frozen'], true)
                || ($event->status === 'registration' && $event->closes_at->isAfter($at))
                || ($event->status === 'frozen' && $event->ends_at->isAfter($at))) {
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
