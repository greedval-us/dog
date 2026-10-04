<?php

namespace App\Modules\Inventory\Queries;

use App\Models\ItemCategory;
use App\Models\ShopOffer;
use App\Models\User;

/**
 * @phpstan-import-type ItemPresentation from CatalogueItemPresentation
 */
final class GetShopCatalogue
{
    public function __construct(private GetShopOffers $offers, private CatalogueItemPresentation $items) {}

    /**
     * @return array{
     *     categories: array<int, array{id: int, code: string, name: string}>,
     *     offers: array<int, ItemPresentation&array{id: int, itemId: int, description: string, currency: string, price: int, stock: int|null, owned: int, soldOut: bool, nextRestockAt: string|null, purchaseLimit: int|null, purchasedThisPeriod: int, ...}>,
     *     nextCursor: string|null, previousCursor: string|null, inventoryCount: int
     * }
     */
    public function handle(User $user, string $locale, ?int $categoryId = null): array
    {
        $offers = $this->offers->handle($categoryId, 12);
        $owned = $user->inventoryItems()
            ->whereIn('item_id', $offers->getCollection()->pluck('item_id'))
            ->selectRaw('item_id, COUNT(*) as quantity')
            ->groupBy('item_id')->pluck('quantity', 'item_id');
        $periodPurchases = $user->itemPurchases()->whereIn('shop_offer_id', $offers->getCollection()->pluck('id'))
            ->whereIn('shop_delivery_id', $offers->getCollection()->pluck('current_delivery_id')->filter())
            ->selectRaw('shop_delivery_id, COUNT(*) as quantity')->groupBy('shop_delivery_id')->pluck('quantity', 'shop_delivery_id');

        return [
            'categories' => ItemCategory::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ItemCategory $category): array => CatalogueLabels::category($category, $locale))->all(),
            'offers' => $offers->getCollection()->map(function (ShopOffer $offer) use ($locale, $owned, $periodPurchases): array {
                return [
                    ...$this->items->forTemplate($offer->item, $locale),
                    'id' => $offer->id,
                    'itemId' => $offer->item_id,
                    'currency' => $offer->currency,
                    'price' => $offer->price,
                    'stock' => $offer->stock,
                    'owned' => (int) ($owned[$offer->item_id] ?? 0),
                    'soldOut' => $offer->stock === 0,
                    'nextRestockAt' => $offer->next_restock_at?->toIso8601String(),
                    'purchaseLimit' => $offer->purchase_limit,
                    'purchasedThisPeriod' => (int) ($periodPurchases[$offer->getAttribute('current_delivery_id')] ?? 0),
                ];
            })->all(),
            'nextCursor' => $offers->nextCursor()?->encode(),
            'previousCursor' => $offers->previousCursor()?->encode(),
            'inventoryCount' => $user->inventoryItems()->count(),
        ];
    }
}
