<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\StatusEffect;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemEffectRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(fn () => Item::query()->with('category')->each(fn (Item $item) => $this->seedFor($item)));
    }

    public function seedFor(Item $item): void
    {
        $codes = config('item_bonuses.'.$item->code.'.granted_effects', []);
        $riskCode = match ($item->category->code) {
            'food' => 'poisoning',
            'collars', 'leashes' => 'chafing',
            'clothing' => 'stuffy',
            'sports' => 'muscle_soreness',
            'toys' => 'overstimulated',
            'care' => 'skin_irritation',
            default => null,
        };
        $defaultChances = [1 => 3, 2 => 2.5, 3 => 2, 4 => 1, 5 => 0.5];
        $legacyRisk = in_array($item->category->code, ['toys', 'collars', 'leashes', 'clothing', 'sports'], true)
            ? $item->effectRules()->whereHas('statusEffect', fn ($query) => $query->where('code', 'minor_injury'))->first()
            : null;
        $replaceLegacyRisk = $legacyRisk !== null && $legacyRisk->chance_percent === 0.0
            && $legacyRisk->chance_by_quality == $defaultChances && $legacyRisk->duration_seconds === null;
        foreach (StatusEffect::query()->whereIn('code', [...$codes, ...($riskCode === null ? [] : [$riskCode])])->get() as $effect) {
            $durations = [
                1 => $effect->duration_seconds, 2 => $effect->duration_seconds,
                3 => (int) ($effect->duration_seconds * 0.8),
                4 => (int) ($effect->duration_seconds * 0.6),
                5 => (int) ($effect->duration_seconds * 0.5),
            ];
            $rule = $item->effectRules()->firstOrCreate(['status_effect_id' => $effect->id], [
                'chance_percent' => $effect->code === $riskCode ? 0 : 100,
                'chance_by_quality' => $effect->code === $riskCode ? $defaultChances : null,
                'duration_by_quality' => $effect->code === $riskCode ? $durations : null,
                'is_active' => $effect->code === $riskCode && $replaceLegacyRisk ? $legacyRisk->is_active : true,
            ]);
            if ($effect->code === $riskCode && $rule->chance_percent === 0.0
                && $rule->chance_by_quality == $defaultChances && $rule->duration_seconds === null && $rule->duration_by_quality === null) {
                $rule->update(['duration_by_quality' => $durations]);
            }
        }
        if ($replaceLegacyRisk) {
            $legacyRisk->update(['is_active' => false]);
        }
    }
}
