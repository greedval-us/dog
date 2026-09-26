<?php

namespace App\Modules\Appearance\DTO;

use App\Models\Pet;
use App\Modules\Appearance\Enums\AssetKind;

final readonly class PetAppearanceData
{
    /** @param list<AppearanceAssetData> $assets */
    public function __construct(
        public array $assets,
        public ?int $portraitId,
        public ?int $backgroundId,
    ) {}

    /** @param list<AppearanceAssetData> $assets */
    public static function fromAssets(Pet $pet, array $assets): self
    {
        $catalogue = collect($assets);
        $selected = [];
        foreach (AssetKind::cases() as $kind) {
            $available = $catalogue->filter(fn (AppearanceAssetData $asset): bool => $asset->kind === $kind && $asset->unlocked);
            $currentId = $pet->getAttribute($kind->petColumn());
            $current = $available->first(fn (AppearanceAssetData $asset): bool => $asset->id === $currentId);
            $fallback = $available->first(fn (AppearanceAssetData $asset): bool => $asset->prices === []);
            $selected[$kind->value] = ($current ?? $fallback)?->id;
        }

        return new self($assets, $selected['portrait'], $selected['background']);
    }

    /** @return array{assets: list<array{id: int, kind: string, name: string, prices: list<array{currency: string, amount: int}>, unlocked: bool}>, portraitId: int|null, backgroundId: int|null} */
    public function toArray(): array
    {
        return [
            'assets' => array_map(fn (AppearanceAssetData $asset): array => $asset->toArray(), $this->assets),
            'portraitId' => $this->portraitId,
            'backgroundId' => $this->backgroundId,
        ];
    }
}
