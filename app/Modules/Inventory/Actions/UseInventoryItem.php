<?php

namespace App\Modules\Inventory\Actions;

use App\Models\ItemUsage;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;

final class UseInventoryItem
{
    public function __construct(private InventoryConsumption $consumption) {}

    /** Apply effects in the caller's transaction only for a newly created receipt. */
    public function handle(User $user, int $inventoryItemId, string $token, int $uses = 1): ItemUsage
    {
        return $this->consumption->handle($user, $inventoryItemId, $token, $uses);
    }
}
