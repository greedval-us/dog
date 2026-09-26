<?php

namespace App\Modules\Inventory\Queries;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use InvalidArgumentException;

final class GetPlayerInventory
{
    /** @return CursorPaginator<int, InventoryItem> */
    public function handle(User $user, ?int $itemId = null, int $perPage = 24): CursorPaginator
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        $query = $user->inventoryItems()->with('item.category');

        if ($itemId !== null) {
            $query->where('item_id', $itemId);
        }

        return $query->orderBy('id')->cursorPaginate($perPage);
    }
}
