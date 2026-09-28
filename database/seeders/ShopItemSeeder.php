<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ShopOffer;
use Illuminate\Database\Seeder;

class ShopItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([ItemCategorySeeder::class, StatusEffectSeeder::class]);
        $categories = ItemCategory::query()->pluck('id', 'code');
        $sortOrder = 0;

        foreach ($this->catalogue() as $category => $items) {
            foreach ($items as [$code, $ru, $en, $descriptionRu, $descriptionEn, $quality, $uses, $price, $currency]) {
                $item = Item::query()->firstOrCreate(['code' => $code], [
                    'item_category_id' => $categories[$category],
                    'name' => ['ru' => $ru, 'en' => $en],
                    'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                    'quality' => $quality,
                    'usage_limit' => $uses,
                    'characteristics' => [],
                    'bonuses' => config('item_bonuses.'.$code.'.bonuses', []),
                    'is_active' => true,
                ]);

                if ($item->wasRecentlyCreated) {
                    (new ItemEffectRuleSeeder)->seedFor($item);
                }

                ShopOffer::query()->firstOrCreate(['item_id' => $item->id, 'currency' => $currency], [
                    'price' => $price,
                    'stock' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }

    /** @return array<string, list<array{string, string, string, string, string, int, int, int, string}>> */
    private function catalogue(): array
    {
        return [
            'food' => [
                ['daily_kibble', 'Корм «На каждый день»', 'Everyday kibble', 'Хрустящие гранулы с курицей в удобном небольшом пакете.', 'Crunchy chicken kibble in a handy small bag.', 3, 5, 35, 'coins'],
                ['beef_treats', 'Мясные лакомства', 'Beef treats', 'Небольшие кусочки сушёной говядины в закрывающемся пакете.', 'Small dried beef bites in a resealable pouch.', 5, 8, 65, 'coins'],
                ['salmon_menu', 'Меню с лососем', 'Salmon menu', 'Порционный набор корма с лососем для особого меню.', 'A portioned salmon food selection for a special menu.', 8, 10, 80, 'coins'],
            ],
            'sports' => [
                ['training_cones', 'Тренировочные конусы', 'Training cones', 'Яркий набор невысоких конусов для площадки.', 'A bright set of low cones for the training ground.', 4, 20, 90, 'coins'],
                ['agility_bar', 'Барьер для аджилити', 'Agility hurdle', 'Лёгкий сборный барьер с регулируемой перекладиной.', 'A lightweight hurdle with an adjustable bar.', 6, 30, 150, 'coins'],
                ['agility_tunnel', 'Мягкий тоннель', 'Soft agility tunnel', 'Складной тканевый тоннель с широким входом.', 'A folding fabric tunnel with a wide entrance.', 8, 40, 120, 'coins'],
            ],
            'clothing' => [
                ['sage_bandana', 'Бандана «Лесная»', 'Forest bandana', 'Мягкая хлопковая бандана спокойного зелёного цвета.', 'A soft cotton bandana in a gentle green shade.', 3, 20, 45, 'coins'],
                ['raincoat', 'Дождевик «Облачко»', 'Little Cloud raincoat', 'Лёгкий голубой дождевик с капюшоном и застёжками.', 'A light blue raincoat with a hood and fastenings.', 5, 30, 110, 'coins'],
                ['knitted_sweater', 'Вязаный свитер', 'Knitted sweater', 'Уютный свитер с фактурной вязкой и мягким воротником.', 'A cosy textured knit with a soft collar.', 8, 40, 100, 'coins'],
            ],
            'collars' => [
                ['everyday_collar', 'Ошейник «Базовый»', 'Everyday collar', 'Нейлоновый ошейник с регулировкой длины и простой пряжкой.', 'An adjustable nylon collar with a simple buckle.', 3, 25, 50, 'coins'],
                ['woven_collar', 'Плетёный ошейник', 'Woven collar', 'Плотное плетение, спокойные цвета и металлическое кольцо.', 'A close weave, muted colours and a metal ring.', 5, 40, 95, 'coins'],
                ['leather_collar', 'Кожаный ошейник', 'Leather collar', 'Лаконичный кожаный ошейник с мягкой внутренней стороной.', 'A simple leather collar with a soft lining.', 8, 60, 100, 'coins'],
            ],
            'leashes' => [
                ['walking_leash', 'Поводок «Прогулка»', 'Walking leash', 'Классический тканевый поводок с удобной петлёй для руки.', 'A classic fabric leash with a comfortable hand loop.', 3, 25, 55, 'coins'],
                ['long_leash', 'Длинный поводок', 'Long leash', 'Длинная стропа с карабином и контрастной прострочкой.', 'A long webbing lead with a clip and contrasting stitching.', 5, 40, 100, 'coins'],
                ['retractable_leash', 'Поводок-рулетка', 'Retractable leash', 'Компактная рулетка с широкой лентой и округлой ручкой.', 'A compact reel with a wide tape and a rounded handle.', 8, 60, 120, 'coins'],
            ],
            'care' => [
                ['soft_brush', 'Мягкая щётка', 'Soft brush', 'Небольшая щётка с мягкой щетиной и деревянной ручкой.', 'A small soft-bristled brush with a wooden handle.', 3, 15, 40, 'coins'],
                ['gentle_shampoo', 'Шампунь «Нежный»', 'Gentle shampoo', 'Флакон шампуня для собак с удобным дозатором.', 'A bottle of dog shampoo with a handy dispenser.', 5, 8, 70, 'coins'],
                ['grooming_set', 'Набор для груминга', 'Grooming set', 'Расчёска, щётка и полотенце в аккуратном дорожном чехле.', 'A comb, brush and towel in a neat travel pouch.', 8, 30, 90, 'coins'],
            ],
            'toys' => [
                ['rubber_ball', 'Резиновый мяч', 'Rubber ball', 'Упругий синий мяч с рельефной поверхностью.', 'A bouncy blue ball with a textured surface.', 3, 15, 30, 'coins'],
                ['rope_toy', 'Канатик с узлами', 'Knotted rope', 'Плетёный хлопковый канатик с крупными узлами по краям.', 'A woven cotton rope with large knots at each end.', 5, 25, 60, 'coins'],
                ['snuffle_mat', 'Нюхательный коврик', 'Snuffle mat', 'Мягкий коврик с кармашками и лентами в природных оттенках.', 'A soft mat with pockets and ribbons in natural colours.', 8, 35, 70, 'coins'],
            ],
        ];
    }
}
