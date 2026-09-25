<?php

namespace App\Queries;

use App\Data\PetProfileData;
use App\Models\User;

final class GetPrimaryPet
{
    public function handle(User $user, string $locale): ?PetProfileData
    {
        $pet = $user->pets()->with('dog')->oldest('id')->first();

        return $pet === null ? null : PetProfileData::fromModel($pet, $pet->dog, $locale);
    }
}
