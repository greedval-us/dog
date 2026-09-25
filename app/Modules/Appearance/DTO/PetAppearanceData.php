<?php

namespace App\Modules\Appearance\DTO;

final readonly class PetAppearanceData
{
    /** @param list<AppearanceAssetData> $assets */
    public function __construct(
        public array $assets,
        public ?int $portraitId,
        public ?int $backgroundId,
    ) {}

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
