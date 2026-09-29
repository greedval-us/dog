<?php

namespace Database\Seeders;

use App\Models\StatusEffect;
use App\Models\Training;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $injuries = [];
        foreach ([
            ['training_sprain', 'Подвернул лапу', 'Sprained paw', 3600, 25],
            ['training_bruise', 'Ушиб', 'Bruise', 1800, 15],
        ] as [$code, $ru, $en, $duration, $energy]) {
            $injuries[$code] = StatusEffect::query()->firstOrCreate(['code' => $code], [
                'kind' => 'debuff', 'name' => ['ru' => $ru, 'en' => $en],
                'description' => ['ru' => 'После тренировки движения требуют больше сил. Отдых поможет восстановиться.', 'en' => 'Moving takes more energy after training. Rest helps recovery.'],
                'modifiers' => ['energy_cost_percent' => $energy, 'mood_loss_percent' => 10],
                'duration_seconds' => $duration, 'is_active' => true,
                'recovery_actions' => ['nap' => 600, 'sleep' => 1200],
            ]);
        }
        foreach ([
            ['sprint', 'Бег между конусами', 'Cone sprint', ['speed' => 4, 'endurance' => 3], 15, 120, 600, 'training_sprain', 200],
            ['agility', 'Полоса препятствий', 'Obstacle course', ['agility' => 4, 'strength' => 3], 18, 180, 900, 'training_bruise', 300],
            ['precision', 'Точность команд', 'Precision exercises', ['obedience' => 4, 'intelligence' => 3], 10, 120, 600, 'training_bruise', 100],
            ['stamina', 'Размеренный бег', 'Steady running', ['endurance' => 6], 20, 240, 1200, 'training_sprain', 300],
        ] as [$code, $ru, $en, $gains, $energy, $duration, $cooldown, $injury, $chance]) {
            Training::query()->firstOrCreate(['code' => $code], [
                'name' => ['ru' => $ru, 'en' => $en], 'stat_gains' => $gains,
                'state_costs' => ['satiety' => 5, 'hydration' => 8, 'cleanliness' => 3],
                'energy_cost' => $energy, 'duration_seconds' => $duration, 'cooldown_seconds' => $cooldown,
                'status_effect_id' => ($injuries[$injury] ?? throw new \LogicException('Missing training injury.'))->id, 'risk_chance' => $chance, 'is_active' => true,
            ]);
        }
    }
}
