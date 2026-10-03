<?php

namespace App\Modules\Pets\Actions;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class RetirePet
{
    public function __construct(private PetLifecycleSynchronization $lifecycle, private PetDecayCalculator $decay) {}

    public function handle(User $user, int $petId): Pet
    {
        $at = now()->startOfSecond();
        $this->lifecycle->synchronizeOwner($user, $at);

        return DB::transaction(function () use ($user, $petId, $at): Pet {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            if ($pet->died_at !== null) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            if ($pet->retired_at !== null) {
                return $pet;
            }
            $pet->advanceTo($at, $this->decay);
            if (! $pet->canRetire($at)) {
                throw new PetUnavailable('Retirement is available three months after birth.');
            }
            $pet->retired_at = $at;
            $this->lifecycle->persist($pet);

            return $pet;
        }, attempts: 3);
    }
}
