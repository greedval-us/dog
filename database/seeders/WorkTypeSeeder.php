<?php

namespace Database\Seeders;

use App\Models\WorkType;
use Illuminate\Database\Seeder;

class WorkTypeSeeder extends Seeder
{
    public function run(): void
    {
        WorkType::query()->firstOrCreate(['code' => 'shelter_helper'], [
            'name' => ['ru' => 'Помощник в приюте', 'en' => 'Shelter helper'],
            'description' => ['ru' => 'Помоги приюту с повседневными делами: подготовь миски, наведи порядок и позаботься об уюте.', 'en' => 'Help with everyday tasks at the shelter: prepare bowls, tidy up and make the dogs comfortable.'],
            'coins_reward' => 50,
            'gems_bonus' => 5,
            'is_active' => true,
        ]);
    }
}
