<?php

namespace App\Modules\Pets\Queries;

use App\Models\BreedingPartner;
use App\Models\Pet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PublicPetProfileData;

/** @phpstan-import-type Profile from PublicPetProfileData */
final class GetPetProfile
{
    public function __construct(private PetDecayCalculator $states) {}

    /** @return Profile */
    public function handle(int $petId, string $locale): array
    {
        $pet = Pet::query()->with(['dog', 'characterTraits'])->findOrFail($petId);

        if (! BreedingPartner::query()->where('pet_id', $pet->id)->exists()) {
            $pet->advanceTo(now(), $this->states);
        }

        return PublicPetProfileData::fromModel($pet, $locale);
    }
}
