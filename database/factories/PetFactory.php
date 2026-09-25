<?php

namespace Database\Factories;

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\PetSex;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pet> */
class PetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dog_id' => Dog::factory(),
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'sex' => PetSex::Male,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Pet $pet): void {
            if (! array_key_exists('coat_color', $pet->getAttributes())) {
                $pet->coat_color = array_key_first($pet->dog->coat_colors);
            }

            foreach ($pet->dog->petDefaults() as $attribute => $value) {
                if (! array_key_exists($attribute, $pet->getAttributes())) {
                    $pet->setAttribute($attribute, $value);
                }
            }
        });
    }

    public function female(): static
    {
        return $this->state(fn (): array => ['sex' => PetSex::Female]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
