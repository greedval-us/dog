<?php

namespace App\Modules\Appearance\Services;

use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use App\Modules\Players\Enums\PlayerStatus;

final class AssetAvailability
{
    public function ensureAvailable(User $user, Pet $pet, GameAsset $asset): void
    {
        if ($user->status !== PlayerStatus::Active) {
            throw new AppearanceUnavailable('Your account is blocked.');
        }

        if (! $asset->is_active || ! $asset->matches($pet) || ! $asset->hasValidPrice() || ! $asset->hasFiles()) {
            throw new AppearanceUnavailable('This appearance is unavailable for your dog.');
        }
    }
}
