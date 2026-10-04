<?php

namespace App\Modules\Pets\Actions;

use App\Models\DogWorkShift;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\DogWorkCompletion;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompleteDogWork
{
    public function __construct(private DogWorkCompletion $completion, private PetLifecycleSynchronization $lifecycle) {}

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
            $this->lifecycle->assertCanAdvance($owner, $confirmedAt);

            return $this->completion->complete($owner, $pet, $shift, $confirmedAt);
        }, attempts: 3);
    }
}
