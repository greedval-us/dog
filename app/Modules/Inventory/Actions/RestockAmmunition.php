<?php

namespace App\Modules\Inventory\Actions;

use App\Models\ShopDelivery;
use App\Models\ShopOffer;
use App\Modules\Inventory\Calculators\AmmunitionSupplyRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class RestockAmmunition
{
    public function __construct(private AmmunitionSupplyRules $supplies) {}

    public function handle(?CarbonImmutable $at = null): int
    {
        $at = ($at ?? CarbonImmutable::now())->utc()->startOfSecond();
        $delivered = 0;
        ShopOffer::query()->where('is_active', true)->whereNotNull('restock_interval_hours')
            ->where('next_restock_at', '<=', $at)
            ->whereHas('item', fn (Builder $query) => $query->where('is_active', true)
                ->whereHas('category', fn (Builder $category) => $category->where('code', 'ammunition')->where('is_active', true)))
            ->chunkById(100, function ($offers) use ($at, &$delivered): void {
                foreach ($offers as $offer) {
                    $delivered += (int) $this->restockOffer($offer->id, $at);
                }
            });

        return $delivered;
    }

    private function restockOffer(int $offerId, CarbonImmutable $at): bool
    {
        return DB::transaction(function () use ($offerId, $at): bool {
            $offer = ShopOffer::query()->with('item.category')->lockForUpdate()->findOrFail($offerId);
            if (! $offer->is_active || ! $offer->item->is_active || ! $offer->item->category->is_active
                || $offer->item->category->code !== 'ammunition' || $offer->next_restock_at === null
                || $offer->restock_interval_hours === null
                || $offer->next_restock_at->greaterThan($at) || ! $this->supplies->validInterval($offer->restock_interval_hours)
                || $offer->restock_target === null || $offer->stock === null) {
                return false;
            }

            $schedule = $this->supplies->schedule($at->getTimestamp(), $offer->next_restock_at->getTimestamp(), $offer->restock_interval_hours);
            $scheduled = CarbonImmutable::createFromTimestampUTC($schedule['current']);
            $delivery = ShopDelivery::query()->firstOrCreate([
                'shop_offer_id' => $offer->id, 'scheduled_at' => $scheduled,
            ], ['stock_before' => $offer->stock, 'stock_after' => $offer->restock_target]);

            $offer->update([
                'stock' => $delivery->wasRecentlyCreated ? $offer->restock_target : $offer->stock,
                'last_restock_at' => $scheduled, 'next_restock_at' => CarbonImmutable::createFromTimestampUTC($schedule['next']),
            ]);

            return $delivery->wasRecentlyCreated;
        }, attempts: 3);
    }
}
