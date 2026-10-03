<?php

namespace App\Modules\Pets\Queries;

use App\Models\Training;
use App\Modules\Pets\Calculators\TrainingRules;
use App\Modules\Pets\DTO\CareOption;
use App\Modules\Pets\Enums\PetActivity;

final class TrainingOptionMapper
{
    public function __construct(private TrainingRules $rules) {}

    public function map(Training $training, string $locale): ?CareOption
    {
        if (! $this->rules->valid($training->stat_gains, $training->state_costs, $training->energy_cost, $training->duration_seconds, $training->cooldown_seconds)) {
            return null;
        }

        $effect = $training->statusEffect;
        $risks = $effect !== null && $effect->is_active && $effect->kind === 'debuff' && $effect->duration_seconds > 0 && $training->risk_chance > 0
            ? [['effect' => $effect->snapshot(), 'chance' => min(10000, $training->risk_chance), 'item_name' => $training->name, 'quality' => 0]] : [];

        return new CareOption(
            group: PetActivity::Training, label: $training->name[$locale] ?? $training->name['en'] ?? $training->code,
            duration: $training->duration_seconds, cooldown: $training->cooldown_seconds,
            energy: $training->energy_cost, requirements: ['sports'], optional: [], uses: ['sports' => 1],
            effects: array_map(fn (int $cost): int => -$cost, $training->state_costs),
            statGains: $training->stat_gains, trainingName: $training->name, risks: $risks,
        );
    }
}
