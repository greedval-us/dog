<?php

namespace App\Modules\Kennel\DTO;

use App\Modules\Kennel\Enums\KennelPetAge;

final readonly class AdoptStarterPetData
{
    public function __construct(public int $dogId, public string $name, public int $ageMonths = KennelPetAge::DEFAULT_MONTHS) {}
}
