<?php

namespace Database\Factories;

use App\Models\PetHistoryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PetHistoryEvent> */
class PetHistoryEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(2), 'kind' => 'action', 'name' => ['ru' => 'Кормление', 'en' => 'Feeding'],
            'conditions' => [], 'cooldown_minutes' => 120, 'priority' => 0, 'is_active' => true];
    }

    public function thought(): static
    {
        return $this->state(fn (): array => ['kind' => 'thought', 'name' => ['ru' => 'Голод', 'en' => 'Hunger'],
            'conditions' => [['field' => 'satiety', 'operator' => 'lt', 'value' => 30]]]);
    }
}
