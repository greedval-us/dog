<?php

namespace App\Modules\Pets\Queries;

use App\Models\DogWorkShift;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Calculators\TrainingRules;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class GetPetCare
{
    public function __construct(
        private PetCareRules $rules,
        private PetStatusRules $statuses,
        private PetDecayCalculator $states,
        private GetPetStatuses $getStatuses,
        private GetCareStatusEffects $careStatuses,
        private GetTrainingOptions $trainings,
        private TrainingRules $trainingRules,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->findOrFail($petId);
        $at = now();
        $pet->advanceTo($at, $this->states);
        $options = [...$this->rules->options($pet->size), ...$this->trainings->handle($locale)];
        $status = $this->getStatuses->handle($pet, $at);
        $modifiers = $status->modifiers;
        $cooldowns = PetCareAction::query()->where('pet_id', $petId)->where('available_at', '>', $at)
            ->select('group')->selectRaw('MAX(available_at) as available_at')->groupBy('group')
            ->pluck('available_at', 'group')->map(fn (CarbonImmutable $availableAt): string => $availableAt->toIso8601String());
        $active = PetCareAction::query()->where('pet_id', $petId)->where('user_id', $user->id)
            ->where('activity_token', $pet->activity_token)->whereNull('completed_at')->first();

        $variants = [];
        $careEffects = $this->careStatuses->handle();

        foreach ($options as $id => $option) {
            $baseEnergy = $option['energy'];
            $recovery = $this->statuses->recovery($status->debuffs, $id);
            $option['energy'] = $this->statuses->energyCost($baseEnergy, $modifiers['energy_cost_percent']);
            $gainsByQuality = [];
            if (isset($option['statGains'])) {
                $remaining = [];
                foreach ($option['statGains'] as $name => $gain) {
                    $remaining[$name] = $pet->getAttribute(PetStat::from($name)->potentialColumn()) - $pet->getAttribute($name);
                }
                foreach (range(1, 10) as $quality) {
                    $gainsByQuality[$quality] = $this->trainingRules->gains($option['statGains'], $quality, $pet->statePercentages(null), $remaining);
                }
            }
            $variants[] = [
                'id' => $id,
                ...$option,
                'baseEnergy' => $baseEnergy,
                'grantedEffects' => $careEffects[$id] ?? [],
                'statusRecovery' => $recovery,
                'gainsByQuality' => $gainsByQuality,
                'reason' => $this->rules->unavailableReason($option, $pet->statePercentages(null), $pet->energy, $recovery !== [])
                    ?? ($gainsByQuality !== [] && array_sum($gainsByQuality[10]) === 0 ? 'Your dog has reached the potential for this training.' : null),
            ];
        }

        return [
            'token' => (string) Str::uuid(),
            'serverNow' => $at->toIso8601String(),
            'buffs' => $status->buffs,
            'modifierKeys' => $this->statuses->modifierKeys(),
            'debuffs' => $status->debuffs,
            'recentIncidents' => PetCareAction::query()->where('user_id', $user->id)->where('pet_id', $petId)
                ->whereNotNull('completed_at')->whereNotNull('incidents')->latest('completed_at')->latest('id')->limit(3)->get()
                ->map(fn (PetCareAction $care): array => [
                    'id' => $care->id, 'occurredAt' => $care->ends_at->toIso8601String(), 'incidents' => $care->incidents,
                ])->all(),
            'blocked' => $user->status !== PlayerStatus::Active || ! $pet->isActive(),
            'busy' => $pet->isBusy(),
            'working' => DogWorkShift::query()->where('pet_id', $petId)->where('user_id', $user->id)
                ->where('activity_token', $pet->activity_token)->whereNull('completed_at')->exists(),
            'cooldowns' => $cooldowns,
            'options' => $variants,
            'active' => $active === null ? null : [
                'token' => $active->token,
                'label' => $active->training_name[$locale] ?? $active->training_name['en'] ?? $options[$active->variant]['label'] ?? 'Training',
                'startedAt' => $active->created_at->toIso8601String(),
                'endsAt' => $active->ends_at->toIso8601String(),
                'effects' => $active->effects,
                'statGains' => $active->stat_gains,
            ],
        ];
    }
}
