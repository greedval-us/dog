<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShopDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_offer_id
 * @property CarbonImmutable $scheduled_at
 * @property int $stock_before
 * @property int $stock_after
 */
#[Fillable(['shop_offer_id', 'scheduled_at', 'stock_before', 'stock_after'])]
class ShopDelivery extends Model
{
    /** @use HasFactory<ShopDeliveryFactory> */
    use HasFactory;

    /** @return BelongsTo<ShopOffer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ShopOffer::class, 'shop_offer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['shop_offer_id' => 'integer', 'scheduled_at' => 'immutable_datetime', 'stock_before' => 'integer', 'stock_after' => 'integer'];
    }
}
