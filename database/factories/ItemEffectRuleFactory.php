<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemEffectRule;
use App\Models\StatusEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemEffectRule>
 */
class ItemEffectRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'status_effect_id' => StatusEffect::factory(),
            'chance_percent' => 100,
            'chance_by_quality' => null,
            'duration_seconds' => null,
            'duration_by_quality' => null,
            'is_active' => true,
        ];
    }
}
