<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\PetDiseaseCounter;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Calculators\VeterinaryRules;
use App\Modules\Pets\DTO\VeterinaryServiceDefinition;
use App\Modules\Pets\Enums\VeterinaryService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class VeterinaryCare
{
    public function __construct(
        private PetDecayCalculator $decay,
        private PetStatusRules $statuses,
        private VeterinaryRules $rules,
        private PetStateSynchronizer $state,
    ) {}

    /** The calling Action owns the transaction, owner/pet locks and final pet save. */
    public function apply(Pet $pet, VeterinaryServiceDefinition $definition, ?PetDisease $episode, CarbonImmutable $at, ?CarbonImmutable $availableAt): void
    {
        $pet->advanceTo($at, $this->decay);

        match ($definition->service) {
            VeterinaryService::Treatment => $this->treat($pet, $episode ?? throw new InvalidArgumentException('Treatment requires a disease episode.'), $at),
            VeterinaryService::Checkup => $pet->health = $this->rules->checkupHealth($pet->health, $pet->health_max, $definition->healthRestorePercent),
            VeterinaryService::Vaccination => $this->vaccinate($pet, $definition, $at, $availableAt ?? throw new InvalidArgumentException('Vaccination requires an expiry date.')),
        };

        $this->state->synchronize($pet, $at);
    }

    private function treat(Pet $pet, PetDisease $episode, CarbonImmutable $at): void
    {
        $episode->ended_at = $at;
        $episode->save();
        PetDiseaseCounter::query()->where('pet_id', $pet->id)->where('disease_id', $episode->disease_id)
            ->update(['action_count' => 0]);
    }

    private function vaccinate(Pet $pet, VeterinaryServiceDefinition $definition, CarbonImmutable $at, CarbonImmutable $availableAt): void
    {
        $pet->buffs = $this->statuses->award($pet->buffs ?? [], [[
            'code' => 'veterinarian:vaccination', 'kind' => 'buff',
            'name' => ['ru' => 'Прививка', 'en' => 'Vaccination'],
            'description' => ['ru' => 'Восстановление здоровья усилено.', 'en' => 'Health recovery is improved.'],
            'modifiers' => ['health_gain_percent' => $definition->healthRecoveryBonus],
            'duration_seconds' => $availableAt->getTimestamp() - $at->getTimestamp(),
            'condition_state' => null, 'recovery_actions' => [],
        ]], $at->getTimestamp(), $at->getTimestamp());
    }
}
