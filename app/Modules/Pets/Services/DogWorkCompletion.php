<?php

namespace App\Modules\Pets\Services;

use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;

final class DogWorkCompletion
{
    public function __construct(private PetActivityManager $activities, private PlayerWallet $wallet, private PetDecayCalculator $decay,
        private PetHistoryRecorder $history, private PlayerProgress $progress, private PetLifecycleSynchronization $lifecycle) {}

    /** The caller owns the transaction and holds owner, pet and shift locks, in that order. */
    public function complete(User $owner, Pet $pet, DogWorkShift $shift, CarbonImmutable $confirmedAt): DogWorkShift
    {
        if ($shift->completed_at !== null || $shift->cancelled_at !== null) {
            return $shift;
        }
        if (! $pet->isActive()) {
            $this->lifecycle->persist($pet);

            return $shift->refresh();
        }
        if ($shift->ends_at->greaterThan($confirmedAt)) {
            throw new PetUnavailable('This job has not finished yet.');
        }
        $operation = 'dog-work:'.$shift->id;
        if (CurrencyTransaction::query()->whereBelongsTo($owner)->whereIn('operation_key', [$operation.':coins', $operation.':gems'])->exists()) {
            throw new PetUnavailable('This job reward has already been recorded without a completed shift.');
        }
        if (! $this->activities->complete($owner, $pet->id, $shift->activity_token, $confirmedAt)) {
            throw new PetUnavailable('Your dog is no longer assigned to this job.');
        }

        $pet->refresh()->advanceTo($confirmedAt, $this->decay);
        if (! $pet->isActive()) {
            $this->lifecycle->persist($pet);

            return $shift->refresh();
        }
        $pet->save();
        $this->wallet->change($owner, 'coins', $shift->coins_reward, $operation.':coins', 'dog_work');
        if ($shift->gems_reward > 0) {
            $this->wallet->change($owner, 'gems', $shift->gems_reward, $operation.':gems', 'dog_work');
        }
        $shift->update(['completed_at' => $confirmedAt]);
        $experienceAwarded = $this->progress->award($owner, $shift, fn (DogWorkShift $completed): PlayerProgressFact => new PlayerProgressFact(
            code: 'work', completedAt: $completed->cancelled_at === null ? $completed->completed_at : null,
        ));
        $this->history->record($pet, 'work', 'work:'.$shift->id.':completed', $shift->ends_at, [
            'stage' => 'completed', 'name' => $shift->name, 'experienceAwarded' => $experienceAwarded,
            'durationSeconds' => $shift->ends_at->getTimestamp() - $shift->started_at->getTimestamp(),
            'changes' => [], 'coins' => $shift->coins_reward, 'gems' => $shift->gems_reward,
        ]);

        return $shift;
    }
}
