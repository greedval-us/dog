<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\VeterinaryService;

final readonly class PurchaseVeterinaryServiceData
{
    public function __construct(
        public int $petId,
        public VeterinaryService $service,
        public ?int $diseaseEpisodeId,
        public int $expectedPrice,
        public string $token,
    ) {}
}
