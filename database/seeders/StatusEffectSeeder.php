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
                'Реакция на некачественную еду: расход энергии +20%, прибавка настроения −20%. Вода сокращает эффект на 5 минут, короткий сон — на 10, долгий — на 30.',
                'A reaction to low-quality food: energy cost +20%, mood gain −20%. Water removes 5 minutes, a nap 10, long sleep 30.'],
            ['minor_injury', 'Лёгкая травма', 'Minor injury', 1200, ['energy_cost_percent' => 25],
                'Неудача с некачественным снаряжением: расход энергии +25%. Короткий сон сокращает эффект на 5 минут, долгий — на 20.',
                'An accident with low-quality equipment: energy cost +25%. A nap removes 5 minutes, long sleep 20.'],
        ] as [$code, $ru, $en, $duration, $modifiers, $descriptionRu, $descriptionEn]) {
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => 'debuff', 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $duration,
                'recovery_actions' => $code === 'poisoning' ? ['water' => 300, 'nap' => 600, 'sleep' => 1800] : ['nap' => 300, 'sleep' => 1200],
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
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => $kind, 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $duration,
                'condition_state' => $state, 'condition_threshold' => $state === null ? null : 20,
                'condition_group' => $state, 'condition_priority' => $state === null ? 0 : 1,
            ]);
        }

        $this->seedTimedEffects();
        $this->seedConditionalEffects();
    }

    private function seedTimedEffects(): void
    {
        foreach ([
            ['refreshed', 'Свежесть', 'Refreshed', 900, ['hydration_loss_percent' => -15], ['water'], [],
                'После воды: потери воды во время действий −15%.', 'After drinking: hydration spent on activities −15%.'],
            ['rested', 'Передышка', 'Rested', 1200, ['energy_cost_percent' => -10], ['nap'], [],
                'После короткого сна: расход энергии −10%.', 'After a nap: energy cost −10%.'],
            ['deep_rest', 'Крепкий сон', 'Deep rest', 2700, ['energy_cost_percent' => -15, 'energy_gain_percent' => 10], ['sleep'], [],
                'После долгого сна: расход энергии −15%, восстановление энергии действиями +10%.', 'After long sleep: energy cost −15%, energy gained from activities +10%.'],
            ['companionship', 'Время вместе', 'Together time', 1500, ['bond_gain_percent' => 20], ['attention'], [],
                'После совместной игры: прибавка привязанности +20%.', 'After playing together: bond gain +20%.'],
            ['explorer', 'Новые впечатления', 'New experiences', 1200, ['mood_gain_percent' => 15, 'bond_gain_percent' => 10], ['walk'], [],
                'После прогулки: прибавка настроения +15%, привязанности +10%.', 'After a walk: mood gain +15%, bond gain +10%.'],
            ['limber', 'Разминка', 'Warmed up', 900, ['satiety_loss_percent' => -10, 'hydration_loss_percent' => -10], ['home'], [],
                'После движения дома: потери сытости и воды во время действий −10%.', 'After moving at home: satiety and hydration spent on activities −10%.'],
            ['playful', 'Игровой настрой', 'Playful', 1200, ['bond_gain_percent' => 15, 'energy_cost_percent' => -5], ['toy'], [],
                'После игры с игрушкой: прибавка привязанности +15%, расход энергии −5%.', 'After toy play: bond gain +15%, energy cost −5%.'],
            ['groomed', 'Ухоженная шерсть', 'Groomed coat', 1800, ['cleanliness_loss_percent' => -20], ['wash', 'care'], [],
                'После мытья: потери чистоты во время действий −20%.', 'After washing: cleanliness lost during activities −20%.'],
            ['eager', 'Вкусная награда', 'Tasty reward', 1200, ['bond_gain_percent' => 15], [], [],
                'После мясного лакомства: прибавка привязанности +15%.', 'After a beef treat: bond gain +15%.'],
            ['rain_protection', 'Защита от брызг', 'Splash protection', 1800, ['cleanliness_loss_percent' => -25], [], [],
                'После прогулки в дождевике: потери чистоты во время действий −25%.', 'After a raincoat walk: cleanliness lost during activities −25%.'],
            ['focused', 'Сосредоточенность', 'Focused', 1200, ['bond_gain_percent' => 25, 'energy_cost_percent' => 5], [], [],
                'Занятия на внимание: прибавка привязанности +25%, но расход энергии +5%.', 'Focus practice: bond gain +25%, but energy cost +5%.'],
            ['skin_irritation', 'Раздражение кожи', 'Skin irritation', 1200, ['mood_gain_percent' => -15], [], ['wash' => 600, 'sleep' => 1200],
                'Редкая реакция на средства ухода низкого качества: прибавка настроения −15%. Мытьё сокращает эффект на 10 минут, долгий сон — на 20.', 'A rare reaction to low-quality care supplies: mood gain −15%. Rinsing removes 10 minutes; long sleep removes 20.'],
            ['overstimulated', 'Перевозбуждение', 'Overstimulated', 900, ['hydration_loss_percent' => 15, 'bond_gain_percent' => -10], [], ['attention' => 300, 'nap' => 600, 'sleep' => 900],
                'Редкая реакция на игрушки низкого качества: потери воды +15%, прибавка привязанности −10%. Совместная игра сокращает эффект на 5 минут, короткий сон — на 10.', 'A rare reaction to low-quality toys: hydration loss +15%, bond gain −10%. Together time removes 5 minutes; a nap removes 10.'],
        ] as [$code, $ru, $en, $duration, $modifiers, $variants, $recovery, $descriptionRu, $descriptionEn]) {
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => $recovery === [] ? 'buff' : 'debuff',
                'name' => ['ru' => $ru, 'en' => $en], 'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => $duration,
                'condition_state' => null, 'conditions' => null,
                'care_variants' => $variants, 'recovery_actions' => $recovery,
            ]);
        }
    }

    private function seedConditionalEffects(): void
    {
        foreach ([
            ['famished', 'debuff', 'Сильный голод', 'Famished', 'satiety', 'lt', 5, 2, ['energy_cost_percent' => 25], [],
                'Сытость ниже 5%: расход энергии +25%. Заменяет обычный голод. Еда помогает.', 'Satiety below 5%: energy cost +25%. Replaces ordinary hunger. Feed your dog.'],
            ['dehydrated', 'debuff', 'Сильная жажда', 'Very thirsty', 'hydration', 'lt', 5, 2, ['energy_cost_percent' => 30], [],
                'Вода ниже 5%: расход энергии +30%. Заменяет обычную жажду. Предложи воду.', 'Hydration below 5%: energy cost +30%. Replaces ordinary thirst. Offer water.'],
            ['filthy', 'debuff', 'Грязная шерсть', 'Muddy coat', 'cleanliness', 'lt', 5, 2, ['mood_gain_percent' => -35], [],
                'Чистота ниже 5%: прибавка настроения −35%. Заменяет дискомфорт. Поможет мытьё.', 'Cleanliness below 5%: mood gain −35%. Replaces discomfort. Wash your dog.'],
            ['tired', 'debuff', 'Усталость', 'Tired', 'energy', 'lt', 20, 1, ['energy_cost_percent' => 15], [],
                'Энергия ниже 20%: расход энергии +15%. Сон остаётся бесплатным.', 'Energy below 20%: energy cost +15%. Sleep still costs no energy.'],
            ['exhausted', 'debuff', 'Изнеможение', 'Exhausted', 'energy', 'lt', 5, 2, ['energy_cost_percent' => 25], [],
                'Энергия ниже 5%: расход энергии +25%. Заменяет усталость. Дай собаке поспать.', 'Energy below 5%: energy cost +25%. Replaces tiredness. Let your dog sleep.'],
            ['low_spirits', 'debuff', 'Хандра', 'Low spirits', 'mood', 'lt', 20, 1, ['bond_gain_percent' => -15], [],
                'Настроение ниже 20%: прибавка привязанности −15%. Игры и прогулки помогут вернуть настроение.', 'Mood below 20%: bond gain −15%. Play and walks help restore mood.'],
            ['unwell', 'debuff', 'Недомогание', 'Unwell', 'health', 'lt', 30, 1, ['energy_cost_percent' => 10], [],
                'Здоровье ниже 30%: расход энергии +10%. Поддерживай еду и воду; сон понемногу восстанавливает здоровье.', 'Health below 30%: energy cost +10%. Keep food and water available; sleep slowly restores health.'],
            ['well_fed', 'buff', 'Сбалансированный рацион', 'Well nourished', 'satiety', 'gte', 80, 1, ['satiety_loss_percent' => -10], ['hydration' => 60, 'health' => 50],
                'Сытость от 80%, вода от 60%, здоровье от 50%: потери сытости во время действий −10%.', 'Satiety at least 80%, hydration 60%, health 50%: satiety spent on activities −10%.'],
            ['well_hydrated', 'buff', 'Водный баланс', 'Well hydrated', 'hydration', 'gte', 80, 1, ['hydration_loss_percent' => -10], ['satiety' => 50],
                'Вода от 80% и сытость от 50%: потери воды во время действий −10%.', 'Hydration at least 80% and satiety 50%: hydration spent on activities −10%.'],
            ['good_spirits', 'buff', 'Хорошее настроение', 'Good spirits', 'mood', 'gte', 80, 1, ['bond_gain_percent' => 10], ['energy' => 40],
                'Настроение от 80% и энергия от 40%: прибавка привязанности +10%.', 'Mood at least 80% and energy 40%: bond gain +10%.'],
            ['trusting', 'buff', 'Доверие', 'Trusting', 'bond', 'gte', 60, 1, ['mood_gain_percent' => 10], ['mood' => 60],
                'Привязанность и настроение от 60%: прибавка настроения +10%.', 'Bond and mood at least 60%: mood gain +10%.'],
            ['thriving', 'buff', 'Полная гармония', 'Thriving', 'health', 'gte', 80, 1, ['energy_cost_percent' => -5, 'health_gain_percent' => 15], ['satiety' => 80, 'hydration' => 80, 'mood' => 80, 'cleanliness' => 80, 'energy' => 80, 'bond' => 50],
                'Все потребности от 80%, привязанность от 50%: расход энергии −5%, восстановление здоровья действиями +15%.', 'All needs at least 80%, bond at least 50%: energy cost −5%, health gained from activities +15%.'],
        ] as [$code, $kind, $ru, $en, $state, $operator, $threshold, $priority, $modifiers, $requirements, $descriptionRu, $descriptionEn]) {
            $conditions = [];
            foreach ($requirements as $requiredState => $minimum) {
                $conditions[] = ['state' => $requiredState, 'operator' => 'gte', 'threshold' => $minimum];
            }
            StatusEffect::query()->updateOrCreate(['code' => $code], [
                'kind' => $kind, 'name' => ['ru' => $ru, 'en' => $en], 'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'modifiers' => $modifiers, 'duration_seconds' => null,
                'condition_state' => $state, 'condition_operator' => $operator, 'condition_threshold' => $threshold,
                'conditions' => $conditions, 'condition_group' => $state, 'condition_priority' => $priority,
            ]);
        }
    }
}
