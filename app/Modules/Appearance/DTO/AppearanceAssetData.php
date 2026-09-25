<?php

namespace App\Modules\Appearance\DTO;

use App\Modules\Appearance\Enums\AssetKind;

final readonly class AppearanceAssetData
{
    /** @param list<AssetPriceData> $prices */
    public function __construct(
        public int $id,
        public AssetKind $kind,
        public string $name,
        public array $prices,
        public bool $unlocked,
    ) {}

    /** @return array{id: int, kind: string, name: string, prices: list<array{currency: string, amount: int}>, unlocked: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind->value, 'name' => $this->name,
            'prices' => array_map(fn (AssetPriceData $price): array => $price->toArray(), $this->prices),
            'unlocked' => $this->unlocked,
        ];
    }
}
