<?php

namespace App\Modules\Inventory\Services;

use App\Models\ItemUsage;
use App\Models\User;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Public cross-module operation for consuming inventory within a gameplay transaction. */
final class InventoryConsumption
{
    /**
     * Run within the gameplay transaction together with the item's effects.
     * A repeated token returns its original receipt, even when the item no longer exists.
     * Apply effects only when the returned receipt wasRecentlyCreated.
     */
    public function handle(User $user, int $inventoryItemId, string $token, int $uses = 1): ItemUsage
    {
        if ($inventoryItemId < 1 || $uses < 1 || $uses > 2147483647 || ! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid item usage.');
        }

        $token = strtolower($token);

        return DB::transaction(function () use ($user, $inventoryItemId, $token, $uses): ItemUsage {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new ItemUnavailable('This player cannot use items.');
            }

            $existing = $owner->itemUsages()->where('token', $token)->first();

            if ($existing !== null) {
                if ($existing->inventory_item_id !== $inventoryItemId || $existing->uses_spent !== $uses) {
                    throw new InvalidArgumentException('The token was already used for a different item usage.');
                }

                return $existing;
            }

            $instance = $owner->inventoryItems()->lockForUpdate()->findOrFail($inventoryItemId);

            if ($instance->remaining_uses < $uses) {
                throw new ItemUnavailable('The item does not have enough uses left.');
            }

            $remaining = $instance->remaining_uses - $uses;
            $receipt = $owner->itemUsages()->create([
                'item_id' => $instance->item_id,
                'inventory_item_id' => $instance->id,
                'token' => $token,
                'uses_spent' => $uses,
                'uses_before' => $instance->remaining_uses,
                'uses_after' => $remaining,
            ]);

            $query = $owner->inventoryItems()->whereKey($instance->id)->where('remaining_uses', $instance->remaining_uses);
            $changed = $remaining === 0
                ? $query->delete()
                : $query->update(['remaining_uses' => $remaining]);

            if ($changed !== 1) {
                throw new ItemUnavailable('The item has changed. Retry the operation.');
            }

            return $receipt;
        }, attempts: 3);
    }
}
