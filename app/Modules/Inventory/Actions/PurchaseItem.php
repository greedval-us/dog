<?php

namespace App\Modules\Inventory\Actions;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemPurchase;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PurchaseItem
{
    public function __construct(private PlayerWallet $wallet) {}

    /** One token identifies one purchase of one instance, including after its destruction. */
    public function handle(User $user, PurchaseItemData $data): ItemPurchase
    {
        if ($data->offerId < 1 || $data->itemId < 1 || $data->expectedPrice < 1
            || $data->expectedCurrency !== 'coins' || ! Str::isUuid($data->token)) {
            throw new InvalidArgumentException('Invalid item purchase.');
        }

        $token = strtolower($data->token);

        return DB::transaction(function () use ($user, $data, $token): ItemPurchase {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new ItemUnavailable('This player cannot purchase items.');
            }

            $existing = $owner->itemPurchases()->where('token', $token)->first();

            if ($existing !== null) {
                if ($existing->shop_offer_id !== $data->offerId || $existing->item_id !== $data->itemId
                    || $existing->currency !== $data->expectedCurrency || $existing->price_paid !== $data->expectedPrice) {
                    throw new InvalidArgumentException('The token was already used for a different purchase.');
                }

                return $existing;
            }

            $offer = ShopOffer::query()->lockForUpdate()->findOrFail($data->offerId);
            $item = Item::query()->sharedLock()->findOrFail($offer->item_id);
            $category = ItemCategory::query()->sharedLock()->findOrFail($item->item_category_id);

            if ($offer->currency !== 'coins' || ! $offer->is_active || ! $item->is_active || ! $category->is_active || $offer->stock === 0) {
                throw new ItemUnavailable('This item is not available in the shop.');
            }

            if ($item->id !== $data->itemId || $offer->price !== $data->expectedPrice) {
                throw new ItemUnavailable('The offer has changed. Refresh the shop before purchasing.');
            }

            $entry = $this->wallet->change($owner, $offer->currency, -$offer->price, 'item-purchase:'.$token, 'item_purchase');

            if ($offer->stock !== null) {
                $changed = ShopOffer::query()->whereKey($offer->id)->where('stock', $offer->stock)->decrement('stock');

                if ($changed !== 1) {
                    throw new ItemUnavailable('This item is no longer in stock.');
                }
            }

            $snapshot = $item->inventorySnapshot();
            $purchase = $owner->itemPurchases()->create([
                'shop_offer_id' => $offer->id,
                'item_id' => $item->id,
                'currency_transaction_id' => $entry->id,
                'token' => $token,
                'currency' => $offer->currency,
                'price_paid' => $offer->price,
                'item_snapshot' => $snapshot,
            ]);

            $owner->inventoryItems()->create([
                ...$snapshot,
                'item_id' => $item->id,
                'item_purchase_id' => $purchase->id,
                'remaining_uses' => $item->usage_limit,
            ]);

            return $purchase;
        }, attempts: 3);
    }
}
