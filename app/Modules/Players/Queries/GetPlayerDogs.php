<?php

namespace App\Modules\Players\Queries;

use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Appearance\DTO\AppearanceAssetData;
use App\Modules\Appearance\DTO\PetAppearanceData;
use Illuminate\Database\Eloquent\Builder;

final class GetPlayerDogs
{
    /** @return list<array{id: int, name: string, breed: string, portraitId: int|null, backgroundId: int|null}> */
    public function handle(User $user, string $locale): array
    {
        $pets = $user->pets()->active()->with('dog')->oldest('id')->get();
        if ($pets->isEmpty()) {
            return [];
        }

        $assets = GameAsset::query()->where('is_active', true)
            ->where(function (Builder $query) use ($pets): void {
                foreach ($pets as $pet) {
                    $query->orWhere(fn (Builder $query) => $query->compatibleWith($pet));
                }
            })
            ->withExists(['unlocks as is_unlocked' => fn (Builder $query) => $query->whereBelongsTo($user)])
            ->orderBy('sort_order')->orderBy('id')->get();
        $appearanceAssets = AppearanceAssetData::fromCatalogue($assets, $locale);

        return array_values($pets->map(function (Pet $pet) use ($assets, $appearanceAssets, $locale): array {
            $compatibleAssets = $assets->filter(fn (GameAsset $asset): bool => isset($appearanceAssets[$asset->id]) && $asset->matches($pet))
                ->map(fn (GameAsset $asset): AppearanceAssetData => $appearanceAssets[$asset->id]);
            $appearance = PetAppearanceData::fromAssets($pet, array_values($compatibleAssets->all()));

            return [
                'id' => $pet->id,
                'name' => $pet->name,
                'breed' => $pet->dog->localizedName($locale),
                'portraitId' => $appearance->portraitId,
                'backgroundId' => $appearance->backgroundId,
            ];
        })->all());
    }
}
