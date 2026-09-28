<?php

namespace App\Modules\Inventory\Queries;

use App\Models\ItemCategory;
use App\Models\ShopOffer;
use App\Models\StatusEffect;
use App\Models\User;

final class GetShopCatalogue
{
    public function __construct(private GetShopOffers $offers) {}

    /**
     * @return array{
     *     categories: array<int, array{id: int, code: string, name: string}>,
     *     offers: array<int, array{id: int, itemId: int, name: string, description: string, category: string, categoryCode: string, bonuses: array<string, int>, grantedEffects: list<array<string, mixed>>, quality: int, usageLimit: int, characteristics: array<string, int|float|string|bool>, currency: string, price: int, stock: int|null, owned: int}>,
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

        $statusEffects = StatusEffect::query()->where('is_active', true)->get()->keyBy('code');

        return [
            'categories' => ItemCategory::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ItemCategory $category): array => CatalogueLabels::category($category, $locale))->all(),
            'offers' => $offers->getCollection()->map(function (ShopOffer $offer) use ($locale, $owned, $statusEffects): array {
                $item = $offer->item;
                $category = $item->category;

                return [
                    'id' => $offer->id,
                    'itemId' => $item->id,
                    'name' => CatalogueLabels::text($item->name, $locale, $item->code),
                    'description' => CatalogueLabels::text($item->description, $locale, ''),
                    'category' => CatalogueLabels::text($category->name, $locale, $category->code),
                    'categoryCode' => $category->code,
                    'quality' => $item->quality,
                    'usageLimit' => $item->usage_limit,
                    'characteristics' => $item->characteristics,
                    'bonuses' => $item->bonuses ?? [],
                    'grantedEffects' => array_values($statusEffects->filter(fn (StatusEffect $effect): bool => in_array($effect->code, $item->granted_effects ?? [], true))->map(fn (StatusEffect $effect): array => $effect->snapshot())->all()),
                    'currency' => $offer->currency,
                    'price' => $offer->price,
                    'stock' => $offer->stock,
                    'owned' => (int) ($owned[$item->id] ?? 0),
                ];
            })->all(),
            'nextCursor' => $offers->nextCursor()?->encode(),
            'previousCursor' => $offers->previousCursor()?->encode(),
            'inventoryCount' => $user->inventoryItems()->count(),
        ];
    }
}
