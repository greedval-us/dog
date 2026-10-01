<?php

namespace Database\Factories;

use App\Models\DogWorkBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DogWorkBoard> */
class DogWorkBoardFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['work_date' => now(config('doglive.work_timezone'))->toDateString(), 'generated_at' => now()];
    }
}
