<?php

namespace App\Modules\Pets\Queries;

use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetProfileData;

final class GetPrimaryPet
{
    public function __construct(private PetDecayCalculator $states) {}

    public function handle(User $user, string $locale, ?int $petId = null): ?PetProfileData
    {
        $query = $user->pets()->with(['dog', 'characterTraits', 'titles'])->oldest('id');
        $pet = $petId === null ? $query->active()->first() : $query->findOrFail($petId);
        $pet?->advanceTo(now(), $this->states);

        return $pet === null ? null : PetProfileData::fromModel($pet, $pet->dog, $locale);
    }
}
