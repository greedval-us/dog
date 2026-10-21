<?php

namespace App\Modules\Kennel\DTO;

use App\Modules\Kennel\Enums\KennelPetAge;

final readonly class PurchaseKennelPetData
{
    public function __construct(
        public int $dogId,
        public string $name,
        public int $expectedPrice,
        public string $token,
        public int $ageMonths = KennelPetAge::DEFAULT_MONTHS,
    ) {}
}
