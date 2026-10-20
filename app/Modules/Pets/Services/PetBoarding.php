<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetBoardingRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Enums\PetBoardingPlan;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Internal operation; the caller owns the transaction and locks owner, pets, then care receipts.
 *
 * @phpstan-import-type BoardingPeriod from PetBoardingRules
 */
final class PetBoarding
{
    public function __construct(
        private PetBoardingRules $rules,
        private PetDecayCalculator $decay,
        private PetCareCompletion $careCompletion,
        private PlayerWallet $wallet,
    ) {}

    /**
     * @param  Collection<int, Pet>  $pets  All the owner's non-archived dogs, locked by ID.
     * @param  array<string, int>  $thresholds  Random care draws retained across transaction retries.
     * @return list<int> Completed care receipt IDs.
     */
    public function cover(User $owner, Collection $pets, CarbonImmutable $at, array &$thresholds): array
    {
        if ($owner->status !== PlayerStatus::Active) {
            return [];
        }
        $windows = $owner->boarding_absences ?? [];
        if ($owner->boarding_plan !== PetBoardingPlan::Off && $owner->boarding_enabled_at !== null) {
            $idleAt = $owner->boarding_enabled_at->max($owner->last_seen_at ?? $owner->boarding_enabled_at)
                ->addSeconds($this->rules->inactivitySeconds());
            if ($idleAt->lessThanOrEqualTo($at)) {
                $windows[] = [
                    ...$this->rules->period($owner->boarding_plan, $idleAt->getTimestamp(), $at->getTimestamp() + 1),
                    'hourly_price' => $this->rules->hourlyPrice($owner->boarding_plan),
                ];
            }
        }
        $completed = [];
        foreach ($windows as $window) {
            $through = min($at->getTimestamp(), $window['ends_at'] - 1);
            if ($window['starts_at'] <= $through) {
                $completed = [...$completed, ...$this->coverWindow($owner, $pets, $window, $through, $thresholds)];
            }
        }
        if ($owner->boarding_absences !== null) {
            $remaining = array_values(array_filter($owner->boarding_absences,
                fn (array $window): bool => $window['ends_at'] > $at->getTimestamp()));
            $owner->boarding_absences = $remaining === [] ? null : $remaining;
            if ($owner->isDirty('boarding_absences')) {
                $owner->save();
            }
        }

        return $completed;
    }

    /**
     * @param  Collection<int, Pet>  $pets
     * @param  BoardingPeriod  $window
     * @param  array<string, int>  $thresholds
     * @return list<int>
     */
    private function coverWindow(User $owner, Collection $pets, array $window, int $through, array &$thresholds): array
    {
        $completed = [];
        $candidates = [];
        $laterCare = [];
        $careBoundary = null;
        foreach ($pets as $pet) {
            if (! $pet->isActive()) {
                continue;
            }
            $startsAt = $this->nextStart($pet, $window['starts_at']);
            if ($pet->activity_token !== null) {
                $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $pet->id)
                    ->where('activity_token', $pet->activity_token)->where('ends_at', '<=', CarbonImmutable::createFromTimestampUTC($through))
                    ->whereNull('completed_at')->whereNull('cancelled_at')->lockForUpdate()->first();
                if ($care !== null) {
                    if ($care->ends_at->getTimestamp() <= $startsAt) {
                        if ($this->careCompletion->complete($owner, $pet, $care, $care->ends_at, $thresholds)) {
                            $completed[] = $care->id;
                        }
                        $startsAt = $this->nextStart($pet, $window['starts_at']);
                    } else {
                        $laterCare[$pet->id] = $care;
                        $careBoundary = $careBoundary === null ? $care->ends_at->getTimestamp() : min($careBoundary, $care->ends_at->getTimestamp());
                    }
                }
            }
            $petThrough = min($through, $pet->naturalDeathAt()->getTimestamp() - 1);
            if ($startsAt > $petThrough) {
                continue;
            }
            $snapshot = clone $pet;
            $snapshot->advanceTo(CarbonImmutable::createFromTimestampUTC($startsAt), $this->decay);
            if (! $snapshot->isActive()) {
                continue;
            }
            $candidates[$pet->id] = ['starts_at' => $startsAt, 'through' => $petThrough];
        }
        if ($careBoundary !== null) {
            foreach ($candidates as $petId => $candidate) {
                $candidates[$petId]['through'] = min($candidate['through'], $careBoundary - 1);
                if ($candidate['starts_at'] > $candidates[$petId]['through']) {
                    unset($candidates[$petId]);
                }
            }
        }
        $price = $window['hourly_price'] ?? $this->rules->hourlyPrice(PetBoardingPlan::from($window['plan']));
        $allocation = $this->rules->allocate($candidates, $owner->coins, $price);
        foreach ($allocation as $petId => $hours) {
            if ($hours < 1) {
                continue;
            }
            $pet = $pets->find($petId);
            if (! $pet instanceof Pet) {
                continue;
            }
            $startsAt = $candidates[$petId]['starts_at'];
            $endsAt = min($startsAt + $hours * PetBoardingRules::SECONDS_PER_HOUR, $pet->naturalDeathAt()->getTimestamp());
            $payment = $this->wallet->change($owner, 'coins', -$hours * $price,
                'pet-boarding:'.$petId.':'.$startsAt.':'.$hours.':'.$window['plan'], 'pet_boarding');
            $owner->coins = $payment->balance_after;
            $owner->syncOriginalAttribute('coins');
            $pet->boarding_periods = [...($pet->boarding_periods ?? []), [...$window, 'starts_at' => $startsAt, 'ends_at' => $endsAt]];
            $pet->save();
        }
        foreach ($laterCare as $petId => $care) {
            if ($care->ends_at->getTimestamp() !== $careBoundary) {
                continue;
            }
            $pet = $pets->find($petId);
            if ($pet instanceof Pet && $this->careCompletion->complete($owner, $pet, $care, $care->ends_at, $thresholds)) {
                $completed[] = $care->id;
            }
        }
        if ($careBoundary !== null) {
            $completed = [...$completed, ...$this->coverWindow($owner, $pets, $window, $through, $thresholds)];
        }

        return $completed;
    }

    private function nextStart(Pet $pet, int $idleAt): int
    {
        $startsAt = max($idleAt, $pet->state_updated_at->getTimestamp(), $pet->stats_updated_at->getTimestamp());
        foreach ($pet->boarding_periods ?? [] as $period) {
            $startsAt = max($startsAt, $period['ends_at']);
        }

        return $startsAt;
    }
}
