<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalogue = [
            'first-dog' => [
                'rules' => ['metric' => 'first_dog', 'target' => 1],
                'name' => ['ru' => 'Теперь ты персонал', 'en' => 'You are the staff now'],
                'description' => ['ru' => 'Ты думал, что завёл собаку. Собака завела человека.', 'en' => 'You thought you adopted a dog. The dog adopted a human.'],
                'rule_description' => ['ru' => 'Заведи первую собаку.', 'en' => 'Adopt your first dog.'],
            ],
            'pack-leader' => [
                'rules' => ['metric' => 'active_dogs', 'target' => 3],
                'name' => ['ru' => 'Совет директоров', 'en' => 'Board of directors'],
                'description' => ['ru' => 'Три хвоста голосуют за еду. Ты снова в меньшинстве.', 'en' => 'Three tails vote for food. You are outnumbered again.'],
                'rule_description' => ['ru' => 'Держи одновременно трёх активных собак.', 'en' => 'Own three active dogs at the same time.'],
            ],
            'kennel-regular' => [
                'rules' => ['metric' => 'kennel_purchases', 'target' => 3],
                'name' => ['ru' => 'Я только посмотреть', 'en' => 'Just browsing'],
                'description' => ['ru' => 'Самая дорогая фраза на входе в питомник.', 'en' => 'The most expensive sentence at the kennel door.'],
                'rule_description' => ['ru' => 'Купи три собаки в питомнике. Бесплатная первая не считается.', 'en' => 'Buy three dogs from the kennel. The free starter dog does not count.'],
            ],
            'first-training' => [
                'rules' => ['metric' => 'trainings', 'target' => 1],
                'name' => ['ru' => 'Сидеть. Почти.', 'en' => 'Sit. Almost.'],
                'description' => ['ru' => 'Первый урок: собака поняла команду, но оставила право на апелляцию.', 'en' => 'First lesson: the dog understood the command and reserved the right to appeal.'],
                'rule_description' => ['ru' => 'Заверши одну тренировку или урок навыка.', 'en' => 'Complete one training session or skill lesson.'],
            ],
            'training-50' => [
                'rules' => ['metric' => 'trainings', 'target' => 50],
                'name' => ['ru' => 'Кто кого дрессирует?', 'en' => 'Who is training whom?'],
                'description' => ['ru' => 'Пятьдесят занятий. Ты уже подаёшь лакомство без команды.', 'en' => 'Fifty lessons. You now hand out treats without being told.'],
                'rule_description' => ['ru' => 'Заверши 50 тренировок или уроков навыков суммарно.', 'en' => 'Complete a total of 50 training sessions or skill lessons.'],
            ],
            'training-100' => [
                'rules' => ['metric' => 'trainings', 'target' => 100],
                'name' => ['ru' => 'Диссертация по гавкологии', 'en' => 'PhD in barkology'],
                'description' => ['ru' => 'Сто занятий. Диплом погрызен, квалификация подтверждена.', 'en' => 'One hundred lessons. The diploma is chewed, the qualification stands.'],
                'rule_description' => ['ru' => 'Заверши 100 тренировок или уроков навыков суммарно.', 'en' => 'Complete a total of 100 training sessions or skill lessons.'],
            ],
            'first-meal' => [
                'rules' => ['metric' => 'meals', 'target' => 1],
                'name' => ['ru' => 'Шеф, ещё порцию', 'en' => 'Chef, another serving'],
                'description' => ['ru' => 'Миска опустела быстрее, чем ты успел сказать «приятного аппетита».', 'en' => 'The bowl emptied before you could say bon appetit.'],
                'rule_description' => ['ru' => 'Заверши одно кормление собаки едой.', 'en' => 'Complete one food feeding.'],
            ],
            'bottomless-bowl' => [
                'rules' => ['metric' => 'daily_meals', 'target' => 15],
                'name' => ['ru' => 'Чёрная дыра с хвостом', 'en' => 'A black hole with a tail'],
                'description' => ['ru' => 'Еда исчезает. Хвост остаётся. Физики пока бессильны.', 'en' => 'Food disappears. The tail remains. Physicists have no explanation.'],
                'rule_description' => ['ru' => 'Заверши 15 кормлений одной и той же собаки за календарный день по Москве.', 'en' => 'Complete 15 food feedings for the same dog in one Moscow calendar day.'],
            ],
            'first-walk' => [
                'rules' => ['metric' => 'walks', 'target' => 1],
                'name' => ['ru' => 'Поводок судьбы', 'en' => 'Leash of destiny'],
                'description' => ['ru' => 'Вы вышли гулять. Маршрут согласован с каждым кустом.', 'en' => 'You went for a walk. Every bush approved the route.'],
                'rule_description' => ['ru' => 'Заверши одну прогулку.', 'en' => 'Complete one walk.'],
            ],
            'walk-50' => [
                'rules' => ['metric' => 'walks', 'target' => 50],
                'name' => ['ru' => 'Инспектор каждого куста', 'en' => 'Every bush inspector'],
                'description' => ['ru' => 'Район проверен. Отчёт хранится в носу.', 'en' => 'The neighbourhood is checked. The report is stored in a nose.'],
                'rule_description' => ['ru' => 'Заверши 50 прогулок суммарно.', 'en' => 'Complete a total of 50 walks.'],
            ],
            'first-wash' => [
                'rules' => ['metric' => 'washes', 'target' => 1],
                'name' => ['ru' => 'Чистый подозреваемый', 'en' => 'A squeaky clean suspect'],
                'description' => ['ru' => 'Следы грязи смыты. Намерение вернуться в лужу осталось.', 'en' => 'The mud evidence is gone. The intent to revisit the puddle remains.'],
                'rule_description' => ['ru' => 'Заверши одно мытьё собаки.', 'en' => 'Complete one dog wash.'],
            ],
            'first-skill' => [
                'rules' => ['metric' => 'skills', 'target' => 1],
                'name' => ['ru' => 'Лапа с квалификацией', 'en' => 'A qualified paw'],
                'description' => ['ru' => 'В резюме появился навык. «Хороший мальчик» уже было.', 'en' => 'A skill joined the CV. Good dog was already listed.'],
                'rule_description' => ['ru' => 'Заверши первый урок навыка у инструктора.', 'en' => 'Complete your first skill lesson with an instructor.'],
            ],
            'first-job' => [
                'rules' => ['metric' => 'jobs', 'target' => 1],
                'name' => ['ru' => 'Кормилец наоборот', 'en' => 'Reverse breadwinner'],
                'description' => ['ru' => 'Теперь собака зарабатывает. Ты по-прежнему моешь миску.', 'en' => 'The dog earns a living now. You still wash the bowl.'],
                'rule_description' => ['ru' => 'Заверши одну рабочую смену собаки.', 'en' => 'Complete one dog work shift.'],
            ],
            'vet-regular' => [
                'rules' => ['metric' => 'veterinary', 'target' => 3],
                'name' => ['ru' => 'Доктор, мы снова', 'en' => 'Doctor, us again'],
                'description' => ['ru' => 'Ветеринар узнаёт хвост раньше, чем тебя.', 'en' => 'The vet recognises the tail before recognising you.'],
                'rule_description' => ['ru' => 'Получи три ветеринарные услуги суммарно: осмотр, вакцинацию или лечение.', 'en' => 'Receive three veterinary services in total: checkups, vaccinations or treatment.'],
            ],
            'week-together' => [
                'rules' => ['metric' => 'active_days', 'target' => 7],
                'name' => ['ru' => 'Это уже отношения', 'en' => 'It is a relationship now'],
                'description' => ['ru' => 'Семь активных дней. Шерсть на одежде стала частью стиля.', 'en' => 'Seven active days. Fur on your clothes is now a fashion choice.'],
                'rule_description' => ['ru' => 'Завершай действия с собаками в семь разных дней. Дни не обязаны идти подряд.', 'en' => 'Complete dog actions on seven different days. They do not have to be consecutive.'],
            ],
        ];

        $sortOrder = 0;

        foreach ($catalogue as $code => $achievement) {
            Achievement::query()->updateOrCreate(['code' => $code], [
                ...$achievement,
                'image_path' => 'images/achievements/'.$code.'.svg',
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
