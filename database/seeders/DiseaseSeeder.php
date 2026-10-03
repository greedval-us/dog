<?php

namespace Database\Seeders;

use App\Models\Disease;
use Illuminate\Database\Seeder;

class DiseaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['digestive_upset', 'Расстройство желудка', 'Upset stomach', null, ['meal'], 10, 16,
                ['satiety_gain_percent' => -25, 'health_gain_percent' => -20, 'mood_decay_percent' => 25]],
            ['skin_irritation', 'Раздражение кожи', 'Skin irritation', null, ['wash', 'care'], 6, 10,
                ['cleanliness_gain_percent' => -25, 'mood_decay_percent' => 20]],
            ['sore_paws', 'Повреждение подушечек лап', 'Sore paw pads', null, ['walk'], 7, 11,
                ['energy_cost_percent' => 25, 'mood_gain_percent' => -15]],
            ['muscle_strain', 'Растяжение мышц', 'Muscle strain', 'training', [], 5, 8,
                ['energy_cost_percent' => 30, 'energy_gain_percent' => -20]],
            ['exhaustion', 'Переутомление', 'Exhaustion', 'play', [], 12, 18,
                ['energy_cost_percent' => 20, 'energy_gain_percent' => -25, 'mood_gain_percent' => -20]],
        ] as [$code, $ru, $en, $group, $variants, $minimum, $maximum, $modifiers]) {
            Disease::query()->firstOrCreate(['code' => $code], [
                'name' => ['ru' => $ru, 'en' => $en],
                'description' => [
                    'ru' => 'Собака нездорова. Эффект сохраняется до лечения.',
                    'en' => 'Your dog is unwell. The effect lasts until treatment.',
                ],
                'acquisition_rules' => ['group' => $group, 'variants' => $variants, 'daily_min' => $minimum, 'daily_max' => $maximum],
                'modifiers' => $modifiers,
                'is_active' => true,
            ]);
        }
    }
}
