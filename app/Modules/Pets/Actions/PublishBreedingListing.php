<?php

namespace App\Modules\Pets\Actions;

use App\Models\BreedingListing;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use App\Modules\Pets\Queries\BreedingEligibility;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Calculators\PlayerLevelRules;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class PublishBreedingListing
{
    public function __construct(private PetLifecycle $lifecycle, private BreedingEligibility $eligibility, private PetDecayCalculator $states) {}

    public function handle(User $user, int $petId, int $price): BreedingListing
    {
        if ($price < 1 || $price > 1000000) {
            throw new BreedingUnavailable('breeding.errors.invalid');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $price): BreedingListing {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new BreedingUnavailable('breeding.errors.blocked');
            }
            if (PlayerLevelRules::progress($owner->experience)['level'] < config('doglive.breeding_minimum_level', 5)) {
                throw new BreedingUnavailable('breeding.errors.level');
            }
            $pet = Pet::query()->lockForUpdate()->find($petId);
            if ($pet === null || $pet->user_id !== $owner->id || $pet->sex !== PetSex::Male || ! $pet->is_purebred) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            $at = now();
            $this->lifecycle->assertCanAdvance($owner, $at);
            $pet->advanceTo($at, $this->states);
            $reason = $this->eligibility->reason($pet, $at);
            if ($reason !== null) {
                throw new BreedingUnavailable($reason);
            }

            return BreedingListing::query()->updateOrCreate(['pet_id' => $pet->id], ['user_id' => $owner->id, 'price' => $price, 'is_active' => true]);
        }, attempts: 3);
    }

    public function withdraw(User $user, BreedingListing $listing): void
    {
        DB::transaction(function () use ($user, $listing): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $pet = Pet::query()->lockForUpdate()->find($listing->pet_id);
            $locked = BreedingListing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($owner->status !== PlayerStatus::Active || $locked->user_id !== $owner->id || $pet?->user_id !== $owner->id) {
                throw new BreedingUnavailable('breeding.errors.unavailable');
            }
            $locked->update(['is_active' => false]);
        }, attempts: 3);
    }
}
