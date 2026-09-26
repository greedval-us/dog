<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'food' => ['ru' => 'Еда', 'en' => 'Food'],
            'sports' => ['ru' => 'Спортивный инвентарь', 'en' => 'Sports equipment'],
            'clothing' => ['ru' => 'Одежда', 'en' => 'Clothing'],
            'collars' => ['ru' => 'Ошейники', 'en' => 'Collars'],
            'leashes' => ['ru' => 'Поводки', 'en' => 'Leashes'],
            'care' => ['ru' => 'Уход', 'en' => 'Care'],
            'toys' => ['ru' => 'Игрушки', 'en' => 'Toys'],
        ];

        $sortOrder = 0;

        foreach ($categories as $code => $name) {
            ItemCategory::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);
        }
    }
}
