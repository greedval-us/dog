<?php

namespace App\Modules\Pets\Services;

use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Queries\GetPendingEventRegistrations;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Internal lifecycle settlement and persistence; external modules use PetLifecycle. */
final class PetLifecycleSynchronization
{
    public function __construct(
        private PetDecayCalculator $decay,
        private PetHistoryRecorder $history,
        private PetCareCompletion $careCompletion,
        private GetPendingEventRegistrations $registrations,
        private PetBoarding $boarding,
        private PlayerNotifications $notifications,
    ) {}

    /** @return list<int> Care receipts settled before a possible lifecycle transition. */
    public function synchronizeOwner(User $user, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $thresholds = [];

        return DB::transaction(function () use ($user, $at, &$thresholds): array {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertCanAdvance($owner, $at);
            $pets = $owner->pets()->whereNull('surrendered_at')->whereNull('died_at')->orderBy('id')->lockForUpdate()->get();
            $completedCareIds = $this->boarding->cover($owner, $pets, $at, $thresholds);
            foreach ($pets as $pet) {
                if (($pet->boarding_periods ?? []) !== []) {
                    $completedCareId = $this->completeDueCare($owner, $pet, $at, $thresholds);
                    if ($completedCareId !== null) {
                        $completedCareIds[] = $completedCareId;
                    }
                    $pet->advanceTo($at, $this->decay);
                    $this->persist($pet);

                    continue;
                }
                $elapsed = clone $pet;
                $elapsed->advanceTo($at, $this->decay);
                if ($elapsed->archivedAt() === null && ! $elapsed->isDirty('retired_at')) {
                    continue;
                }
                $completedCareId = $this->completeDueCare($owner, $pet, $at, $thresholds);
                if ($completedCareId !== null) {
                    $completedCareIds[] = $completedCareId;
                }
                $pet->advanceTo($at, $this->decay);
                if ($pet->isDirty() || $pet->isBusy()) {
                    $this->persist($pet);
                }
            }

            return $completedCareIds;
        }, attempts: 3);
    }

    /** @param array<string, int> $thresholds */
    private function completeDueCare(User $owner, Pet $pet, CarbonImmutable $at, array &$thresholds): ?int
    {
        if ($owner->status !== PlayerStatus::Active || $pet->activity_token === null) {
            return null;
        }
        $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $pet->id)
            ->where('activity_token', $pet->activity_token)->where('ends_at', '<=', $at)
            ->whereNull('completed_at')->whereNull('cancelled_at')->lockForUpdate()->first();

        return $care !== null && $this->careCompletion->complete($owner, $pet, $care, $at, $thresholds) ? $care->id : null;
    }

    /** The caller holds the owner row lock in the transaction that will change gameplay state. */
    public function assertCanAdvance(User $owner, ?CarbonImmutable $at = null): void
    {
        $current = CarbonImmutable::now();
        $through = $at === null ? $current : max($at, $current);
        if ($this->registrations->handle($owner, $through)) {
            throw new PendingGameEventRegistration;
        }
    }

    /** The caller must hold the owner and pet row locks in that order. */
    public function persist(Pet $pet): void
    {
        $died = $pet->isDirty('died_at') && $pet->died_at !== null;
        $retired = $pet->isDirty('retired_at') && $pet->retired_at !== null;
        $archivedAt = $pet->archivedAt();
        if ($archivedAt !== null) {
            $pet->clearActivity();
            PetCareAction::query()->where('pet_id', $pet->id)->whereNull('completed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $archivedAt]);
            DogWorkShift::query()->where('pet_id', $pet->id)->whereNull('completed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $archivedAt]);
        }
        $pet->save();

        if ($died && $pet->user !== null) {
            $this->notifications->petDied($pet->user, $pet->id, $pet->name);
        }

        if ($archivedAt !== null) {
            $code = $pet->died_at === null ? 'life.surrender' : 'life.death';
            $this->history->record($pet, $code, $code.':'.$pet->id, $archivedAt, [
                'stage' => 'completed', 'automatic' => false,
                'changes' => [],
            ]);
        }
        if ($retired) {
            $this->history->record($pet, 'life.retirement', 'life.retirement:'.$pet->id.':'.$pet->retired_at->getTimestamp(), $pet->retired_at, [
                'stage' => 'completed', 'automatic' => false, 'changes' => [],
            ]);
        }
    }
}
