<?php

namespace App\Modules\Inventory\Queries;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-import-type ItemPresentation from CatalogueItemPresentation
 */
final class GetInventoryCatalogue
{
    public function __construct(private GetPlayerInventory $inventory, private CatalogueItemPresentation $items) {}

    /**
     * @return array{
     *     categories: array<int, array{id: int, code: string, name: string}>,
     *     items: array<int, ItemPresentation&array{id: int, remainingUses: int, acquiredAt: string|null, ...}>,
     *     inventoryCount: int, itemTypesCount: int, nextCursor: string|null, previousCursor: string|null
     * }
     */
    public function handle(User $user, string $locale, ?int $categoryId = null): array
    {
        $items = $this->inventory->handle($user, perPage: 12, categoryId: $categoryId);

        return [
            'categories' => ItemCategory::query()
                ->whereHas('items.inventoryItems', fn (Builder $query) => $query->where('user_id', $user->id))
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ItemCategory $category): array => CatalogueLabels::category($category, $locale))->all(),
            'items' => $items->getCollection()->map(function (InventoryItem $instance) use ($locale): array {
                return [
                    ...$this->items->forInstance($instance, $locale),
                    'id' => $instance->id,
                    'remainingUses' => $instance->remaining_uses,
                    'acquiredAt' => $instance->created_at?->toDateString(),
                ];
            })->all(),
            'inventoryCount' => $user->inventoryItems()->count(),
            'itemTypesCount' => $user->inventoryItems()->distinct()->count('item_id'),
            'nextCursor' => $items->nextCursor()?->encode(),
            'previousCursor' => $items->previousCursor()?->encode(),
        ];
    }
}
