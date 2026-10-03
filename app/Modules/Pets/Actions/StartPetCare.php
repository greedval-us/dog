<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Calculators\TrainingRules;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetCareStatusEffects;
use App\Modules\Pets\Queries\GetTrainingOptions;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetStateSynchronizer;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Random\Randomizer;

final class StartPetCare
{
    public function __construct(
        private PetCareRules $rules,
        private PetActivityManager $activities,
        private InventoryConsumption $inventory,
        private CompletePetCare $completeCare,
        private PetStatusRules $statuses,
        private ItemEffectRules $riskRules,
        private Randomizer $random,
        private PetStateSynchronizer $state,
        private GetCareStatusEffects $careStatuses,
        private GetTrainingOptions $trainings,
        private TrainingRules $trainingRules,
        private PetHistoryRecorder $history,
    ) {}

    /** @param array<string, int> $itemIds */
    public function handle(User $user, int $petId, string $variant, array $itemIds, string $token): PetCareAction
    {
        if (! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid care token.');
        }

        $token = strtolower($token);
        ksort($itemIds);
        $rolls = [];

        return DB::transaction(function () use ($user, $petId, $variant, $itemIds, $token, &$rolls): PetCareAction {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('This player cannot care for pets.');
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $existing = PetCareAction::query()->where('user_id', $owner->id)->where('token', $token)->first();

            if ($existing !== null) {
                if ($existing->pet_id !== $petId || $existing->variant !== $variant || $existing->inventory_item_ids !== $itemIds) {
                    throw new InvalidArgumentException('The care token was already used for a different action.');
                }

                return $existing;
            }

            $options = str_starts_with($variant, 'training:') ? $this->trainings->handle('en') : $this->rules->options($pet->size);
            $option = $options[$variant] ?? throw new InvalidArgumentException('Unknown care action.');

            if ($pet->isBusy() && $pet->retired_at === null) {
                $finished = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $petId)
                    ->where('activity_token', $pet->activity_token)->whereNull('completed_at')
                    ->where('ends_at', '<=', now())->first();

                if ($finished !== null) {
                    $this->completeCare->handle($owner, $petId, $finished->token);
                    $pet->refresh();
                }
            }

            if ($pet->retired_at !== null || $pet->isBusy()) {
                throw new PetUnavailable('Your dog is busy or retired.');
            }

            if (PetCareAction::query()->where('pet_id', $petId)->where('group', $option['group'])->where('available_at', '>', now())->exists()) {
                throw new PetUnavailable('This action is cooling down. Wait before trying again.');
            }

            $at = now();
            $status = $this->state->advance($pet, $at);
            $modifiers = $status->modifiers;
            $option['energy'] = $this->statuses->energyCost($option['energy'], $modifiers['energy_cost_percent']);
            $recovery = $this->statuses->recovery($status->debuffs, $variant);
            $reason = $this->rules->unavailableReason($option, $pet->statePercentages(null), $pet->energy, $recovery !== []);

            if ($reason !== null) {
                throw new PetUnavailable($reason);
            }

            $requirements = $option['requirements'];
            sort($requirements);

            if (array_diff($requirements, array_keys($itemIds)) !== [] || array_diff(array_keys($itemIds), [...$requirements, ...$option['optional']]) !== []) {
                throw new PetUnavailable('Select the required items from your inventory.');
            }

            $instances = $owner->inventoryItems()->with('item.category')->whereIn('id', array_values($itemIds))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $qualities = [];
            $bonuses = [];
            $risks = $option['risks'] ?? [];

            foreach ($itemIds as $category => $id) {
                $instance = $instances->get($id);

                if ($instance === null || $instance->remaining_uses < ($option['uses'][$category] ?? 1) || $instance->item->category->code !== $category) {
                    throw new PetUnavailable('A selected item is unavailable. Choose another item.');
                }

                $qualities[$category] = $instance->quality;
                foreach ($instance->bonuses ?? [] as $state => $bonus) {
                    $bonuses[$state] = ($bonuses[$state] ?? 0) + $bonus;
                }
                $risks = [...$risks, ...$this->riskRules->forItem($instance->effect_rules ?? [], $instance->quality, $instance->name)];
            }

            $statGains = null;
            if (isset($option['statGains'])) {
                $remaining = [];
                foreach ($option['statGains'] as $name => $gain) {
                    $stat = PetStat::from($name);
                    $remaining[$name] = $pet->getAttribute($stat->potentialColumn()) - $pet->getAttribute($name);
                }
                $statGains = $this->trainingRules->gains($option['statGains'], $qualities['sports'], $pet->statePercentages(null), $remaining);
                if (array_sum($statGains) === 0) {
                    throw new PetUnavailable('Your dog has reached the potential for this training.');
                }
            }

            $pet->save();
            $started = $this->activities->start($owner, $petId, PetActivity::from($option['group']), now()->addSeconds($option['duration']), $option['energy']);

            foreach ($itemIds as $category => $id) {
                $usage = $this->inventory->handle($owner, $id, (string) Str::uuid(), $option['uses'][$category] ?? 1);

                if (! $usage->wasRecentlyCreated) {
                    throw new PetUnavailable('A selected item is unavailable. Choose another item.');
                }
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

            $care = PetCareAction::query()->create([
                'user_id' => $owner->id,
                'pet_id' => $petId,
                'token' => $token,
                'activity_token' => $started->token,
                'group' => $option['group'],
                'variant' => $variant,
                'inventory_item_ids' => $itemIds,
                'effects' => $this->statuses->apply($this->rules->effects($option, $qualities, $bonuses), $modifiers),
                'granted_effects' => $grantedEffects,
                'incidents' => $incidents === [] ? null : $incidents,
                'status_recovery' => $recovery,
                'stat_gains' => $statGains,
                'training_name' => $option['trainingName'] ?? null,
                'ends_at' => $started->endsAt,
                'available_at' => $started->endsAt->addSeconds($option['cooldown']),
            ]);

            $energyBefore = $pet->energy / $pet->energy_max * 100;
            $energyAfter = ($pet->energy - $option['energy']) / $pet->energy_max * 100;
            $this->history->record($pet, $care->group === 'training' ? 'training' : 'care.'.$variant,
                'care:'.$care->id.':started', $started->startedAt, [
                    'stage' => 'started',
                    'name' => $care->training_name,
                    'durationSeconds' => $option['duration'],
                    'items' => array_map(fn (string $category, int $id): array => [
                        'category' => $category, 'name' => $instances->get($id)?->name,
                        'uses' => $option['uses'][$category] ?? 1,
                    ], array_keys($itemIds), array_values($itemIds)),
                    'changes' => $option['energy'] === 0 ? [] : [[
                        'metric' => 'energy', 'before' => round($energyBefore, 4),
                        'after' => round($energyAfter, 4), 'delta' => round($energyAfter - $energyBefore, 4),
                        'unit' => 'percent',
                    ]],
                ]);

            return $care;
        }, attempts: 3);
    }
}
