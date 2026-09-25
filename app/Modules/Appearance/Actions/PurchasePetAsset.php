<?php

namespace App\Modules\Appearance\Actions;

use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use App\Modules\Appearance\Services\AssetAvailability;
use Illuminate\Support\Facades\DB;

final class PurchasePetAsset
{
    public function __construct(private AssetAvailability $availability) {}

    public function handle(User $user, int $petId, PurchaseAssetData $data): void
    {
        DB::transaction(function () use ($user, $petId, $data): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $asset = GameAsset::query()->lockForUpdate()->findOrFail($data->assetId);

            $this->availability->ensureAvailable($owner, $pet, $asset);

            if (! $asset->isFree() && ! $owner->assetUnlocks()->where('game_asset_id', $asset->id)->exists()) {
                $price = $asset->priceFor($data->expectedCurrency);

                if ($price === null || $price !== $data->expectedPrice) {
                    throw new AppearanceUnavailable('The price has changed. Refresh the page before purchasing.');
                }

                $currency = $data->expectedCurrency->value;
                $charged = User::query()->whereKey($owner->id)->where($currency, '>=', $price)
                    ->decrement($currency, $price);

                if ($charged !== 1) {
                    throw new AppearanceUnavailable('You do not have enough currency for this appearance.');
                }

                $owner->assetUnlocks()->create([
                    'game_asset_id' => $asset->id,
                    'currency' => $data->expectedCurrency,
                    'price_paid' => $price,
                ]);
            }

            $pet->setAttribute($asset->kind->petColumn(), $asset->id);
            $pet->save();
        }, 3);
    }
}
