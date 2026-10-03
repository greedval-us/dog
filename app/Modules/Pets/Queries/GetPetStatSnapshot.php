<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Modules\Pets\DTO\PetStatSnapshot;
use App\Modules\Pets\Enums\PetStat;

final class GetPetStatSnapshot
{
    public function handle(Pet $pet): PetStatSnapshot
    {
        $values = [];
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $values[$stat->value] = (int) $pet->getAttribute($stat->value);
            $potentials[$stat->value] = (int) $pet->getAttribute($stat->potentialColumn());
        }

        return new PetStatSnapshot($values, $potentials);
    }
}
