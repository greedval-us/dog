<?php

namespace Database\Seeders;

use App\Models\Dog;
use App\Models\GameAsset;
use App\Modules\Appearance\Enums\AssetKind;
use Illuminate\Database\Seeder;

class GameAssetSeeder extends Seeder
{
    public function run(): void
    {
        $originalCoats = ['german_shepherd' => 'black_tan', 'pit_bull' => 'fawn', 'dachshund' => 'red'];
        $dogs = Dog::query()->whereIn('breed', array_keys($originalCoats))->get();

        foreach ($dogs as $dog) {
            foreach ($dog->coat_colors as $coat => $names) {
                foreach (['standing', 'sitting'] as $pose) {
                    $original = $originalCoats[$dog->breed] === $coat;
                    $directory = "appearance/{$dog->breed}/{$coat}";
                    GameAsset::query()->firstOrCreate(['code' => "{$dog->breed}_{$coat}_{$pose}"], [
                        'kind' => AssetKind::Portrait,
                        'name' => [
                            'ru' => $names['ru'].' · '.($pose === 'standing' ? 'Стоя' : 'Сидя'),
                            'en' => $names['en'].' · '.ucfirst($pose),
                        ],
                        'dog_id' => $dog->id,
                        'coat_color' => $coat,
                        'pose' => $pose,
                        'image_path' => $original && $pose === 'standing' ? "breeds/{$dog->breed}/portrait.png" : "{$directory}/{$pose}.png",
                        'icon_path' => $original ? "breeds/{$dog->breed}/icon.png" : "{$directory}/icon.png",
                        'coins_price' => $pose === 'standing' ? null : 100,
                        'gems_price' => $pose === 'standing' ? null : 10,
                        'sort_order' => $pose === 'standing' ? 0 : 10,
                    ]);
                }
            }
        }

        foreach ([
            ['alpine_meadow', 'Альпийский луг', 'Alpine meadow', 'scenes/pet-profile.png', null, null],
            ['forest_glade', 'Лесная поляна', 'Forest glade', 'appearance/backgrounds/forest_glade.png', null, null],
            ['moonlit_lake', 'Лунное озеро', 'Moonlit lake', 'appearance/backgrounds/moonlit_lake.png', 100, 10],
        ] as [$code, $ru, $en, $path, $coinsPrice, $gemsPrice]) {
            GameAsset::query()->firstOrCreate(['code' => $code], [
                'kind' => AssetKind::Background,
                'name' => ['ru' => $ru, 'en' => $en],
                'image_path' => $path,
                'icon_path' => $path,
                'coins_price' => $coinsPrice,
                'gems_price' => $gemsPrice,
                'sort_order' => $coinsPrice === null ? 0 : 10,
            ]);
        }
    }
}
