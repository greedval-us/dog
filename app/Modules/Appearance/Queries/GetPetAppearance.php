<?php

namespace App\Modules\Appearance\Queries;

use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\DTO\AppearanceAssetData;
use App\Modules\Appearance\DTO\PetAppearanceData;
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
            ->map(fn (GameAsset $asset): AppearanceAssetData => AppearanceAssetData::fromModel($asset, $locale));

        return PetAppearanceData::fromAssets($pet, array_values($assets->all()));
    }
}
