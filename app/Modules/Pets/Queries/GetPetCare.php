<?php

namespace App\Modules\Pets\Queries;

use App\Models\InventoryItem;
use App\Models\PetCareAction;
use App\Models\StatusEffect;
use App\Models\User;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetStateCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Str;

final class GetPetCare
{
    public function __construct(private PetCareRules $rules, private PetStatusRules $statuses, private ItemEffectRules $riskRules, private PetStateCalculator $states) {}

    /** @return array<string, mixed> */
    public function handle(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->findOrFail($petId);
        $pet->advanceStatesTo(now(), $this->states);
        $options = $this->rules->options($pet->size);
        $catalogue = array_values(StatusEffect::query()->where('is_active', true)->whereNotNull('condition_state')->get()->map(fn (StatusEffect $effect): array => $effect->snapshot())->all());
        $buffs = $this->statuses->current($catalogue, $pet->statePercentages(), $pet->buffs ?? [], now()->getTimestamp(), 'buff');
        $debuffs = $this->statuses->current($catalogue, $pet->statePercentages(), $pet->debuffs ?? [], now()->getTimestamp(), 'debuff');
        $modifiers = $this->statuses->modifiers([...$buffs, ...$debuffs]);
        $items = $user->inventoryItems()->with('item.category')->where('remaining_uses', '>', 0)
            ->whereHas('item.category', fn ($query) => $query->whereIn('code', ['food', 'collars', 'leashes', 'toys', 'care', 'clothing', 'sports']))
            ->oldest('id')->get();
        $cooldowns = PetCareAction::query()->where('pet_id', $petId)->where('available_at', '>', now())
            ->get()->groupBy('group')->map(fn ($actions) => $actions->max('available_at')->toIso8601String());
        $active = PetCareAction::query()->where('pet_id', $petId)->where('user_id', $user->id)
            ->where('activity_token', $pet->activity_token)->whereNull('completed_at')->first();

        $variants = [];

        foreach ($options as $id => $option) {
            $baseEnergy = $option['energy'];
            $option['energy'] = $this->statuses->energyCost($baseEnergy, $modifiers['energy_cost_percent']);
            $variants[] = [
                'id' => $id,
                ...$option,
                'baseEnergy' => $baseEnergy,
                'reason' => $this->rules->unavailableReason($option, $pet->statePercentages(), $pet->energy),
            ];
        }

        return [
            'token' => (string) Str::uuid(),
            'serverNow' => now()->toIso8601String(),
            'buffs' => $buffs,
            'modifierKeys' => $this->statuses->modifierKeys(),
            'debuffs' => $debuffs,
            'recentIncidents' => PetCareAction::query()->where('user_id', $user->id)->where('pet_id', $petId)
                ->whereNotNull('completed_at')->whereNotNull('incidents')->latest('completed_at')->latest('id')->limit(3)->get()
                ->map(fn (PetCareAction $care): array => [
                    'id' => $care->id, 'occurredAt' => $care->ends_at->toIso8601String(), 'incidents' => $care->incidents,
                ])->all(),
            'blocked' => $user->status !== PlayerStatus::Active || $pet->retired_at !== null,
            'busy' => $pet->isBusy(),
            'cooldowns' => $cooldowns,
            'options' => $variants,
            'items' => $items->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'category' => $item->item->category->code,
                'name' => $item->name[$locale] ?? $item->name['en'] ?? $item->item->code,
                'quality' => $item->quality,
                'bonus' => $this->rules->qualityBonus($item->item->category->code, $item->quality),
                'bonuses' => $item->bonuses ?? [],
                'grantedEffects' => $this->riskRules->guaranteed($this->riskRules->forItem($item->effect_rules ?? [], $item->quality, $item->name)),
                'risks' => $this->riskRules->uncertain($this->riskRules->forItem($item->effect_rules ?? [], $item->quality, $item->name)),
                'remainingUses' => $item->remaining_uses,
            ])->all(),
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
