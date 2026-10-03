<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\StatusEffect;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\DTO\PetStatusData;
use Carbon\CarbonImmutable;

final class GetPetStatuses
{
    public function __construct(private PetStatusRules $rules) {}

    /** Project statuses from the supplied pet snapshot without changing or persisting it. */
    public function handle(Pet $pet, CarbonImmutable $at): PetStatusData
    {
        $catalogue = array_values(StatusEffect::query()->where('is_active', true)
            ->orderBy('id')->get()->map(fn (StatusEffect $effect): array => $effect->snapshot())->all());
        $percentages = $pet->statePercentages(precision: null);
        $buffs = $this->rules->current($catalogue, $percentages, $pet->buffs ?? [], $at->getTimestamp(), 'buff');
        $existing = array_values(array_filter($pet->debuffs ?? [], fn (array $effect): bool => ! isset($effect['disease_id'])));
        $diseases = $pet->activeDiseaseEpisodes()->whereNotNull('effect_snapshot')->orderBy('id')->get(['id', 'effect_snapshot'])
            ->map(fn (PetDisease $episode): ?array => $episode->effect_snapshot)->filter()->values()->all();
        $debuffs = $this->rules->current($catalogue, $percentages, [...$existing, ...$diseases], $at->getTimestamp(), 'debuff');

        return new PetStatusData($buffs, $debuffs, $this->rules->modifiers([...$buffs, ...$debuffs]));
    }
}
