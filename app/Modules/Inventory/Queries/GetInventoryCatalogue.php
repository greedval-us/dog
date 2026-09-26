<?php

namespace App\Modules\Inventory\Queries;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class GetInventoryCatalogue
{
    public function __construct(private GetPlayerInventory $inventory) {}

    /**
     * @return array{
     *     categories: array<int, array{id: int, code: string, name: string}>,
     *     items: array<int, array{id: int, name: string, category: string, categoryCode: string, quality: int, usageLimit: int, remainingUses: int, characteristics: array<string, int|float|string|bool>, acquiredAt: string|null}>,
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
                $category = $instance->item->category;

                return [
                    'id' => $instance->id,
                    'name' => CatalogueLabels::text($instance->name, $locale, $instance->item->code),
                    'category' => CatalogueLabels::text($category->name, $locale, $category->code),
                    'categoryCode' => $category->code,
                    'quality' => $instance->quality,
                    'usageLimit' => $instance->usage_limit,
                    'remainingUses' => $instance->remaining_uses,
                    'characteristics' => $instance->characteristics,
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
