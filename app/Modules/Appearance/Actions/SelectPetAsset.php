<?php

namespace App\Modules\Appearance\Actions;

use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use App\Modules\Appearance\Services\AssetAvailability;
use Illuminate\Support\Facades\DB;

final class SelectPetAsset
{
    public function __construct(private AssetAvailability $availability) {}

    public function handle(User $user, int $petId, int $assetId): void
    {
        DB::transaction(function () use ($user, $petId, $assetId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $asset = GameAsset::query()->sharedLock()->findOrFail($assetId);

            $this->availability->ensureAvailable($owner, $pet, $asset);

            if (! $asset->isFree() && ! $owner->assetUnlocks()->where('game_asset_id', $asset->id)->exists()) {
                throw new AppearanceUnavailable('Unlock this appearance before using it.');
            }

            $pet->setAttribute($asset->kind->petColumn(), $asset->id);
            $pet->save();
        }, 3);
    }
}
