<?php

namespace App\Modules\Pets\DTO;

final readonly class PurchasePetSlotData
{
    public function __construct(
        public int $slot,
        public string $currency,
        public int $expectedPrice,
    ) {}
}
