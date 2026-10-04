<?php

namespace App\Modules\Inventory\Queries;

use App\Models\ShopDelivery;
use App\Models\ShopOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use InvalidArgumentException;

final class GetShopOffers
{
    /** @return CursorPaginator<int, ShopOffer> */
    public function handle(?int $categoryId = null, int $perPage = 24): CursorPaginator
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        return ShopOffer::query()
            ->addSelect(['shop_offers.*', 'current_delivery_id' => ShopDelivery::query()->select('id')
                ->whereColumn('shop_offer_id', 'shop_offers.id')->whereColumn('scheduled_at', 'shop_offers.last_restock_at')->limit(1)])
            ->with(['item.category', 'item.effectRules.statusEffect'])
            ->where('is_active', true)
            ->where('currency', 'coins')
            ->where(fn (Builder $query) => $query->whereNull('stock')->orWhere('stock', '>', 0)
                ->orWhereHas('item.category', fn (Builder $category) => $category->where('code', 'ammunition')))
            ->whereHas('item', function (Builder $query) use ($categoryId): void {
                $query->where('is_active', true)
                    ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));

                if ($categoryId !== null) {
                    $query->where('item_category_id', $categoryId);
                }
            })
            ->orderBy('sort_order')->orderBy('id')
            ->cursorPaginate($perPage);
    }
}
