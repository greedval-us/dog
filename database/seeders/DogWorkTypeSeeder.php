<?php

namespace Database\Seeders;

use App\Models\DogWorkType;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class DogWorkTypeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SkillSeeder::class);
        $skills = Skill::query()->pluck('id', 'code');
        $jobs = [
            ['park_lost_items', 'search', 1, 100, 0, 20, 8, 40, 'Поиск вещей в парке', 'Lost items in the park', 'Помоги посетителям парка найти потерянные вещи.', 'Help park visitors recover lost belongings.'],
            ['search_volunteer', 'search', 2, 180, 0, 45, 12, 20, 'Волонтёр поискового отряда', 'Search team volunteer', 'Присоединись к поисковому отряду с обученной собакой.', 'Join a search team with your trained dog.'],
            ['forest_search', 'search', 3, 280, 0, 90, 18, 12, 'Поиск в лесу', 'Forest search', 'Обследуй лесные маршруты вместе с опытной командой.', 'Inspect forest trails alongside an experienced team.'],
            ['mountain_search', 'search', 5, 480, 2, 120, 24, 5, 'Горная поисковая группа', 'Mountain search team', 'Помоги спасателям обследовать сложный горный маршрут.', 'Help rescuers inspect a demanding mountain trail.'],
            ['warehouse_guard', 'guardian', 1, 120, 0, 30, 10, 30, 'Охрана склада', 'Warehouse guard', 'Дежурь с собакой у складских ворот.', 'Keep watch with your dog at the warehouse gates.'],
            ['festival_guard', 'guardian', 2, 210, 0, 60, 14, 20, 'Порядок на фестивале', 'Festival patrol', 'Помоги организаторам следить за порядком на площадке.', 'Help organisers keep the festival grounds in order.'],
            ['night_patrol', 'guardian', 4, 360, 1, 90, 20, 8, 'Патруль заповедника', 'Reserve patrol', 'Сопровождай инспектора на охраняемой территории.', 'Accompany a ranger around a protected area.'],
            ['scent_sorting', 'keen_nose', 1, 110, 0, 25, 8, 35, 'Проверка образцов', 'Scent sample checks', 'Помоги инструктору рассортировать учебные образцы запахов.', 'Help an instructor sort training scent samples.'],
            ['truffle_search', 'keen_nose', 3, 260, 0, 60, 16, 15, 'Поиск трюфелей', 'Truffle search', 'Найди ароматные грибы на участке фермерского хозяйства.', 'Find fragrant fungi on a farm plot.'],
            ['scent_expert', 'keen_nose', 5, 440, 2, 100, 22, 6, 'Эксперт по запахам', 'Scent expert', 'Покажи точность собаки в сложном задании кинологического центра.', 'Demonstrate your dog’s precision at a canine centre.'],
            ['wildlife_survey', 'hunter', 1, 130, 0, 30, 10, 25, 'Учёт лесных животных', 'Wildlife survey', 'Помоги егерю обнаружить следы животных для учёта.', 'Help a ranger locate animal signs for a wildlife survey.'],
            ['ranger_assistant', 'hunter', 3, 270, 0, 75, 18, 12, 'Помощник егеря', 'Ranger assistant', 'Сопровождай егеря и помогай собаке находить звериные тропы.', 'Accompany a ranger and let your dog locate wildlife paths.'],
            ['wildlife_monitor', 'hunter', 4, 350, 1, 90, 20, 8, 'Наблюдение в заповеднике', 'Wildlife monitoring', 'Помоги исследователям найти места обитания редких животных.', 'Help researchers locate rare wildlife habitats.'],
            ['rescue_drill', 'rescuer', 1, 140, 0, 35, 12, 25, 'Учения спасателей', 'Rescue drill', 'Помоги команде отработать спасение людей с собакой.', 'Help a team practise rescue procedures with a dog.'],
            ['shore_rescue', 'rescuer', 3, 300, 0, 75, 18, 12, 'Дежурство на берегу', 'Shore rescue duty', 'Сопровождай спасателей на прибрежном участке.', 'Accompany lifeguards around the shore.'],
            ['rescue_specialist', 'rescuer', 5, 500, 2, 120, 26, 5, 'Помощник спасательной службы', 'Rescue service specialist', 'Прими участие в сложных учениях профессиональной команды.', 'Take part in a professional team’s advanced rescue drill.'],
            ['equipment_retrieval', 'retriever', 1, 100, 0, 20, 8, 40, 'Сбор спортивного инвентаря', 'Sports equipment retrieval', 'Помоги тренеру собрать инвентарь после занятий.', 'Help a trainer gather equipment after a session.'],
            ['canine_demo', 'retriever', 4, 320, 1, 60, 18, 10, 'Показательная работа апорта', 'Retrieval demonstration', 'Покажи гостям центра слаженную работу с собакой.', 'Demonstrate coordinated retrieval work to centre visitors.'],
        ];

        foreach ($jobs as [$code, $skill, $level, $coins, $gems, $minutes, $energy, $limit, $ru, $en, $descriptionRu, $descriptionEn]) {
            DogWorkType::query()->firstOrCreate(['code' => $code], [
                'name' => ['ru' => $ru, 'en' => $en], 'description' => ['ru' => $descriptionRu, 'en' => $descriptionEn],
                'required_skill_id' => $skills[$skill], 'required_skill_level' => $level, 'coins_reward' => $coins,
                'gems_reward' => $gems, 'duration_seconds' => $minutes * 60, 'energy_cost' => $energy, 'daily_limit' => $limit,
            ]);
        }
    }
}
