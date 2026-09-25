<?php

namespace Database\Factories;

use App\Models\AssetUnlock;
use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\Enums\AssetCurrency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetUnlock> */
class AssetUnlockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_asset_id' => GameAsset::factory()->paid(),
            'currency' => AssetCurrency::Coins,
            'price_paid' => 100,
        ];
    }
}
