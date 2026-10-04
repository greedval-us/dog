<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\PetEventReservation;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterGameEvent
{
    public function __construct(private GameEventAdmission $admission, private PetLifecycle $lifecycle, private PlayerWallet $wallet, private PetEventReservation $reservations) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<array-key, mixed>  $gearIds
     */
    public function handle(User $user, int $eventId, int $petId, array $plan, array $gearIds, int $expectedFee, string $token): GameEventEntry
    {
        $token = strtolower($token);
        if (! Str::isUuid($token) || $eventId < 1 || $petId < 1 || $expectedFee < 1) {
            throw new GameEventUnavailable('events.errors.invalid');
        }
        $hash = hash('sha256', serialize([$eventId, $petId, $expectedFee, $plan, $gearIds]));
        $pendingRegistration = null;
        try {
            $this->lifecycle->synchronizeOwner($user);
        } catch (PendingGameEventRegistration $exception) {
            $pendingRegistration = $exception;
        }

        return DB::transaction(function () use ($user, $eventId, $petId, $plan, $gearIds, $expectedFee, $token, $hash, $pendingRegistration): GameEventEntry {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($eventId);
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new GameEventUnavailable('events.errors.blocked');
            }
            $existing = GameEventEntry::query()->where('operation_token', $token)->first();
            if ($existing !== null) {
                if ($existing->user_id !== $owner->id || $existing->registration_hash !== $hash) {
                    throw new GameEventUnavailable('events.errors.token');
                }

                return $existing;
            }
            if ($pendingRegistration !== null) {
                throw $pendingRegistration;
            }
            $at = now()->startOfSecond();
            if ($event->status !== 'registration' || $at->lessThan($event->registration_opens_at) || $at->greaterThanOrEqualTo($event->closes_at)) {
                throw new GameEventUnavailable('events.errors.closed');
            }
            if (($event->rules['fee'] ?? null) !== $expectedFee) {
                throw new GameEventUnavailable('events.errors.fee');
            }
            if ($event->entries()->where('user_id', $owner->id)->exists()) {
                throw new GameEventUnavailable('events.errors.entered');
            }
            if (CurrencyTransaction::query()->where('user_id', $owner->id)->whereRaw('LOWER(operation_key) = ?', ['event-registration:'.$token])->exists()) {
                throw new GameEventUnavailable('events.errors.token');
            }
            $startOfDay = $event->starts_at->setTimezone(config('game-events.timezone'))->startOfDay()->utc();
            $dailyCount = GameEventEntry::query()->where('user_id', $owner->id)->whereNotIn('status', ['cancelled', 'withdrawn'])
                ->whereHas('event', fn ($query) => $query->where('starts_at', '>=', $startOfDay)->where('starts_at', '<', $startOfDay->addDay()))->count();
            if ($dailyCount >= config('game-events.daily_limit', 3)) {
                throw new GameEventUnavailable('events.errors.daily_limit');
            }
            $pet = Pet::query()->where('user_id', $owner->id)->lockForUpdate()->findOrFail($petId);
            $this->lifecycle->assertCanAdvance($owner, $at);
            if (($reason = $this->admission->reason($event, $pet, $at)) !== null) {
                throw new GameEventUnavailable($reason);
            }
            $this->reservations->assertCanRegister($pet, $event);
            $preparation = $this->admission->prepare($owner, $event, $pet, $plan, $gearIds);
            $division = $this->availableDivision($event, $this->admission->division($event, $pet));
            try {
                $this->wallet->change($owner, 'coins', -$expectedFee, 'event-registration:'.$token, 'event_registration');
            } catch (InsufficientFunds) {
                throw new GameEventUnavailable('events.errors.funds');
            }

            return GameEventEntry::query()->create([
                'game_event_id' => $event->id, 'user_id' => $owner->id, 'pet_id' => $pet->id,
                'operation_token' => $token, 'registration_hash' => $hash, 'division' => $division,
                'status' => 'registered', 'fee' => $expectedFee, 'plan' => $preparation['plan'], 'gear_ids' => $gearIds,
            ]);
        }, attempts: 3);
    }

    private function availableDivision(GameEvent $event, string $baseDivision): string
    {
        $fieldSize = $event->rules['field_size'] ?? 8;
        $counts = $event->entries()->whereIn('status', ['registered', 'frozen'])
            ->where(fn (Builder $query): Builder => $query->where('division', $baseDivision)->orWhere('division', 'like', $baseDivision.':heat-%'))
            ->toBase()->select('division')->selectRaw('COUNT(*) AS entries_count')
            ->groupBy('division')->pluck('entries_count', 'division');
        $heat = 1;
        do {
            $division = $heat === 1 ? $baseDivision : $baseDivision.':heat-'.$heat;
            $heat++;
        } while (($counts[$division] ?? 0) >= $fieldSize);

        return $division;
    }
}
