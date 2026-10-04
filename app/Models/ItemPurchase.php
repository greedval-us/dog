<?php

namespace App\Models;

use Database\Factories\ItemPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $item_id
 * @property int $shop_offer_id
 * @property int|null $currency_transaction_id
 * @property int|null $shop_delivery_id
 * @property string $token
 * @property string $currency
 * @property int $price_paid
 * @property array{name: array<string, string>, quality: int, usage_limit: int, characteristics: array<string, mixed>} $item_snapshot
 */
#[Fillable(['user_id', 'item_id', 'shop_offer_id', 'shop_delivery_id', 'currency_transaction_id', 'token', 'currency', 'price_paid', 'item_snapshot'])]
class ItemPurchase extends Model
{
    /** @use HasFactory<ItemPurchaseFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<ShopOffer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ShopOffer::class, 'shop_offer_id');
    }

    /** @return BelongsTo<ShopDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ShopDelivery::class, 'shop_delivery_id');
    }

    /** @return HasOne<InventoryItem, $this> */
    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    /** @return BelongsTo<CurrencyTransaction, $this> */
    public function currencyTransaction(): BelongsTo
    {
        return $this->belongsTo(CurrencyTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'item_id' => 'integer',
            'shop_offer_id' => 'integer',
            'shop_delivery_id' => 'integer',
            'currency_transaction_id' => 'integer',
            'price_paid' => 'integer',
            'item_snapshot' => 'array',
        ];
    }
}
