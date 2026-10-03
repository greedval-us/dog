<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Pets\Enums\VeterinaryService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VeterinaryVisit>
 */
class VeterinaryVisitFactory extends Factory
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
            'pet_id' => Pet::factory(),
            'pet_name' => fake()->firstName(),
            'service' => VeterinaryService::Checkup,
            'token' => (string) Str::uuid(),
            'price_paid' => 60,
            'performed_at' => now(),
            'available_at' => now()->addWeek(),
        ];
    }
}
