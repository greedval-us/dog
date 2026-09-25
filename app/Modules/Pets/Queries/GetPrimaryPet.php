<?php

namespace App\Modules\Pets\Queries;

use App\Models\User;
use App\Modules\Pets\DTO\PetProfileData;

final class GetPrimaryPet
{
    public function handle(User $user, string $locale): ?PetProfileData
    {
        $pet = $user->pets()->with(['dog', 'characterTraits'])->oldest('id')->first();

        return $pet === null ? null : PetProfileData::fromModel($pet, $pet->dog, $locale);
    }
}
