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
    ) {}

    /** @return list<int> Care receipts settled before a possible lifecycle transition. */
    public function synchronizeOwner(User $user, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $thresholds = [];

        return DB::transaction(function () use ($user, $at, &$thresholds): array {
            $completedCareIds = [];
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertCanAdvance($owner, $at);
            $pets = $owner->pets()->whereNull('retired_at')->whereNull('died_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($pets as $pet) {
                $elapsed = clone $pet;
                $elapsed->advanceTo($at, $this->decay);
                if ($elapsed->archivedAt() === null) {
                    continue;
                }
                if ($owner->status === PlayerStatus::Active && $pet->activity_token !== null) {
                    $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $pet->id)
                        ->where('activity_token', $pet->activity_token)->where('ends_at', '<=', $at)
                        ->whereNull('completed_at')->whereNull('cancelled_at')->lockForUpdate()->first();
                    if ($care !== null && $this->careCompletion->complete($owner, $pet, $care, $at, $thresholds)) {
                        $completedCareIds[] = $care->id;
                    }
                }
                $pet->advanceTo($at, $this->decay);
                if ($pet->archivedAt() !== null && ($pet->isDirty() || $pet->isBusy())) {
                    $this->persist($pet);
                }
            }

            return $completedCareIds;
        }, attempts: 3);
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
        $archivedAt = $pet->archivedAt();
        if ($archivedAt !== null) {
            $pet->clearActivity();
            PetCareAction::query()->where('pet_id', $pet->id)->whereNull('completed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $archivedAt]);
            DogWorkShift::query()->where('pet_id', $pet->id)->whereNull('completed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $archivedAt]);
        }
        $pet->save();

        if ($archivedAt !== null) {
            $code = $pet->died_at === null ? 'life.retirement' : 'life.death';
            $this->history->record($pet, $code, $code.':'.$pet->id, $archivedAt, [
                'stage' => 'completed', 'automatic' => $pet->retired_at?->equalTo($pet->automaticRetirementAt()) ?? false,
                'changes' => [],
            ]);
        }
    }
}
