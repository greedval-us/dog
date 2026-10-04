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
            'first-competition' => [
                'rules' => ['metric' => 'competition_starts', 'target' => 1],
                'name' => ['ru' => 'На старт, внимание, хвост!', 'en' => 'Ready, steady, tail!'],
                'description' => ['ru' => 'Номер участника получен. Собака считает его новым аксессуаром.', 'en' => 'The entry number is yours. Your dog considers it a new accessory.'],
                'rule_description' => ['ru' => 'Заверши первое соревнование: аджилити, ноузворк или каникросс.', 'en' => 'Complete your first agility, nosework or canicross competition.'],
            ],
            'competition-20' => [
                'rules' => ['metric' => 'competition_starts', 'target' => 20],
                'name' => ['ru' => 'Стартовый номер прописался', 'en' => 'A permanent entry number'],
                'description' => ['ru' => 'Двадцать стартов. Разминка стала семейной традицией.', 'en' => 'Twenty starts. Warming up is now a family tradition.'],
                'rule_description' => ['ru' => 'Заверши 20 соревнований суммарно.', 'en' => 'Complete 20 competitions in total.'],
            ],
            'first-competition-podium' => [
                'rules' => ['metric' => 'competition_podiums', 'target' => 1],
                'name' => ['ru' => 'Три ступеньки до лакомства', 'en' => 'Three steps to a treat'],
                'description' => ['ru' => 'На пьедестале удобнее просить награду.', 'en' => 'A podium is the perfect place to ask for a reward.'],
                'rule_description' => ['ru' => 'Займи одно из первых трёх мест в соревновании без дисквалификации.', 'en' => 'Finish in the top three of a competition without elimination.'],
            ],
            'competition-winner' => [
                'rules' => ['metric' => 'competition_wins', 'target' => 1],
                'name' => ['ru' => 'Первый среди хвостов', 'en' => 'First among tails'],
                'description' => ['ru' => 'Ты радуешься победе. Собака ждёт съедобную награду.', 'en' => 'You celebrate the win. Your dog expects an edible reward.'],
                'rule_description' => ['ru' => 'Победи в первом соревновании.', 'en' => 'Win your first competition.'],
            ],
            'agility-winner' => [
                'rules' => ['metric' => 'agility_wins', 'target' => 1],
                'name' => ['ru' => 'Препятствия? Какие препятствия?', 'en' => 'Obstacles? What obstacles?'],
                'description' => ['ru' => 'Тоннель, слалом и барьеры остались позади.', 'en' => 'The tunnel, weave poles and jumps are behind you.'],
                'rule_description' => ['ru' => 'Победи в аджилити.', 'en' => 'Win an agility competition.'],
            ],
            'nosework-winner' => [
                'rules' => ['metric' => 'nosework_wins', 'target' => 1],
                'name' => ['ru' => 'Нос знает ответ', 'en' => 'The nose knows'],
                'description' => ['ru' => 'Судья ещё проверяет, а нос уже всё понял.', 'en' => 'The judge is still checking. The nose already knows.'],
                'rule_description' => ['ru' => 'Победи в ноузворке.', 'en' => 'Win a nosework competition.'],
            ],
            'canicross-winner' => [
                'rules' => ['metric' => 'canicross_wins', 'target' => 1],
                'name' => ['ru' => 'Двое на одном финише', 'en' => 'Two at one finish line'],
                'description' => ['ru' => 'Собака тянула вперёд. Ты старался соответствовать.', 'en' => 'Your dog pulled ahead. You did your best to keep up.'],
                'rule_description' => ['ru' => 'Победи в каникроссе.', 'en' => 'Win a canicross competition.'],
            ],
            'first-exhibition' => [
                'rules' => ['metric' => 'exhibition_starts', 'target' => 1],
                'name' => ['ru' => 'Красота по расписанию', 'en' => 'Beauty on schedule'],
                'description' => ['ru' => 'Первый выход на выставку. Волнение спрятано под шерстью.', 'en' => 'Your first show. The nerves are hidden under the fur.'],
                'rule_description' => ['ru' => 'Заверши первую выставку экстерьера или конкурс производителей.', 'en' => 'Complete your first conformation show or progeny competition.'],
            ],
            'first-exhibition-podium' => [
                'rules' => ['metric' => 'exhibition_podiums', 'target' => 1],
                'name' => ['ru' => 'Лента к лицу', 'en' => 'A ribbon suits you'],
                'description' => ['ru' => 'Судья оценил стойку. Собака оценила аплодисменты.', 'en' => 'The judge appreciated the stance. Your dog appreciated the applause.'],
                'rule_description' => ['ru' => 'Займи одно из первых трёх мест на выставке без дисквалификации.', 'en' => 'Finish in the top three of a show without elimination.'],
            ],
            'exhibition-winner' => [
                'rules' => ['metric' => 'exhibition_wins', 'target' => 1],
                'name' => ['ru' => 'Лучший выход', 'en' => 'Best appearance'],
                'description' => ['ru' => 'Титул получен. Теперь можно снова валяться на диване.', 'en' => 'The title is yours. Time to return to the sofa.'],
                'rule_description' => ['ru' => 'Победи на первой выставке экстерьера или в конкурсе производителей.', 'en' => 'Win your first conformation show or progeny competition.'],
            ],
            'weekly-champion' => [
                'rules' => ['metric' => 'weekly_event_wins', 'target' => 1],
                'name' => ['ru' => 'Хвост недели', 'en' => 'Tail of the week'],
                'description' => ['ru' => 'Неделя тренировок закончилась кубком.', 'en' => 'A week of preparation ended with a trophy.'],
                'rule_description' => ['ru' => 'Победи в еженедельном соревновании или на еженедельной выставке.', 'en' => 'Win a weekly competition or show.'],
            ],
            'monthly-champion' => [
                'rules' => ['metric' => 'monthly_event_wins', 'target' => 1],
                'name' => ['ru' => 'Месяц под знаком собаки', 'en' => 'Month of the dog'],
                'description' => ['ru' => 'Победа, которую стоит отметить в календаре.', 'en' => 'A victory worth marking on the calendar.'],
                'rule_description' => ['ru' => 'Победи в ежемесячном соревновании или на ежемесячной выставке.', 'en' => 'Win a monthly competition or show.'],
            ],
            'first-progeny-show' => [
                'rules' => ['metric' => 'progeny_starts', 'target' => 1],
                'name' => ['ru' => 'Семейное портфолио', 'en' => 'A family portfolio'],
                'description' => ['ru' => 'Потомки вышли в свет. Родитель принимает поздравления.', 'en' => 'The offspring made their debut. Their parent accepts congratulations.'],
                'rule_description' => ['ru' => 'Заверши первый конкурс производителей.', 'en' => 'Complete your first progeny competition.'],
            ],
            'first-litter' => [
                'rules' => ['metric' => 'litters_born', 'target' => 1],
                'name' => ['ru' => 'Маленькие большие планы', 'en' => 'Tiny paws, big plans'],
                'description' => ['ru' => 'Первый помёт родился. Тишина закончилась.', 'en' => 'Your first litter arrived. So much for peace and quiet.'],
                'rule_description' => ['ru' => 'Дождись рождения первого помёта от начатой тобой вязки.', 'en' => 'Welcome the first litter from a breeding you initiated.'],
            ],
            'breeder-5' => [
                'rules' => ['metric' => 'litters_born', 'target' => 5],
                'name' => ['ru' => 'Пять выпусков хвостатых', 'en' => 'Five graduating litters'],
                'description' => ['ru' => 'Пять помётов. Семейное древо пора рисовать на стене.', 'en' => 'Five litters. The family tree now needs an entire wall.'],
                'rule_description' => ['ru' => 'Дождись рождения пяти помётов от начатых тобой вязок.', 'en' => 'Welcome five litters from breedings you initiated.'],
            ],
            'puppies-10' => [
                'rules' => ['metric' => 'puppies_born', 'target' => 10],
                'name' => ['ru' => 'Десять причин не спать', 'en' => 'Ten reasons to stay awake'],
                'description' => ['ru' => 'Десять щенков родились. Каждый уверен, что он главный.', 'en' => 'Ten puppies were born. Every one of them thinks they are in charge.'],
                'rule_description' => ['ru' => 'Выведи суммарно десять родившихся щенков в начатых тобой вязках.', 'en' => 'Breed a total of ten born puppies from breedings you initiated.'],
            ],
            'puppy-kept' => [
                'rules' => ['metric' => 'puppies_kept', 'target' => 1],
                'name' => ['ru' => 'Этот остаётся', 'en' => 'This one stays'],
                'description' => ['ru' => 'Решение принято. В доме стало на один хвост больше.', 'en' => 'The decision is made. There is one more tail at home.'],
                'rule_description' => ['ru' => 'Оставь себе первого щенка из полученного помёта.', 'en' => 'Keep your first puppy from a litter you received.'],
            ],
            'puppy-sold' => [
                'rules' => ['metric' => 'puppies_sold', 'target' => 1],
                'name' => ['ru' => 'Новый дом найден', 'en' => 'A new home found'],
                'description' => ['ru' => 'Щенок отправился к другому игроку. Фото всё равно ждёшь.', 'en' => 'Your puppy went to another player. You still expect photos.'],
                'rule_description' => ['ru' => 'Продай первого щенка другому игроку. Объявление без продажи не считается.', 'en' => 'Sell your first puppy to another player. An unsold listing does not count.'],
            ],
            'titled-offspring' => [
                'rules' => ['metric' => 'titled_offspring', 'target' => 1],
                'name' => ['ru' => 'Гордость заводчика', 'en' => 'A breeder’s pride'],
                'description' => ['ru' => 'Потомок получил титул. Даже в чужом доме он часть твоей истории.', 'en' => 'An offspring earned a title. Even in another home, they are part of your story.'],
                'rule_description' => ['ru' => 'Выведи щенка, который затем получит титул за победу в соревновании или на выставке.', 'en' => 'Breed a puppy that later earns a winning competition or show title.'],
            ],
            'ammunition-prepared' => [
                'rules' => ['metric' => 'ammunition_purchases', 'target' => 3],
                'name' => ['ru' => 'Снаряжение с характером', 'en' => 'Gear with personality'],
                'description' => ['ru' => 'Три покупки амуниции. Теперь главное — выбрать подходящую.', 'en' => 'Three gear purchases. Now comes the art of choosing what fits.'],
                'rule_description' => ['ru' => 'Купи три предмета соревновательной амуниции. Спортивный инвентарь не считается.', 'en' => 'Buy three items of competition ammunition. Training equipment does not count.'],
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
