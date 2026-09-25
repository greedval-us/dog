<?php

namespace Database\Seeders;

use App\Models\CharacterTrait;
use Illuminate\Database\Seeder;

class CharacterTraitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'friendly' => ['ru' => 'Дружелюбный', 'en' => 'Friendly'],
            'active' => ['ru' => 'Активный', 'en' => 'Active'],
            'loyal' => ['ru' => 'Верный', 'en' => 'Loyal'],
            'smart' => ['ru' => 'Умный', 'en' => 'Smart'],
            'fast_learner' => ['ru' => 'Быстро учится', 'en' => 'Quick learner'],
        ] as $code => $name) {
            CharacterTrait::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
