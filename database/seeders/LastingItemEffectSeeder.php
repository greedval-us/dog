<?php

namespace Database\Seeders;

use App\Models\StatusEffect;
use Illuminate\Database\Seeder;

class LastingItemEffectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['slow_digest', 'Долгая сытость', 'Lasting fullness', 12, ['satiety_decay_percent' => -25],
                'Сытость со временем падает на 25% медленнее.', 'Satiety decays 25% more slowly.'],
            ['food_motivation', 'Предвкушение награды', 'Reward anticipation', 6, ['mood_decay_percent' => -20, 'bond_decay_percent' => -15],
                'Настроение падает на 20% медленнее, привязанность — на 15%.', 'Mood decays 20% more slowly; bond decays 15% more slowly.'],
            ['balanced_fuel', 'Питательный запас', 'Nutrient reserve', 24, ['satiety_decay_percent' => -15, 'stats_decay_percent' => -15],
                'Сытость и все характеристики со временем падают на 15% медленнее.', 'Satiety and all stats decay 15% more slowly.'],
            ['familiar_scent', 'Знакомый запах', 'Familiar scent', 12, ['mood_decay_percent' => -15],
                'Привычный ошейник успокаивает: настроение падает на 15% медленнее.', 'A familiar collar is reassuring: mood decays 15% more slowly.'],
            ['easy_movement', 'Свобода движения', 'Easy movement', 6, ['hydration_decay_percent' => -15, 'energy_cost_percent' => -5],
                'Вода расходуется со временем на 15% медленнее, действия требуют на 5% меньше энергии.', 'Hydration decays 15% more slowly; activities cost 5% less energy.'],
            ['steady_rhythm', 'Ровный шаг', 'Steady rhythm', 12, ['endurance_decay_percent' => -30, 'speed_decay_percent' => -20],
                'Выносливость падает на 30% медленнее, скорость — на 20%.', 'Endurance decays 30% more slowly; speed decays 20% more slowly.'],
            ['adventure_memory', 'Радость открытий', 'Happy discoveries', 6, ['mood_decay_percent' => -30],
                'Впечатления от прогулки сохраняются: настроение падает на 30% медленнее.', 'Memories of exploring last: mood decays 30% more slowly.'],
            ['close_companion', 'Крепкая связь', 'Close companions', 12, ['bond_decay_percent' => -30],
                'Совместные занятия укрепляют доверие: привязанность падает на 30% медленнее.', 'Shared activities build trust: bond decays 30% more slowly.'],
            ['silky_coat', 'Гладкая шерсть', 'Silky coat', 12, ['cleanliness_decay_percent' => -25],
                'Расчёсанная шерсть меньше пачкается: чистота падает на 25% медленнее.', 'Brushed fur stays tidy: cleanliness decays 25% more slowly.'],
            ['clean_barrier', 'Защита шерсти', 'Coat protection', 24, ['cleanliness_decay_percent' => -30],
                'Чистота со временем падает на 30% медленнее.', 'Cleanliness decays 30% more slowly.'],
            ['spa_calm', 'Домашний спа', 'Home spa', 12, ['mood_decay_percent' => -20, 'cleanliness_decay_percent' => -15],
                'Настроение падает на 20% медленнее, чистота — на 15%.', 'Mood decays 20% more slowly; cleanliness decays 15% more slowly.'],
            ['play_memory', 'Навык погони', 'Chase practice', 6, ['speed_decay_percent' => -25, 'agility_decay_percent' => -25],
                'Скорость и ловкость падают на 25% медленнее.', 'Speed and agility decay 25% more slowly.'],
            ['mental_map', 'Закреплённые команды', 'Remembered commands', 24, ['obedience_decay_percent' => -30, 'intelligence_decay_percent' => -30],
                'Послушание и интеллект падают на 30% медленнее.', 'Obedience and intelligence decay 30% more slowly.'],
            ['cozy', 'Уют и тепло', 'Warm and cozy', 12, ['mood_decay_percent' => -25, 'satiety_decay_percent' => -10],
                'Настроение падает на 25% медленнее, сытость — на 10%.', 'Mood decays 25% more slowly; satiety decays 10% more slowly.'],
            ['muscle_memory', 'Мышечная память', 'Muscle memory', 12, ['strength_decay_percent' => -30, 'endurance_decay_percent' => -30],
                'Сила и выносливость падают на 30% медленнее.', 'Strength and endurance decay 30% more slowly.'],
            ['coordination', 'Чувство равновесия', 'Sense of balance', 12, ['agility_decay_percent' => -30, 'speed_decay_percent' => -30],
                'Ловкость и скорость падают на 30% медленнее.', 'Agility and speed decay 30% more slowly.'],
        ] as [$code, $ru, $en, $hours, $modifiers, $descriptionRu, $descriptionEn]) {
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => 'buff', 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $hours * 3600,
                'condition_state' => null, 'conditions' => null, 'care_variants' => [], 'recovery_actions' => [],
            ]);
        }

        foreach ([
            ['chafing', 'Натёртость', 'Chafing', ['mood_decay_percent' => 20, 'energy_cost_percent' => 10], ['wash' => 1800, 'sleep' => 21600],
                'Неудобное снаряжение: настроение падает на 20% быстрее, расход энергии +10%. Мытьё убирает 30 минут, долгий сон снимает эффект.', 'Uncomfortable equipment: mood decays 20% faster, energy cost +10%. Washing removes 30 minutes; long sleep clears the effect.'],
            ['stuffy', 'Духота', 'Too warm', ['hydration_decay_percent' => 25], ['water' => 3600, 'sleep' => 21600],
                'Плотная одежда: вода со временем расходуется на 25% быстрее. Питьё убирает час, долгий сон снимает эффект.', 'Heavy clothing: hydration decays 25% faster. Drinking removes an hour; long sleep clears the effect.'],
            ['muscle_soreness', 'Уставшие мышцы', 'Sore muscles', ['stats_decay_percent' => 20, 'energy_cost_percent' => 15], ['nap' => 3600, 'sleep' => 21600],
                'Неудачная нагрузка: характеристики падают на 20% быстрее, расход энергии +15%. Короткий сон убирает час, долгий снимает эффект.', 'Awkward exercise: stats decay 20% faster, energy cost +15%. A nap removes an hour; long sleep clears the effect.'],
        ] as [$code, $ru, $en, $modifiers, $recovery, $descriptionRu, $descriptionEn]) {
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => 'debuff', 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => 21600,
                'condition_state' => null, 'conditions' => null, 'care_variants' => [], 'recovery_actions' => $recovery,
            ]);
        }
    }
}
