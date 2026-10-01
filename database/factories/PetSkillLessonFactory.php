<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\PetSkillLesson;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PetSkillLesson>
 */
class PetSkillLessonFactory extends Factory
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
            'skill_id' => Skill::factory(),
            'level' => 1,
            'price_paid' => 100,
            'requirements' => ['intelligence' => 10, 'obedience' => 5],
            'token' => (string) Str::uuid(),
            'trained_at' => now(),
            'cooldown_until' => now()->addDay(),
        ];
    }
}
