<?php

namespace Database\Factories;

use App\Models\DogWorkBoard;
use App\Models\DogWorkOffer;
use App\Models\DogWorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DogWorkOffer> */
class DogWorkOfferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['dog_work_board_id' => DogWorkBoard::factory(), 'dog_work_type_id' => DogWorkType::factory(), 'reserved_count' => 0];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DogWorkOffer $offer): void {
            $job = DogWorkType::query()->with('skill')->findOrFail($offer->dog_work_type_id);
            $snapshot = [...$job->only(['code', 'name', 'description', 'required_skill_id', 'required_skill_level',
                'coins_reward', 'gems_reward', 'duration_seconds', 'energy_cost', 'daily_limit']),
                'required_skill_name' => $job->skill->name];
            foreach ($snapshot as $key => $value) {
                if (! array_key_exists($key, $offer->getAttributes())) {
                    $offer->setAttribute($key, $value);
                }
            }
        });
    }
}
