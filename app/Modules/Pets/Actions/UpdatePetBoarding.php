<?php

namespace App\Modules\Pets\Actions;

use App\Models\User;
use App\Modules\Pets\Enums\PetBoardingPlan;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class UpdatePetBoarding
{
    public function __construct(private PetLifecycle $lifecycle, private RecordPetOwnerActivity $activity) {}

    public function handle(User $user, PetBoardingPlan $plan): void
    {
        DB::transaction(function () use ($user, $plan): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Boarding is unavailable for this account.');
            }
            $this->activity->handle($owner, now());
            $this->lifecycle->synchronizeOwner($owner);
            $owner->refresh();
            if ($owner->boarding_plan !== $plan) {
                $owner->boarding_plan = $plan;
                $owner->boarding_enabled_at = $plan === PetBoardingPlan::Off ? null : now();
                $owner->save();
            }
        }, attempts: 3);
    }
}
