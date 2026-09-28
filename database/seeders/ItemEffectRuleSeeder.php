<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\StatusEffect;
use Illuminate\Database\Seeder;

class ItemEffectRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Item::query()->with('category')->each(fn (Item $item) => $this->seedFor($item));
    }

    public function seedFor(Item $item): void
    {
        $codes = config('item_bonuses.'.$item->code.'.granted_effects', []);
        $riskCode = match ($item->category->code) {
            'food' => 'poisoning',
            'collars', 'leashes', 'clothing', 'sports', 'toys' => 'minor_injury',
            default => null,
        };
        foreach (StatusEffect::query()->whereIn('code', [...$codes, ...($riskCode === null ? [] : [$riskCode])])->get() as $effect) {
            $item->effectRules()->firstOrCreate(['status_effect_id' => $effect->id], [
                'chance_percent' => $effect->code === $riskCode ? 0 : 100,
                'chance_by_quality' => $effect->code === $riskCode ? [1 => 3, 2 => 2.5, 3 => 2, 4 => 1, 5 => 0.5] : null,
            ]);
        }
    }
}
