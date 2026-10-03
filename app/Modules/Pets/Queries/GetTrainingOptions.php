<?php

namespace App\Modules\Pets\Queries;

use App\Models\Training;
use App\Modules\Pets\Calculators\TrainingRules;
use App\Modules\Pets\DTO\CareOption;
use App\Modules\Pets\Enums\PetActivity;

final class GetTrainingOptions
{
    public function __construct(private TrainingRules $rules) {}

    /** @return array<string, CareOption> */
    public function handle(string $locale): array
    {
        $options = [];
        foreach (Training::query()->where('is_active', true)->with('statusEffect')->orderBy('id')->get() as $training) {
            if (! $this->rules->valid($training->stat_gains, $training->state_costs, $training->energy_cost, $training->duration_seconds, $training->cooldown_seconds)) {
                continue;
            }
            $effect = $training->statusEffect;
            $risks = $effect !== null && $effect->is_active && $effect->kind === 'debuff' && $effect->duration_seconds > 0 && $training->risk_chance > 0
                ? [['effect' => $effect->snapshot(), 'chance' => min(10000, $training->risk_chance), 'item_name' => $training->name, 'quality' => 0]] : [];
            $options['training:'.$training->id] = new CareOption(
                group: PetActivity::Training, label: $training->name[$locale] ?? $training->name['en'] ?? $training->code,
                duration: $training->duration_seconds, cooldown: $training->cooldown_seconds,
                energy: $training->energy_cost, requirements: ['sports'], optional: [], uses: ['sports' => 1],
                effects: array_map(fn (int $cost): int => -$cost, $training->state_costs),
                statGains: $training->stat_gains, trainingName: $training->name, risks: $risks,
            );
        }

        return $options;
    }
}
