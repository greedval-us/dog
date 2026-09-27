<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'work_type_id' => WorkType::factory(),
            'worked_on' => now(config('doglive.work_timezone'))->toDateString(),
            'streak_day' => 1,
            'coins_reward' => 50,
            'gems_reward' => 0,
        ];
    }
}
