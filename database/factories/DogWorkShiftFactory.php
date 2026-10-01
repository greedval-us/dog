<?php

namespace Database\Factories;

use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DogWorkShift> */
class DogWorkShiftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pet_id' => Pet::factory(), 'user_id' => fn (array $attributes): int => (int) Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->user_id,
            'pet_name' => fn (array $attributes): string => Pet::query()->whereKey($attributes['pet_id'])->firstOrFail()->name,
            'dog_work_offer_id' => DogWorkOffer::factory(), 'token' => (string) Str::uuid(), 'activity_token' => (string) Str::uuid(),
            'name' => ['ru' => 'Поиск в парке', 'en' => 'Park search'], 'coins_reward' => 150, 'gems_reward' => 0,
            'started_at' => now(), 'ends_at' => now()->addMinutes(30), 'completed_at' => null];
    }
}
