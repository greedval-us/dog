<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ShopDelivery;
use App\Models\ShopOffer;
use App\Modules\Inventory\Calculators\AmmunitionSupplyRules;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class AmmunitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CompetitionAmmunitionRules $rules, AmmunitionSupplyRules $supplies): void
    {
        DB::transaction(function () use ($rules, $supplies): void {
            $category = ItemCategory::query()->firstOrCreate(['code' => 'ammunition'], [
                'name' => ['ru' => 'Амуниция для выступлений', 'en' => 'Competition gear'],
                'sort_order' => 8, 'is_active' => true,
            ]);
            $at = CarbonImmutable::now(config('doglive.work_timezone'))->startOfSecond();
            foreach ($this->catalogue() as $index => [$code, $ru, $en, $slot, $disciplines, $phase, $sizes, $modifiers, $price, $specialized, $descriptionRu, $descriptionEn]) {
                $supply = $supplies->supply($specialized);
                $metadata = [
                    'slot' => $slot, 'disciplines' => $disciplines, 'phase' => $phase, 'sizes' => $sizes,
                    'modifiers' => $modifiers, 'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                ];
                if ($rules->metadata(['competition' => $metadata]) === null) {
                    throw new LogicException('Invalid ammunition catalogue entry: '.$code);
                }
                $item = Item::query()->firstOrCreate(['code' => $code], [
                    'item_category_id' => $category->id, 'name' => ['ru' => $ru, 'en' => $en],
                    'description' => $metadata['description'], 'quality' => 5, 'usage_limit' => $supply['usageLimit'],
                    'characteristics' => ['competition' => $metadata], 'bonuses' => [], 'is_active' => true,
                ]);
                if (! $item->wasRecentlyCreated && $disciplines === ['conformation']) {
                    Item::query()->whereKey($item->id)
                        ->whereJsonContains('characteristics->competition->disciplines', 'progeny')
                        ->update(['characteristics->competition->disciplines' => ['conformation']]);
                }
                $anchor = $specialized ? $at->startOfWeek(1) : $at->startOfDay();
                $schedule = $supplies->schedule($at->getTimestamp(), $anchor->getTimestamp(), $supply['intervalHours']);
                $period = CarbonImmutable::createFromTimestampUTC($schedule['current']);
                $offer = ShopOffer::query()->firstOrCreate(['item_id' => $item->id, 'currency' => 'coins'], [
                    'price' => $price, 'stock' => $supply['stockTarget'], 'is_active' => true, 'sort_order' => 100 + $index,
                    'restock_interval_hours' => $supply['intervalHours'], 'restock_target' => $supply['stockTarget'],
                    'purchase_limit' => $supply['purchaseLimit'], 'last_restock_at' => $period,
                    'next_restock_at' => CarbonImmutable::createFromTimestampUTC($schedule['next']),
                ]);
                if ($offer->wasRecentlyCreated) {
                    ShopDelivery::query()->create([
                        'shop_offer_id' => $offer->id, 'scheduled_at' => $period,
                        'stock_before' => 0, 'stock_after' => $offer->stock,
                    ]);
                }
            }
        }, attempts: 3);
    }

    /** @return list<array{string, string, string, string, list<string>, string, list<string>, array<string, float>, int, bool, string, string}> */
    private function catalogue(): array
    {
        $all = ['small', 'medium', 'large'];
        $show = ['conformation'];

        return [
            ['agility_light_collar', 'Лёгкий ошейник для разминки', 'Light warm-up collar', 'body', ['agility'], 'preparation', $all, ['pace' => 0.04, 'focus' => -0.02], 90, false, 'Свободное движение на разминке; требует спокойного управления.', 'Free warm-up movement with less guidance.'],
            ['agility_contact_collar', 'Контактный ошейник', 'Contact warm-up collar', 'body', ['agility'], 'preparation', $all, ['focus' => 0.05, 'pace' => -0.03], 95, false, 'Помогает сосредоточиться перед стартом, замедляя темп разминки.', 'Supports pre-start focus at a calmer warm-up pace.'],
            ['agility_short_lead', 'Короткий поводок для разминки', 'Short warm-up lead', 'line', ['agility'], 'preparation', $all, ['precision' => 0.05, 'stamina' => -0.02], 100, false, 'Точная отработка подхода к старту вместо свободного разгона.', 'Rehearse a precise approach to the start with less free movement.'],
            ['agility_reward_pouch', 'Поясная сумка для поощрения', 'Warm-up reward pouch', 'handler', ['agility'], 'preparation', $all, ['focus' => 0.04, 'precision' => -0.02], 100, false, 'Поддерживает мотивацию на разминке, но требует аккуратных команд.', 'Supports warm-up motivation while requiring careful cues.'],
            ['agility_soft_grip', 'Поводок с мягкой петлёй', 'Soft-grip warm-up lead', 'line', ['agility'], 'preparation', $all, ['stamina' => 0.04, 'pace' => -0.02], 150, true, 'Спокойная экономная разминка перед длинной трассой.', 'A relaxed warm-up conserves energy for a long course.'],
            ['agility_precise_pouch', 'Сумка для точного поощрения', 'Precision reward pouch', 'handler', ['agility'], 'preparation', $all, ['precision' => 0.07, 'focus' => -0.04], 165, true, 'Удобна для точных коротких повторений перед стартом.', 'Useful for precise short rehearsals before the start.'],
            ['agility_cooling_wrap', 'Охлаждающая попона для разминки', 'Warm-up cooling wrap', 'body', ['agility'], 'preparation', ['medium', 'large'], ['stamina' => 0.08, 'pace' => -0.04], 180, true, 'Помогает сберечь силы; снимается до выхода на трассу.', 'Conserves energy and is removed before entering the course.'],
            ['agility_settle_mat', 'Подстилка для ожидания старта', 'Pre-start settling mat', 'preparation', ['agility'], 'preparation', $all, ['focus' => 0.08, 'pace' => -0.04], 175, true, 'Спокойное ожидание уменьшает отвлечения и стартовый азарт.', 'Calm waiting reduces distraction and initial excitement.'],
            ['nosework_y_harness', 'Y-шлейка для поиска', 'Y-shaped scent harness', 'body', ['nosework'], 'performance', $all, ['stamina' => 0.05, 'precision' => -0.03], 110, false, 'Даёт свободу обследования участка, сохраняя силы.', 'Supports energy-saving exploration of the search area.'],
            ['nosework_medium_line', 'Поисковый поводок 3 м', 'Three-metre search line', 'line', ['nosework'], 'performance', $all, ['precision' => 0.05, 'pace' => -0.03], 105, false, 'Помогает последовательно обследовать небольшой участок.', 'Supports systematic coverage of a small search area.'],
            ['nosework_handler_belt', 'Пояс для поискового поводка', 'Search-line handler belt', 'handler', ['nosework'], 'performance', $all, ['stamina' => 0.04, 'focus' => -0.02], 110, false, 'Ровное сопровождение собаки с меньшими лишними движениями.', 'Supports steady handling with fewer unnecessary movements.'],
            ['nosework_light_line', 'Лёгкая поисковая стропа', 'Light scent line', 'line', ['nosework'], 'performance', ['small', 'medium'], ['pace' => 0.07, 'precision' => -0.04], 170, true, 'Быстрое обследование открытого участка требует точной работы.', 'Fast coverage of an open area requires precise handling.'],
            ['nosework_steady_harness', 'Шлейка для спокойного поиска', 'Steady-search harness', 'body', ['nosework'], 'performance', $all, ['focus' => 0.07, 'pace' => -0.04], 175, true, 'Поддерживает спокойный ритм среди отвлекающих запахов.', 'Supports a calm rhythm among distracting scents.'],
            ['nosework_long_line', 'Поисковая стропа 5 м', 'Five-metre search line', 'line', ['nosework'], 'performance', ['medium', 'large'], ['pace' => 0.08, 'focus' => -0.05], 180, true, 'Больше свободы охвата, но сложнее удерживать внимание.', 'Wider coverage makes maintaining focus more demanding.'],
            ['nosework_marker_pouch', 'Сумка для поискового поощрения', 'Scent reward pouch', 'handler', ['nosework'], 'preparation', $all, ['precision' => 0.08, 'stamina' => -0.04], 160, true, 'Предстартовые упражнения усиливают точность обозначения.', 'Pre-start exercises support a precise indication.'],
            ['nosework_waiting_mat', 'Коврик для ожидания поиска', 'Scent-search waiting mat', 'preparation', ['nosework'], 'preparation', $all, ['focus' => 0.09, 'pace' => -0.05], 185, true, 'Уменьшает предстартовое возбуждение и поспешные решения.', 'Reduces pre-start excitement and rushed decisions.'],
            ['canicross_y_harness', 'Беговая Y-шлейка', 'Running Y-harness', 'body', ['canicross'], 'performance', ['medium', 'large'], ['stamina' => 0.05, 'pace' => -0.03], 120, false, 'Распределяет тягу для ровного экономного бега.', 'Distributes pull for steady economical running.'],
            ['canicross_elastic_line', 'Беговая эластичная потяжка', 'Elastic running line', 'line', ['canicross'], 'performance', $all, ['precision' => 0.04, 'pace' => -0.02], 115, false, 'Смягчает рывки и помогает удерживать линию движения.', 'Softens sudden pulls and supports a steady running line.'],
            ['canicross_running_belt', 'Беговой пояс', 'Running belt', 'handler', ['canicross'], 'performance', $all, ['stamina' => 0.04, 'precision' => -0.02], 120, false, 'Удобное распределение тяги на длинном отрезке.', 'Comfortably distributes pull on a long section.'],
            ['canicross_compact_harness', 'Компактная беговая шлейка', 'Compact running harness', 'body', ['canicross'], 'performance', ['small', 'medium'], ['pace' => 0.08, 'stamina' => -0.05], 200, true, 'Для короткого быстрого отрезка; расход сил выше.', 'Supports a short fast section at greater energy cost.'],
            ['canicross_distance_harness', 'Шлейка для длинной дистанции', 'Distance running harness', 'body', ['canicross'], 'performance', ['medium', 'large'], ['stamina' => 0.09, 'pace' => -0.05], 225, true, 'Сберегает силы при ровном темпе на длинной дистанции.', 'Conserves energy at an even long-distance pace.'],
            ['canicross_firm_line', 'Упругая короткая потяжка', 'Firm short running line', 'line', ['canicross'], 'performance', $all, ['precision' => 0.08, 'stamina' => -0.04], 195, true, 'Точный контроль поворотов требует больше работы.', 'Precise control through turns requires more effort.'],
            ['canicross_soft_line', 'Мягкая амортизирующая потяжка', 'Soft shock-absorbing line', 'line', ['canicross'], 'performance', $all, ['stamina' => 0.08, 'precision' => -0.05], 195, true, 'Сглаживает смену тяги ценой менее резкого управления.', 'Smooths changes in pull with less immediate steering.'],
            ['canicross_support_belt', 'Пояс с широкой опорой', 'Wide-support running belt', 'handler', ['canicross'], 'performance', $all, ['focus' => 0.08, 'pace' => -0.04], 210, true, 'Помогает поддерживать согласованный ритм команды.', 'Helps maintain a coordinated team rhythm.'],
            ['canicross_ventilated_harness', 'Вентилируемая беговая шлейка', 'Ventilated running harness', 'body', ['canicross'], 'performance', $all, ['stamina' => 0.06, 'precision' => -0.02, 'pace' => 0.02], 230, true, 'Лёгкая конструкция для экономного ровного движения.', 'A light construction supports economical steady movement.'],
            ['canicross_cooling_coat', 'Предстартовая охлаждающая попона', 'Pre-start cooling coat', 'preparation', ['canicross'], 'preparation', $all, ['stamina' => 0.09, 'focus' => -0.04], 205, true, 'Подготовка к дистанции с упором на сохранение сил.', 'Prepares for the distance with an emphasis on energy conservation.'],
            ['show_nylon_lead', 'Нейлоновая ринговка', 'Nylon show lead', 'line', $show, 'performance', $all, ['precision' => 0.04, 'pace' => -0.02], 95, false, 'Помогает выдержать траекторию и правильную стойку.', 'Supports a consistent gait path and stance.'],
            ['show_soft_collar', 'Мягкий выставочный ошейник', 'Soft show collar', 'body', $show, 'preparation', $all, ['focus' => 0.04, 'precision' => -0.02], 90, false, 'Спокойная подготовка к осмотру и смене позиций.', 'Supports calm preparation for examination and positioning.'],
            ['show_grooming_cloth', 'Салфетка для подготовки шерсти', 'Coat preparation cloth', 'preparation', $show, 'preparation', $all, ['precision' => 0.04, 'stamina' => -0.02], 95, false, 'Короткий уход помогает подготовить показ собаки.', 'Brief grooming supports preparation for the presentation.'],
            ['show_fine_lead', 'Тонкая выставочная ринговка', 'Fine show lead', 'line', $show, 'performance', ['small', 'medium'], ['pace' => 0.07, 'focus' => -0.04], 160, true, 'Даёт свободу движения, требуя спокойствия на осмотре.', 'Allows free movement while requiring calm examination behaviour.'],
            ['show_wide_lead', 'Ринговка с широкой поддержкой', 'Wide-support show lead', 'line', $show, 'performance', ['medium', 'large'], ['focus' => 0.08, 'pace' => -0.05], 175, true, 'Поддерживает спокойствие в ринге при более сдержанном движении.', 'Supports ring composure at a more restrained gait.'],
            ['show_precise_lead', 'Ринговка для точного показа', 'Precision presentation lead', 'line', $show, 'performance', $all, ['precision' => 0.09, 'stamina' => -0.05], 190, true, 'Точные переходы между стойкой, осмотром и движением.', 'Supports precise transitions between stance, examination and gait.'],
            ['show_handler_pouch', 'Выставочная сумка хендлера', 'Show handler pouch', 'handler', $show, 'preparation', $all, ['focus' => 0.08, 'precision' => -0.04], 165, true, 'Подготовка внимания перед выходом к эксперту.', 'Prepares attention before presentation to the judge.'],
            ['show_coat_brush', 'Мягкая щётка для ринга', 'Soft ring preparation brush', 'preparation', $show, 'preparation', $all, ['precision' => 0.07, 'pace' => -0.03], 155, true, 'Аккуратная подготовка показа без изменения породных качеств.', 'Careful presentation preparation preserves breed characteristics.'],
            ['show_settling_mat', 'Выставочная подстилка ожидания', 'Show waiting mat', 'preparation', $show, 'preparation', $all, ['focus' => 0.09, 'pace' => -0.05], 180, true, 'Снижает суету при ожидании осмотра в незнакомой обстановке.', 'Reduces restlessness while awaiting examination in unfamiliar surroundings.'],
            ['show_warmup_lead', 'Поводок для репетиции показа', 'Presentation rehearsal lead', 'line', $show, 'preparation', $all, ['pace' => 0.08, 'precision' => -0.04], 175, true, 'Репетиция свободного движения перед выходом в ринг.', 'Rehearses free movement before entering the ring.'],
        ];
    }
}
