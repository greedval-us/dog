<?php

namespace Database\Factories;

use App\Models\PetTitle;
use App\Models\PetTitleConsumption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetTitleConsumption>
 */
class PetTitleConsumptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credential_title_id' => PetTitle::factory()->state(['kind' => 'certificate', 'code' => 'CAC']),
            'championship_title_id' => function (array $attributes): int {
                $credential = PetTitle::query()->whereKey($attributes['credential_title_id'])->firstOrFail();

                return PetTitle::factory()->create([
                    'pet_id' => $credential->pet_id, 'game_event_entry_id' => $credential->game_event_entry_id,
                    'kind' => 'championship', 'code' => 'GCH_RKF',
                ])->id;
            },
            'purpose' => 'show:GCH_RKF',
        ];
    }
}
