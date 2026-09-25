<?php

namespace App\Modules\Appearance\DTO;

use App\Modules\Appearance\Enums\AssetCurrency;

final readonly class AssetPriceData
{
    public function __construct(public AssetCurrency $currency, public int $amount) {}

    /** @return array{currency: string, amount: int} */
    public function toArray(): array
    {
        return ['currency' => $this->currency->value, 'amount' => $this->amount];
    }
}
