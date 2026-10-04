<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\DTO\PetHistoryChange;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Pets\Services\PetCareCompletion;
use App\Modules\Pets\Services\PetCarePreparation;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Pets\Services\PetStateSynchronizer;
use App\Modules\Pets\Services\PetTrainingPreparation;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StartPetCare
{
    public function __construct(
        private PetCarePreparation $carePreparation,
        private PetTrainingPreparation $trainingPreparation,
        private PetActivityManager $activities,
        private InventoryConsumption $inventory,
        private PetCareCompletion $completion,
        private PetStateSynchronizer $state,
        private PetHistoryRecorder $history,
        private PetLifecycleSynchronization $lifecycle,
    ) {}

    /**
     * Synchronize elapsed lifetimes before launch. Expired care completed during launch,
     * activity claims, inventory spending and the new receipt share the launch transaction.
     * A refused or failed launch rolls back that expired care; its random draws survive retries.
     *
     * @param  array<string, int>  $itemIds
     */
    public function handle(User $user, int $petId, string $variant, array $itemIds, string $token): PetCareAction
    {
        if (! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid care token.');
        }

        $token = strtolower($token);
        ksort($itemIds);
        $rolls = [];
        $thresholds = [];
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $variant, $itemIds, $token, &$rolls, &$thresholds): PetCareAction {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw PetUnavailable::forCare(CareRefusal::PlayerBlocked);
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $existing = PetCareAction::query()->where('user_id', $owner->id)->where('token', $token)->first();

            if ($existing !== null) {
                if ($existing->pet_id !== $petId || $existing->variant !== $variant || $existing->inventory_item_ids !== $itemIds) {
                    throw new InvalidArgumentException('The care token was already used for a different action.');
                }

                return $existing;
            }

            $preparation = str_starts_with($variant, 'training:') ? $this->trainingPreparation : $this->carePreparation;
            $option = $preparation->option($pet, $variant);

            if (! $pet->isActive() && $pet->retired_at === null) {
                throw PetUnavailable::forCare(CareRefusal::Inactive);
            }

            if ($pet->isBusy()) {
                $this->lifecycle->assertCanAdvance($owner);
                $finished = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $petId)
                    ->where('activity_token', $pet->activity_token)->whereNull('completed_at')->whereNull('cancelled_at')
                    ->where('ends_at', '<=', now())->lockForUpdate()->first();

                if ($finished !== null) {
                    $this->completion->complete($owner, $pet, $finished, now(), $thresholds);
                    $pet->refresh();
                }
            }

            if (! $pet->isActive() || $pet->isBusy()) {
                throw PetUnavailable::forCare(CareRefusal::Busy);
            }

            if (PetCareAction::query()->where('pet_id', $petId)->where('group', $option->group->value)->where('available_at', '>', now())->exists()) {
                throw PetUnavailable::forCare(CareRefusal::Cooldown);
            }

            $at = now();
            $this->lifecycle->assertCanAdvance($owner, $at);
            $status = $this->state->advance($pet, $at);
            if ($pet->died_at !== null || $pet->retired_at !== null || $pet->health <= 0) {
                throw PetUnavailable::forCare(CareRefusal::Inactive);
            }
            $prepared = $preparation->prepare($owner, $pet, $variant, $itemIds, $option, $status, $rolls);
            $option = $prepared->option;

            $pet->save();
            $started = $this->activities->start($owner, $petId, $option->group, now()->addSeconds($option->duration), $option->energy);

            foreach ($prepared->items as $item) {
                $usage = $this->inventory->handle($owner, $item['id'], (string) Str::uuid(), $item['uses']);

                if (! $usage->wasRecentlyCreated) {
                    throw PetUnavailable::forCare(CareRefusal::ItemUnavailable);
                }
            }

            $care = PetCareAction::query()->create([
                'user_id' => $owner->id,
                'pet_id' => $petId,
                'token' => $token,
                'activity_token' => $started->token,
                'group' => $option->group->value,
                'variant' => $variant,
                'inventory_item_ids' => $itemIds,
                'effects' => $prepared->effects,
                'granted_effects' => $prepared->grantedEffects,
                'incidents' => $prepared->incidents === [] ? null : $prepared->incidents,
                'status_recovery' => $prepared->statusRecovery,
                'stat_gains' => $prepared->statGains,
                'training_name' => $option->trainingName,
                'ends_at' => $started->endsAt,
                'available_at' => $started->endsAt->addSeconds($option->cooldown),
            ]);

            $energyBefore = $pet->energy / $pet->energy_max * 100;
            $energyAfter = ($pet->energy - $option->energy) / $pet->energy_max * 100;
            $this->history->record($pet, $care->group === 'training' ? 'training' : 'care.'.$variant,
                'care:'.$care->id.':started', $started->startedAt, [
                    'stage' => 'started',
                    'name' => $care->training_name,
                    'durationSeconds' => $option->duration,
                    'items' => array_map(fn (array $item): array => [
                        'category' => $item['category'], 'name' => $item['name'], 'uses' => $item['uses'],
                    ], $prepared->items),
                    'changes' => $option->energy === 0 ? [] : [PetHistoryChange::percent('energy', $energyBefore, $energyAfter)->toArray()],
                ]);

            return $care;
        }, attempts: 3);
    }
}
