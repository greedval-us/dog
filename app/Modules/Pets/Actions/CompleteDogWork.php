<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompleteDogWork
{
    public function __construct(private PetActivityManager $activities, private PlayerWallet $wallet, private PetDecayCalculator $decay,
        private PetHistoryRecorder $history, private PlayerProgress $progress, private PetLifecycleSynchronization $lifecycle) {}

    public function handle(User $user, string $token, ?CarbonImmutable $confirmedAt = null): DogWorkShift
    {
        $token = strtolower($token);
        if (! Str::isUuid($token)) {
            throw new PetUnavailable('Invalid dog work request.');
        }
        $confirmedAt = ($confirmedAt ?? CarbonImmutable::now())->startOfSecond();
        $this->lifecycle->synchronizeOwner($user, $confirmedAt);

        return DB::transaction(function () use ($user, $token, $confirmedAt): DogWorkShift {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $shift = DogWorkShift::query()->where('user_id', $owner->id)->where('token', $token)->firstOrFail();
            if ($shift->completed_at !== null || $shift->cancelled_at !== null) {
                return $shift;
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($shift->pet_id);
            $shift = DogWorkShift::query()->lockForUpdate()->findOrFail($shift->id);
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
        }, attempts: 3);
    }
}
