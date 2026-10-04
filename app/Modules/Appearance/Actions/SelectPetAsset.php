<?php

namespace App\Modules\Appearance\Actions;

use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use App\Modules\Appearance\Services\AssetAvailability;
use App\Modules\Pets\Services\PetLifecycle;
use Illuminate\Support\Facades\DB;

final class SelectPetAsset
{
    public function __construct(private AssetAvailability $availability, private PetLifecycle $lifecycle) {}

    public function handle(User $user, int $petId, int $assetId): void
    {
        $this->lifecycle->synchronizeOwner($user);

        DB::transaction(function () use ($user, $petId, $assetId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            if (! $pet->isActive()) {
                throw new AppearanceUnavailable('This dog is no longer active.');
            }
            $asset = GameAsset::query()->sharedLock()->findOrFail($assetId);
            $this->lifecycle->assertCanAdvance($owner);

            $this->availability->ensureAvailable($owner, $pet, $asset);

            if (! $asset->isFree() && ! $owner->assetUnlocks()->where('game_asset_id', $asset->id)->exists()) {
                throw new AppearanceUnavailable('Unlock this appearance before using it.');
            }

            $pet->setAttribute($asset->kind->petColumn(), $asset->id);
            $pet->save();
        }, 3);
    }
}
