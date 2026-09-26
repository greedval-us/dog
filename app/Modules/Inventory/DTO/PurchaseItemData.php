<?php

namespace App\Modules\Inventory\DTO;

final readonly class PurchaseItemData
{
    public function __construct(
        public int $offerId,
        public int $itemId,
        public string $expectedCurrency,
        public int $expectedPrice,
        public string $token,
    ) {}
}
