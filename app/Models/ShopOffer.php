<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShopOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $item_id
 * @property string $currency
 * @property int $price
 * @property int|null $stock
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $restock_interval_hours
 * @property int|null $restock_target
 * @property int|null $purchase_limit
 * @property CarbonImmutable|null $next_restock_at
 * @property CarbonImmutable|null $last_restock_at
 */
#[Fillable(['item_id', 'currency', 'price', 'stock', 'is_active', 'sort_order', 'restock_interval_hours', 'restock_target', 'purchase_limit', 'next_restock_at', 'last_restock_at'])]
class ShopOffer extends Model
{
    /** @use HasFactory<ShopOfferFactory> */
    use HasFactory;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return HasMany<ItemPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(ItemPurchase::class);
    }

    /** @return HasMany<ShopDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ShopDelivery::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'restock_interval_hours' => 'integer',
            'restock_target' => 'integer',
            'purchase_limit' => 'integer',
            'next_restock_at' => 'immutable_datetime',
            'last_restock_at' => 'immutable_datetime',
        ];
    }
}
