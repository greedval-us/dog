<?php

namespace Database\Factories;

use App\Models\Dog;
use App\Models\GameAsset;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Appearance\Enums\AssetKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameAsset> */
class GameAssetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(),
            'kind' => AssetKind::Portrait,
            'name' => ['ru' => 'Портрет', 'en' => 'Portrait'],
            'dog_id' => Dog::factory(),
            'coat_color' => 'black',
            'pose' => 'standing',
            'image_path' => 'appearance/test/portrait.png',
            'icon_path' => 'appearance/test/icon.png',
            'coins_price' => null,
            'gems_price' => null,
            'is_active' => true,
        ];
    }

    public function paid(AssetCurrency $currency = AssetCurrency::Coins, int $price = 100): static
    {
        return $this->state(fn (): array => [
            'coins_price' => $currency === AssetCurrency::Coins ? $price : null,
            'gems_price' => $currency === AssetCurrency::Gems ? $price : null,
        ]);
    }

    public function purchasableWithEitherCurrency(int $coins = 100, int $gems = 10): static
    {
        return $this->state(fn (): array => ['coins_price' => $coins, 'gems_price' => $gems]);
    }

    public function background(): static
    {
        return $this->state(fn (): array => [
            'kind' => AssetKind::Background, 'dog_id' => null, 'coat_color' => null, 'pose' => null,
        ]);
    }
}
