<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['keen_nose', 'Развитый нюх', 'Keen nose',
                'Различает знакомые запахи и замечает даже слабый след. Инструктор учит собаку сосредотачиваться на нужном запахе.',
                'Recognises familiar scents and notices even a faint trail. The instructor teaches your dog to focus on the right scent.',
                ['intelligence' => 1, 'obedience' => 0.6], 80],
            ['guardian', 'Охранник', 'Guardian',
                'Внимательно охраняет территорию и предупреждает хозяина о незнакомцах. Учится выдержке и реакции на команду.',
                'Watches over its territory and alerts its owner to strangers. Learns restraint and how to respond to commands.',
                ['strength' => 1, 'obedience' => 0.8], 120],
            ['search', 'Поиск', 'Search',
                'Находит спрятанные предметы по команде. Инструктор учит последовательно обследовать площадку и обозначать находку.',
                'Finds hidden objects on command. The instructor teaches systematic searching and how to signal a find.',
                ['intelligence' => 1, 'agility' => 0.6, 'obedience' => 0.5], 100],
            ['hunter', 'Охотник', 'Hunter',
                'Замечает дичь и помогает хозяину на охоте. Инструктор учит быстрому преследованию и согласованной работе по команде.',
                'Spots game and helps its owner on a hunt. The instructor teaches swift pursuit and coordinated work on command.',
                ['speed' => 1, 'endurance' => 0.8, 'intelligence' => 0.6], 120],
            ['rescuer', 'Спасатель', 'Rescuer',
                'Ищет человека и подаёт сигнал хозяину. Инструктор развивает выдержку, послушание и работу в сложных условиях.',
                'Searches for a person and signals its owner. The instructor develops stamina, obedience and working in difficult conditions.',
                ['endurance' => 1, 'obedience' => 0.8, 'intelligence' => 0.8], 150],
            ['retriever', 'Ловкий апорт', 'Nimble retrieve',
                'Быстро догоняет и аккуратно приносит предмет. Учится ловкости, точности и возвращению к хозяину по команде.',
                'Chases and carefully returns an object. Learns agility, precision and returning to its owner on command.',
                ['agility' => 1, 'speed' => 0.8, 'obedience' => 0.5], 100],
        ] as [$code, $ru, $en, $ruDescription, $enDescription, $requirementFactors, $basePrice]) {
            $levels = [];
            foreach ([10, 25, 45, 70, 100] as $index => $percentage) {
                $levels[] = [
                    'price' => $basePrice * [1, 2, 4, 7, 11][$index],
                    'requirements' => array_map(fn (int|float $factor): int => (int) ceil($percentage * $factor), $requirementFactors),
                ];
            }
            Skill::query()->firstOrCreate(['code' => $code], [
                'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => $ruDescription, 'en' => $enDescription],
                'levels' => $levels, 'is_active' => true,
            ]);
        }
    }
}
