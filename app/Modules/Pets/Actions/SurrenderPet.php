<?php

namespace App\Modules\Pets\Actions;

use App\Models\BreedingListing;
use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\PetOwnershipEligibility;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class SurrenderPet
{
    public function __construct(private PetLifecycle $lifecycle, private PetOwnershipEligibility $eligibility, private PetDecayCalculator $decay) {}

    public function handle(User $user, int $petId): Pet
    {
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId): Pet {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $this->lifecycle->assertCanAdvance($owner);
            if ($pet->surrendered_at !== null) {
                return $pet;
            }
            $pet->advanceTo(now(), $this->decay);
            if (($reason = $this->eligibility->reason($pet)) !== null) {
                throw new PetUnavailable($reason);
            }
            $pet->forceFill(['surrendered_at' => now(), 'is_favorite' => false])->save();
            PetSaleListing::query()->where('pet_id', $pet->id)->update(['is_active' => false]);
            BreedingListing::query()->where('pet_id', $pet->id)->update(['is_active' => false]);
            PetHistoryEntry::query()->firstOrCreate(['pet_id' => $pet->id, 'source_key' => 'ownership:surrender'], [
                'kind' => 'action', 'event_code' => 'surrendered',
                'title' => ['ru' => 'Передача в приют', 'en' => 'Transferred to a shelter'],
                'occurred_at' => now(),
            ]);

            return $pet;
        }, attempts: 3);
    }
}
