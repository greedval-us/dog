<?php

namespace App\Modules\Inventory\Queries;

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
            ->with('item.category')
            ->where('is_active', true)
            ->where('currency', 'coins')
            ->where(fn (Builder $query) => $query->whereNull('stock')->orWhere('stock', '>', 0))
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
