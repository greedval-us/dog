<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompleteDogWork
{
    public function __construct(private PetActivityManager $activities, private PlayerWallet $wallet, private PetDecayCalculator $decay) {}

    public function handle(User $user, string $token): DogWorkShift
    {
        $token = strtolower($token);
        if (! Str::isUuid($token)) {
            throw new PetUnavailable('Invalid dog work request.');
        }

        return DB::transaction(function () use ($user, $token): DogWorkShift {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $shift = DogWorkShift::query()->where('user_id', $owner->id)->where('token', $token)->firstOrFail();
            if ($shift->completed_at !== null) {
                return $shift;
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($shift->pet_id);
            $shift = DogWorkShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->ends_at->greaterThan(now()->startOfSecond())) {
                throw new PetUnavailable('This job has not finished yet.');
            }
            $operation = 'dog-work:'.$shift->id;
            if (CurrencyTransaction::query()->whereBelongsTo($owner)->whereIn('operation_key', [$operation.':coins', $operation.':gems'])->exists()) {
                throw new PetUnavailable('This job reward has already been recorded without a completed shift.');
            }
            if (! $this->activities->complete($owner, $pet->id, $shift->activity_token)) {
                throw new PetUnavailable('Your dog is no longer assigned to this job.');
            }

            $pet->refresh()->advanceTo(now(), $this->decay);
            $pet->save();
            $this->wallet->change($owner, 'coins', $shift->coins_reward, $operation.':coins', 'dog_work');
            if ($shift->gems_reward > 0) {
                $this->wallet->change($owner, 'gems', $shift->gems_reward, $operation.':gems', 'dog_work');
            }
            $shift->update(['completed_at' => now()->startOfSecond()]);

            return $shift;
        }, attempts: 3);
    }
}
