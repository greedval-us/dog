<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingLitter;
use App\Models\GameEventEntry;
use App\Models\Pet;

final class PetOwnershipEligibility
{
    public function reason(Pet $pet): ?string
    {
        if (! $pet->isActive()) {
            return 'ownership.errors.inactive';
        }
        if ($pet->isBusy()) {
            return 'ownership.errors.busy';
        }
        if (GameEventEntry::query()->involvingPets([$pet->id])->whereIn('status', ['registered', 'frozen'])->exists()) {
            return 'ownership.errors.event';
        }
        if (BreedingLitter::query()->whereNull('delivered_at')->where(function ($query) use ($pet): void {
            $query->where('father_id', $pet->id)->orWhere('mother_id', $pet->id);
        })->exists()) {
            return 'ownership.errors.litter';
        }

        return null;
    }
}
