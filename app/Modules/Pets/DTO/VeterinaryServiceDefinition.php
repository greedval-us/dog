<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\VeterinaryService;

final readonly class VeterinaryServiceDefinition
{
    public function __construct(
        public VeterinaryService $service,
        public int $price,
        public bool $validPrice,
        public int $intervalDays = 0,
        public int $healthRestorePercent = 0,
        public int $healthRecoveryBonus = 0,
    ) {}
}
