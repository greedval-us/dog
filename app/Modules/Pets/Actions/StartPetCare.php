<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StartPetCare
{
    public function __construct(
        private PetCareRules $rules,
        private PetActivityManager $activities,
        private InventoryConsumption $inventory,
        private CompletePetCare $completeCare,
    ) {}

    /** @param array<string, int> $itemIds */
    public function handle(User $user, int $petId, string $variant, array $itemIds, string $token): PetCareAction
    {
        if (! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid care token.');
        }

        $token = strtolower($token);
        ksort($itemIds);

        return DB::transaction(function () use ($user, $petId, $variant, $itemIds, $token): PetCareAction {
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

            $option = $this->rules->options($pet->size)[$variant] ?? throw new InvalidArgumentException('Unknown care action.');

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

            $reason = $this->rules->unavailableReason($option, $pet->statePercentages(), $pet->energy);

            if ($reason !== null) {
                throw new PetUnavailable($reason);
            }

            $requirements = $option['requirements'];
            sort($requirements);

            if (array_keys($itemIds) !== $requirements) {
                throw new PetUnavailable('Select the required items from your inventory.');
            }

            $instances = $owner->inventoryItems()->with('item.category')->whereIn('id', array_values($itemIds))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $qualities = [];

            foreach ($itemIds as $category => $id) {
                $instance = $instances->get($id);

                if ($instance === null || $instance->remaining_uses < $option['uses'][$category] || $instance->item->category->code !== $category) {
                    throw new PetUnavailable('A selected item is unavailable. Choose another item.');
                }

                $qualities[$category] = $instance->quality;
            }

            $started = $this->activities->start($owner, $petId, PetActivity::from($option['group']), now()->addSeconds($option['duration']), $option['energy']);

            foreach ($itemIds as $category => $id) {
                $usage = $this->inventory->handle($owner, $id, (string) Str::uuid(), $option['uses'][$category]);

                if (! $usage->wasRecentlyCreated) {
                    throw new PetUnavailable('A selected item is unavailable. Choose another item.');
                }
            }

            return PetCareAction::query()->create([
                'user_id' => $owner->id,
                'pet_id' => $petId,
                'token' => $token,
                'activity_token' => $started->token,
                'group' => $option['group'],
                'variant' => $variant,
                'inventory_item_ids' => $itemIds,
                'effects' => $this->rules->effects($option, $qualities),
                'ends_at' => $started->endsAt,
                'available_at' => $started->endsAt->addSeconds($option['cooldown']),
            ]);
        }, attempts: 3);
    }
}
