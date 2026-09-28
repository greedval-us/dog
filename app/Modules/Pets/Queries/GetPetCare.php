<?php

namespace App\Modules\Pets\Queries;

use App\Models\InventoryItem;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetStateCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class GetPetCare
{
    public function __construct(
        private PetCareRules $rules,
        private PetStatusRules $statuses,
        private ItemEffectRules $riskRules,
        private PetStateCalculator $states,
        private GetPetStatuses $getStatuses,
        private GetCareStatusEffects $careStatuses,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->findOrFail($petId);
        $at = now();
        $pet->advanceStatesTo($at, $this->states);
        $options = $this->rules->options($pet->size);
        $status = $this->getStatuses->handle($pet, $at);
        $modifiers = $status->modifiers;
        $items = $user->inventoryItems()
            ->select(['id', 'user_id', 'item_id', 'name', 'quality', 'bonuses', 'effect_rules', 'remaining_uses'])
            ->with(['item:id,item_category_id,code', 'item.category:id,code'])->where('remaining_uses', '>', 0)
            ->whereHas('item.category', fn ($query) => $query->whereIn('code', ['food', 'collars', 'leashes', 'toys', 'care', 'clothing', 'sports']))
            ->oldest('id')->get();
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
            $variants[] = [
                'id' => $id,
                ...$option,
                'baseEnergy' => $baseEnergy,
                'grantedEffects' => $careEffects[$id] ?? [],
                'statusRecovery' => $recovery,
                'reason' => $this->rules->unavailableReason($option, $pet->statePercentages(), $pet->energy, $recovery !== []),
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
            'blocked' => $user->status !== PlayerStatus::Active || $pet->retired_at !== null,
            'busy' => $pet->isBusy(),
            'cooldowns' => $cooldowns,
            'options' => $variants,
            'items' => $items->map(function (InventoryItem $item) use ($locale): array {
                $outcomes = $this->riskRules->forItem($item->effect_rules ?? [], $item->quality, $item->name);

                return [
                    'id' => $item->id,
                    'category' => $item->item->category->code,
                    'name' => $item->name[$locale] ?? $item->name['en'] ?? $item->item->code,
                    'quality' => $item->quality,
                    'bonus' => $this->rules->qualityBonus($item->item->category->code, $item->quality),
                    'bonuses' => $item->bonuses ?? [],
                    'grantedEffects' => $this->riskRules->guaranteed($outcomes),
                    'risks' => $this->riskRules->uncertain($outcomes),
                    'remainingUses' => $item->remaining_uses,
                ];
            })->all(),
            'active' => $active === null ? null : [
                'token' => $active->token,
                'label' => $options[$active->variant]['label'],
                'startedAt' => $active->created_at->toIso8601String(),
                'endsAt' => $active->ends_at->toIso8601String(),
                'effects' => $active->effects,
            ],
        ];
    }
}
