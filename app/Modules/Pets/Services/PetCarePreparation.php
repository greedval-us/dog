<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\DTO\CareOption;
use App\Modules\Pets\DTO\PetStatusData;
use App\Modules\Pets\DTO\PreparedPetCare;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetCareStatusEffects;
use InvalidArgumentException;
use Random\Randomizer;

final class PetCarePreparation
{
    public function __construct(
        private PetCareRules $rules,
        private PetStatusRules $statuses,
        private ItemEffectRules $riskRules,
        private GetCareStatusEffects $careStatuses,
        private Randomizer $random,
    ) {}

    public function option(Pet $pet, string $variant): CareOption
    {
        return $this->rules->options($pet->size)[$variant] ?? throw new InvalidArgumentException('Unknown care action.');
    }

    /**
     * Prepare a receipt snapshot without spending items or saving the pet.
     * The caller opens the transaction and locks the owner, pet and any expired care receipt first.
     * Selected inventory rows are locked here in ID order; draws survive retries of that transaction.
     *
     * @param  array<string, int>  $itemIds
     * @param  array<string, int>  $rolls
     */
    public function prepare(User $owner, Pet $pet, string $variant, array $itemIds, CareOption $option, PetStatusData $status, array &$rolls): PreparedPetCare
    {
        $option = $option->withEnergy($this->statuses->energyCost($option->energy, $status->modifiers['energy_cost_percent']));
        $recovery = $this->statuses->recovery($status->debuffs, $variant);
        $refusal = $this->rules->unavailableCode($option, $pet->statePercentages(null), $pet->energy, $recovery !== []);
        if ($refusal !== null) {
            throw PetUnavailable::forCare($refusal);
        }

        if (array_diff($option->requirements, array_keys($itemIds)) !== []
            || array_diff(array_keys($itemIds), [...$option->requirements, ...$option->optional]) !== []) {
            throw PetUnavailable::forCare(CareRefusal::ItemsRequired);
        }

        $instances = $owner->inventoryItems()->with('item.category')->whereIn('id', array_values($itemIds))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $qualities = [];
        $bonuses = [];
        $risks = $option->risks;
        $items = [];
        foreach ($itemIds as $category => $id) {
            $instance = $instances->get($id);
            $uses = $option->uses[$category] ?? 1;
            if ($instance === null || $instance->remaining_uses < $uses || $instance->item->category->code !== $category) {
                throw PetUnavailable::forCare(CareRefusal::ItemUnavailable);
            }
            $items[] = ['category' => $category, 'id' => $id, 'name' => $instance->name, 'uses' => $uses];
            $qualities[$category] = $instance->quality;
            foreach ($instance->bonuses ?? [] as $state => $bonus) {
                $bonuses[$state] = ($bonuses[$state] ?? 0) + $bonus;
            }
            $risks = [...$risks, ...$this->riskRules->forItem($instance->effect_rules ?? [], $instance->quality, $instance->name)];
        }

        foreach ($this->careStatuses->handle()[$variant] ?? [] as $effect) {
            $risks[] = ['effect' => $effect, 'chance' => 10000, 'item_name' => [], 'quality' => 10];
        }
        $incidents = [];
        $grantedEffects = [];
        foreach ($this->riskRules->combine($risks) as $risk) {
            $code = $risk['effect']['code'];
            if ($risk['chance'] === 10000 && $risk['effect']['kind'] === 'buff') {
                $grantedEffects[] = $risk['effect'];

                continue;
            }
            $rolls[$code] ??= $risk['chance'] === 10000 ? 1 : $this->random->getInt(1, 10000);
            if ($rolls[$code] <= $risk['chance']) {
                $incidents[] = $risk;
            }
        }

        return new PreparedPetCare(
            option: $option,
            effects: $this->statuses->apply($this->rules->effects($option, $qualities, $bonuses), $status->modifiers),
            grantedEffects: $grantedEffects,
            incidents: $incidents,
            statusRecovery: $recovery,
            qualities: $qualities,
            items: $items,
        );
    }
}
