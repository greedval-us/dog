<?php

namespace App\Modules\Pets\Queries;

use App\Models\StatusEffect;
use App\Modules\Pets\Calculators\PetStatusRules;

/** @phpstan-import-type Effect from PetStatusRules */
final class GetCareStatusEffects
{
    /** @return array<string, list<Effect>> */
    public function handle(): array
    {
        $variants = [];
        foreach (StatusEffect::query()->where('is_active', true)->where('kind', 'buff')
            ->whereNull('condition_state')->where('duration_seconds', '>', 0)
            ->whereNotNull('care_variants')->orderBy('id')->get() as $effect) {
            if (($effect->conditions ?? []) !== []) {
                continue;
            }
            foreach ($effect->care_variants ?? [] as $variant) {
                $variants[$variant][] = $effect->snapshot();
            }
        }

        return $variants;
    }
}
