<?php

namespace App\Modules\Kennel\DTO;

final readonly class PurchaseKennelPetData
{
    public function __construct(
        public int $dogId,
        public string $name,
        public int $expectedPrice,
        public string $token,
    ) {}
}
