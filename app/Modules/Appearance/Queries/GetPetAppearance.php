<?php

namespace App\Modules\Appearance\Queries;

use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\DTO\AppearanceAssetData;
use App\Modules\Appearance\DTO\AssetPriceData;
use App\Modules\Appearance\DTO\PetAppearanceData;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Appearance\Enums\AssetKind;
use Illuminate\Database\Eloquent\Builder;

final class GetPetAppearance
{
    public function handle(User $user, int $petId, string $locale): PetAppearanceData
    {
        $pet = $user->pets()->findOrFail($petId);
        $assets = GameAsset::query()->where('is_active', true)->compatibleWith($pet)
            ->withExists(['unlocks as is_unlocked' => fn (Builder $query) => $query->whereBelongsTo($user)])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (GameAsset $asset): bool => $asset->hasValidPrice() && $asset->hasFiles())
            ->map(function (GameAsset $asset) use ($locale): AppearanceAssetData {
                $prices = [];
                foreach (AssetCurrency::cases() as $currency) {
                    $amount = $asset->priceFor($currency);
                    if ($amount !== null) {
                        $prices[] = new AssetPriceData($currency, $amount);
                    }
                }

                return new AppearanceAssetData(
                    $asset->id, $asset->kind, $asset->localizedName($locale), $prices,
                    $asset->isFree() || (bool) $asset->getAttribute('is_unlocked'),
                );
            });

        $selected = [];
        foreach (AssetKind::cases() as $kind) {
            $available = $assets->filter(fn (AppearanceAssetData $asset): bool => $asset->kind === $kind && $asset->unlocked);
            $currentId = $pet->getAttribute($kind->petColumn());
            $current = $available->first(fn (AppearanceAssetData $asset): bool => $asset->id === $currentId);
            $fallback = $available->first(fn (AppearanceAssetData $asset): bool => $asset->prices === []);
            $selected[$kind->value] = ($current ?? $fallback)?->id;
        }

        return new PetAppearanceData(array_values($assets->all()), $selected['portrait'], $selected['background']);
    }
}
