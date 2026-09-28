<?php

namespace Database\Seeders;

use App\Models\StatusEffect;
use Illuminate\Database\Seeder;

class StatusEffectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['poisoning', 'Лёгкое отравление', 'Mild poisoning', 1800, ['energy_cost_percent' => 20, 'mood_gain_percent' => -20],
                'Реакция на некачественную еду: расход энергии +20%, прибавка настроения −20%. Пройдёт само через 30 минут.',
                'A reaction to low-quality food: energy cost +20%, mood gain −20%. Wears off after 30 minutes.'],
            ['minor_injury', 'Лёгкая травма', 'Minor injury', 1200, ['energy_cost_percent' => 25],
                'Неудача с некачественным снаряжением: расход энергии +25%. Пройдёт само через 20 минут.',
                'An accident with low-quality equipment: energy cost +25%. Wears off after 20 minutes.'],
        ] as [$code, $ru, $en, $duration, $modifiers, $descriptionRu, $descriptionEn]) {
            StatusEffect::query()->firstOrCreate(['code' => $code], [
                'kind' => 'debuff', 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $duration,
            ]);
        }

        foreach ([
            ['energized', 'buff', 'Прилив сил', 'Energized', 'После питательной еды: расход энергии −15%.', 'After nutritious food: energy cost −15%.', ['energy_cost_percent' => -15], 1800, null],
            ['comfortable', 'buff', 'Комфорт', 'Comfortable', 'Удобное снаряжение: расход энергии −10%.', 'Comfortable supplies: energy cost −10%.', ['energy_cost_percent' => -10], 2700, null],
            ['relaxed', 'buff', 'Спокойствие', 'Relaxed', 'После игр или ухода: прибавка настроения +20%.', 'After play or grooming: mood gain +20%.', ['mood_gain_percent' => 20], 1800, null],
            ['hungry', 'debuff', 'Голод', 'Hungry', 'Сытость ниже 20%: расход энергии +15%. Покорми до 20% или выше.', 'Satiety below 20%: energy cost +15%. Feed to at least 20%.', ['energy_cost_percent' => 15], null, 'satiety'],
            ['thirsty', 'debuff', 'Жажда', 'Thirsty', 'Вода ниже 20%: расход энергии +20%. Напои до 20% или выше.', 'Hydration below 20%: energy cost +20%. Offer water to at least 20%.', ['energy_cost_percent' => 20], null, 'hydration'],
            ['dirty', 'debuff', 'Дискомфорт', 'Dirty', 'Чистота ниже 20%: прибавка настроения −25%. Подними чистоту до 20%.', 'Cleanliness below 20%: mood gain −25%. Restore cleanliness to at least 20%.', ['mood_gain_percent' => -25], null, 'cleanliness'],
        ] as [$code, $kind, $ru, $en, $descriptionRu, $descriptionEn, $modifiers, $duration, $state]) {
            StatusEffect::query()->firstOrCreate(['code' => $code], [
                'kind' => $kind, 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $duration,
                'condition_state' => $state, 'condition_threshold' => $state === null ? null : 20,
            ]);
        }
    }
}
