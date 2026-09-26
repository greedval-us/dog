<?php

namespace App\Models;

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
 */
#[Fillable(['item_id', 'currency', 'price', 'stock', 'is_active', 'sort_order'])]
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
