<?php

namespace App\Modules\Appearance\DTO;

use App\Modules\Appearance\Enums\AssetCurrency;

final readonly class PurchaseAssetData
{
    public function __construct(
        public int $assetId,
        public int $expectedPrice,
        public AssetCurrency $expectedCurrency,
    ) {}
}
