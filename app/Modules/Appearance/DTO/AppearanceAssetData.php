<?php

namespace App\Modules\Appearance\DTO;

use App\Models\GameAsset;
use App\Modules\Appearance\Enums\AssetCurrency;
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

    /**
     * @param  iterable<GameAsset>  $assets
     * @return array<int, self>
     */
    public static function fromCatalogue(iterable $assets, string $locale): array
    {
        $catalogue = [];
        foreach ($assets as $asset) {
            if ($asset->hasValidPrice() && $asset->hasFiles()) {
                $catalogue[$asset->id] = self::fromModel($asset, $locale);
            }
        }

        return $catalogue;
    }

    public static function fromModel(GameAsset $asset, string $locale): self
    {
        $prices = [];
        foreach (AssetCurrency::cases() as $currency) {
            $amount = $asset->priceFor($currency);
            if ($amount !== null) {
                $prices[] = new AssetPriceData($currency, $amount);
            }
        }

        return new self(
            $asset->id, $asset->kind, $asset->localizedName($locale), $prices,
            $asset->isFree() || (bool) $asset->getAttribute('is_unlocked'),
        );
    }

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
