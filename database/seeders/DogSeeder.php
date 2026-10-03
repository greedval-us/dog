<?php

namespace Database\Seeders;

use App\Models\Dog;
use App\Modules\Pets\Enums\DogSize;
use Illuminate\Database\Seeder;

class DogSeeder extends Seeder
{
    /**
     * Preliminary game balance. Nutrition and hydration units are not real feeding advice.
     */
    public function run(): void
    {
        $dogs = [
            [
                'breed' => 'german_shepherd',
                'name' => ['ru' => 'Немецкая овчарка', 'en' => 'German Shepherd'],
                'description' => ['ru' => 'Крупная, выносливая собака с высоким потенциалом послушания и интеллекта.', 'en' => 'A large, resilient dog with strong potential for obedience and intelligence.'],
                'size' => DogSize::Large,
                'coat_colors' => [
                    'black_tan' => ['ru' => 'Чепрачный', 'en' => 'Black and tan'],
                    'sable' => ['ru' => 'Зонарный', 'en' => 'Sable'],
                    'black' => ['ru' => 'Чёрный', 'en' => 'Black'],
                ],
                'endurance_potential' => 100,
                'speed_potential' => 85,
                'strength_potential' => 90,
                'agility_potential' => 85,
                'obedience_potential' => 110,
                'intelligence_potential' => 105,
                'health_max' => 120,
                'satiety_max' => 600,
                'hydration_max' => 900,
                'food_per_day' => 300,
                'water_per_day' => 450,
            ],
            [
                'breed' => 'pit_bull',
                'name' => ['ru' => 'Питбуль', 'en' => 'Pit bull'],
                'description' => ['ru' => 'Собака среднего размера с высоким потенциалом силы и выносливости.', 'en' => 'A medium-sized dog with strong potential for strength and endurance.'],
                'size' => DogSize::Medium,
                'coat_colors' => [
                    'fawn' => ['ru' => 'Палевый', 'en' => 'Fawn'],
                    'brindle' => ['ru' => 'Тигровый', 'en' => 'Brindle'],
                    'black' => ['ru' => 'Чёрный', 'en' => 'Black'],
                ],
                'endurance_potential' => 95,
                'speed_potential' => 85,
                'strength_potential' => 115,
                'agility_potential' => 90,
                'obedience_potential' => 75,
                'intelligence_potential' => 80,
                'health_max' => 100,
                'satiety_max' => 400,
                'hydration_max' => 600,
                'food_per_day' => 200,
                'water_per_day' => 300,
            ],
            [
                'breed' => 'dachshund',
                'name' => ['ru' => 'Такса', 'en' => 'Dachshund'],
                'description' => ['ru' => 'Небольшая собака с высоким потенциалом ловкости и интеллекта.', 'en' => 'A small dog with strong potential for agility and intelligence.'],
                'size' => DogSize::Small,
                'coat_colors' => [
                    'red' => ['ru' => 'Рыжий', 'en' => 'Red'],
                    'black_tan' => ['ru' => 'Чёрно-подпалый', 'en' => 'Black and tan'],
                    'chocolate_tan' => ['ru' => 'Коричнево-подпалый', 'en' => 'Chocolate and tan'],
                ],
                'endurance_potential' => 80,
                'speed_potential' => 70,
                'strength_potential' => 55,
                'agility_potential' => 100,
                'obedience_potential' => 80,
                'intelligence_potential' => 100,
                'health_max' => 80,
                'satiety_max' => 200,
                'hydration_max' => 300,
                'food_per_day' => 100,
                'water_per_day' => 150,
            ],
        ];

        foreach ($dogs as $dog) {
            $existing = Dog::query()->where('breed', $dog['breed'])->first();
            Dog::query()->updateOrCreate(['breed' => $dog['breed']], [
                ...$dog,
                'coat_colors' => array_replace($existing === null ? [] : $existing->coat_colors, $dog['coat_colors']),
                'is_starter' => true,
                'energy_max' => 100,
                'mood_max' => 100,
                'cleanliness_max' => 100,
                'bond_max' => 100,
            ]);
        }
    }
}
