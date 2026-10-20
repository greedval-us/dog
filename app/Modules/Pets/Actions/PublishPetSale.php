<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\PetOwnershipEligibility;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class PublishPetSale
{
    public const MAXIMUM_PRICE = 1000000;

    public function __construct(private PetLifecycle $lifecycle, private PetOwnershipEligibility $eligibility, private PetDecayCalculator $decay) {}

    public function handle(User $user, int $petId, int $price): PetSaleListing
    {
        if ($price < 1 || $price > self::MAXIMUM_PRICE) {
            throw new PetUnavailable('ownership.errors.price');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $price): PetSaleListing {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $this->lifecycle->assertCanAdvance($owner);
            $pet->advanceTo(now(), $this->decay);
            if (($reason = $this->eligibility->reason($pet)) !== null) {
                throw new PetUnavailable($reason);
            }
            $pet->save();

            return PetSaleListing::query()->updateOrCreate(['pet_id' => $pet->id], [
                'user_id' => $owner->id, 'price' => $price, 'is_active' => true,
            ]);
        }, attempts: 3);
    }

    public function withdraw(User $user, int $petId): void
    {
        DB::transaction(function () use ($user, $petId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            PetSaleListing::query()->where('pet_id', $pet->id)->where('user_id', $owner->id)->update(['is_active' => false]);
        }, attempts: 3);
    }
}
