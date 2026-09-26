<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\Actions\UseInventoryItem;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Inventory\Queries\GetPlayerInventory;
use App\Modules\Inventory\Queries\GetShopOffers;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use Database\Seeders\ItemCategorySeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function itemPurchaseData(ShopOffer $offer, ?string $token = null): PurchaseItemData
{
    return new PurchaseItemData($offer->id, $offer->item_id, $offer->currency, $offer->price, $token ?? (string) Str::uuid());
}

test('purchases charge only coins and create independent item instances', function () {
    $user = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $item = Item::factory()->create(['quality' => 10, 'usage_limit' => 3, 'characteristics' => ['mood' => 12, 'size' => 'small']]);
    $offer = ShopOffer::factory()->for($item)->create(['currency' => 'coins', 'stock' => 2]);

    $purchase = app(PurchaseItem::class)->handle($user, itemPurchaseData($offer));
    $second = app(PurchaseItem::class)->handle($user, itemPurchaseData($offer));

    expect($purchase->inventoryItem->id)->not->toBe($second->inventoryItem->id);
    expect($purchase->inventoryItem->characteristics)->toBe(['mood' => 12, 'size' => 'small']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 50, 'gems' => 100]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 0]);
    $this->assertDatabaseHas('inventory_items', ['item_purchase_id' => $purchase->id, 'quality' => 10, 'usage_limit' => 3, 'remaining_uses' => 3]);
    $this->assertDatabaseHas('currency_transactions', ['id' => $purchase->currency_transaction_id, 'currency' => 'coins', 'amount' => -25]);
    $this->assertDatabaseCount('inventory_items', 2);
});

test('purchase retries survive catalogue changes and item destruction without charging or granting again', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['stock' => 1]);
    $data = itemPurchaseData($offer);
    $purchase = app(PurchaseItem::class)->handle($user, $data);
    $instance = $purchase->inventoryItem;
    app(UseInventoryItem::class)->handle($user, $instance->id, (string) Str::uuid(), 5);
    $offer->update(['is_active' => false, 'price' => 99]);

    $repeat = app(PurchaseItem::class)->handle($user, new PurchaseItemData($data->offerId, $data->itemId, $data->expectedCurrency, $data->expectedPrice, strtoupper($data->token)));

    expect($repeat->id)->toBe($purchase->id);
    expect($repeat->inventoryItem)->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 75]);
    $this->assertDatabaseCount('item_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('inventory_items', 0);
});

test('a purchase token cannot be reused with changed contents', function (string $field) {
    $user = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $offer = ShopOffer::factory()->create();
    $data = itemPurchaseData($offer);
    app(PurchaseItem::class)->handle($user, $data);
    $changed = new PurchaseItemData(
        $field === 'offer' ? $data->offerId + 1 : $data->offerId,
        $field === 'item' ? $data->itemId + 1 : $data->itemId,
        $field === 'currency' ? 'gems' : $data->expectedCurrency,
        $field === 'price' ? 26 : $data->expectedPrice,
        $data->token,
    );

    expect(fn () => app(PurchaseItem::class)->handle($user, $changed))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('item_purchases', 1);
    $this->assertDatabaseCount('inventory_items', 1);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 75, 'gems' => 100]);
})->with(['offer', 'item', 'currency', 'price']);

test('insufficient funds roll back stock and inventory', function () {
    $user = User::factory()->create(['coins' => 24]);
    $offer = ShopOffer::factory()->create(['stock' => 1]);

    expect(fn () => app(PurchaseItem::class)->handle($user, itemPurchaseData($offer)))->toThrow(InsufficientFunds::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 24]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 1]);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('malformed purchase arguments cannot charge a player', function (int $offerId, int $itemId, string $currency, int $price, string $token) {
    $user = User::factory()->create(['coins' => 100]);
    $data = new PurchaseItemData($offerId, $itemId, $currency, $price, $token);

    expect(fn () => app(PurchaseItem::class)->handle($user, $data))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('inventory_items', 0);
})->with([
    'offer' => [0, 1, 'coins', 25, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'item' => [1, 0, 'coins', 25, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'gems' => [1, 1, 'gems', 25, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'currency' => [1, 1, 'experience', 25, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'price' => [1, 1, 'coins', 0, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'token' => [1, 1, 'coins', 25, 'invalid'],
]);

test('unavailable or changed offers cannot charge the player', function (string $change) {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['stock' => 1]);
    $data = itemPurchaseData($offer);

    match ($change) {
        'offer' => $offer->update(['is_active' => false]),
        'item' => $offer->item->update(['is_active' => false]),
        'category' => $offer->item->category->update(['is_active' => false]),
        'stock' => $offer->update(['stock' => 0]),
        'price' => $offer->update(['price' => 26]),
        'currency' => $offer->update(['currency' => 'gems']),
        'replacement' => $offer->update(['item_id' => Item::factory()->create()->id]),
        'blocked' => $user->forceFill(['status' => PlayerStatus::Blocked])->save(),
    };

    expect(fn () => app(PurchaseItem::class)->handle($user, $data))->toThrow(ItemUnavailable::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['offer', 'item', 'category', 'stock', 'price', 'currency', 'replacement', 'blocked']);

test('the last unit cannot be sold to another buyer', function () {
    $first = User::factory()->create(['coins' => 100]);
    $second = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['stock' => 1]);
    app(PurchaseItem::class)->handle($first, itemPurchaseData($offer));

    expect(fn () => app(PurchaseItem::class)->handle($second, itemPurchaseData($offer)))->toThrow(ItemUnavailable::class);

    $this->assertDatabaseHas('users', ['id' => $second->id, 'coins' => 100]);
    $this->assertDatabaseCount('item_purchases', 1);
});

test('purchase tokens belong to individual players and unlimited stock stays unlimited', function () {
    $players = User::factory()->count(2)->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create();
    $data = itemPurchaseData($offer);

    foreach ($players as $player) {
        app(PurchaseItem::class)->handle($player, $data);
    }

    $this->assertDatabaseCount('item_purchases', 2);
    $this->assertDatabaseCount('inventory_items', 2);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => null]);
});

test('failed inventory issuance rolls back the payment purchase and stock', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['stock' => 1]);
    InventoryItem::creating(function (): void {
        throw new RuntimeException('Inventory unavailable.');
    });

    try {
        expect(fn () => app(PurchaseItem::class)->handle($user, itemPurchaseData($offer)))
            ->toThrow(RuntimeException::class, 'Inventory unavailable.');
    } finally {
        InventoryItem::flushEventListeners();
    }

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 1]);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('inventory_items', 0);
});

test('owned characteristics quality and durability survive catalogue edits and deactivation', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create();
    $purchase = app(PurchaseItem::class)->handle($user, itemPurchaseData($offer));
    $offer->item->update(['name' => ['ru' => 'Новый мяч'], 'quality' => 9, 'usage_limit' => 1, 'characteristics' => ['mood' => 99], 'is_active' => false]);

    $receipt = app(UseInventoryItem::class)->handle($user, $purchase->inventoryItem->id, (string) Str::uuid());
    $instance = $purchase->inventoryItem->fresh();

    expect($instance->quality)->toBe(3);
    expect($instance->usage_limit)->toBe(5);
    expect($instance->name)->toBe(['ru' => 'Мяч', 'en' => 'Ball']);
    expect($instance->characteristics)->toBe(['mood' => 10, 'size' => 'medium']);
    expect($receipt->uses_after)->toBe(4);
    expect($purchase->fresh()->item_snapshot['quality'])->toBe(3);
});

test('usage decrements just the chosen instance and retries do not spend more uses', function () {
    $user = User::factory()->create();
    $instances = InventoryItem::factory()->for($user)->count(2)->create();
    $token = (string) Str::uuid();

    $receipt = app(UseInventoryItem::class)->handle($user, $instances[0]->id, $token, 2);
    $repeat = app(UseInventoryItem::class)->handle($user, $instances[0]->id, strtoupper($token), 2);

    expect($receipt->wasRecentlyCreated)->toBeTrue();
    expect($repeat->wasRecentlyCreated)->toBeFalse();
    expect($repeat->id)->toBe($receipt->id);
    $this->assertDatabaseHas('inventory_items', ['id' => $instances[0]->id, 'remaining_uses' => 3]);
    $this->assertDatabaseHas('inventory_items', ['id' => $instances[1]->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('item_usages', 1);
});

test('the last use destroys the instance and a repeated request retains its original receipt', function () {
    $instance = InventoryItem::factory()->create(['remaining_uses' => 1]);
    $token = (string) Str::uuid();

    $receipt = app(UseInventoryItem::class)->handle($instance->user, $instance->id, $token);
    $repeat = app(UseInventoryItem::class)->handle($instance->user, $instance->id, $token);

    expect($repeat->id)->toBe($receipt->id);
    $this->assertModelMissing($instance);
    $this->assertDatabaseHas('item_usages', ['id' => $receipt->id, 'inventory_item_id' => $instance->id, 'uses_after' => 0]);
    expect(fn () => app(UseInventoryItem::class)->handle($instance->user, $instance->id, (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

test('usage tokens cannot be reused for another instance or amount', function (string $change) {
    $instance = InventoryItem::factory()->create();
    $token = (string) Str::uuid();
    app(UseInventoryItem::class)->handle($instance->user, $instance->id, $token);

    expect(fn () => app(UseInventoryItem::class)->handle($instance->user, $change === 'instance' ? $instance->id + 1 : $instance->id, $token, $change === 'amount' ? 2 : 1))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 4]);
    $this->assertDatabaseCount('item_usages', 1);
})->with(['instance', 'amount']);

test('players cannot use another players item', function () {
    $instance = InventoryItem::factory()->create();
    $stranger = User::factory()->create();

    expect(fn () => app(UseInventoryItem::class)->handle($stranger, $instance->id, (string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('item_usages', 0);
});

test('blocked players cannot consume their items', function () {
    $user = User::factory()->create(['status' => PlayerStatus::Blocked]);
    $instance = InventoryItem::factory()->for($user)->create();

    expect(fn () => app(UseInventoryItem::class)->handle($user, $instance->id, (string) Str::uuid()))
        ->toThrow(ItemUnavailable::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('item_usages', 0);
});

test('overspending durability does not consume or destroy the item', function () {
    $instance = InventoryItem::factory()->create(['remaining_uses' => 2]);

    expect(fn () => app(UseInventoryItem::class)->handle($instance->user, $instance->id, (string) Str::uuid(), 3))
        ->toThrow(ItemUnavailable::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 2]);
    $this->assertDatabaseCount('item_usages', 0);
});

test('failed gameplay effects roll back item usage including destruction', function () {
    $instance = InventoryItem::factory()->create(['remaining_uses' => 1]);

    expect(fn () => DB::transaction(function () use ($instance): void {
        app(UseInventoryItem::class)->handle($instance->user, $instance->id, (string) Str::uuid());
        throw new RuntimeException('Effect failed.');
    }))->toThrow(RuntimeException::class, 'Effect failed.');

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 1]);
    $this->assertDatabaseCount('item_usages', 0);
});

test('invalid usage arguments cannot mutate inventory', function (int $uses, string $token) {
    $instance = InventoryItem::factory()->create();

    expect(fn () => app(UseInventoryItem::class)->handle($instance->user, $instance->id, $token, $uses))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $instance->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('item_usages', 0);
})->with([
    'zero' => [0, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'negative' => [-1, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'overflow' => [2147483648, '6b639cae-3484-4e11-8ce8-873c547211bc'],
    'invalid token' => [1, 'invalid'],
]);

test('database rejects invalid quality or usage limits even when models are bypassed', function (string $table, array $attributes) {
    $item = Item::factory()->create();
    $instance = InventoryItem::factory()->for($item)->create();
    $id = $table === 'items' ? $item->id : $instance->id;

    expect(fn () => DB::transaction(fn () => DB::table($table)->where('id', $id)->update($attributes)))
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas($table, ['id' => $id, 'quality' => 3, 'usage_limit' => 5]);
})->with([
    'quality zero' => ['items', ['quality' => 0]],
    'quality eleven' => ['items', ['quality' => 11]],
    'zero limit' => ['items', ['usage_limit' => 0]],
    'instance quality' => ['inventory_items', ['quality' => 11]],
    'empty instance' => ['inventory_items', ['remaining_uses' => 0]],
    'overfilled instance' => ['inventory_items', ['remaining_uses' => 6]],
]);

test('database rejects invalid shop prices currencies and stock', function (array $attributes) {
    expect(fn () => DB::transaction(fn () => ShopOffer::factory()->create($attributes)))->toThrow(QueryException::class);

    $this->assertDatabaseCount('shop_offers', 0);
})->with([
    'zero price' => [['price' => 0]],
    'negative price' => [['price' => -1]],
    'currency' => [['currency' => 'experience']],
    'stock' => [['stock' => -1]],
]);

test('new categories and characteristics do not require schema changes and seeding preserves edits', function () {
    $this->seed(ItemCategorySeeder::class);
    ItemCategory::query()->where('code', 'food')->update(['name' => ['ru' => 'Корма'], 'is_active' => false]);
    $this->seed(ItemCategorySeeder::class);
    $category = ItemCategory::factory()->create(['code' => 'medicine']);
    $item = Item::factory()->for($category, 'category')->create(['characteristics' => ['healing' => 7.5, 'waterproof' => true]]);
    $offer = ShopOffer::factory()->for($item)->create();

    expect(app(GetShopOffers::class)->handle($category->id)->items()[0]->id)->toBe($offer->id);
    expect($item->fresh()->characteristics)->toBe(['healing' => 7.5, 'waterproof' => true]);
    expect(ItemCategory::query()->where('code', 'food')->first()->name)->toBe(['ru' => 'Корма']);
    $this->assertDatabaseCount('item_categories', 8);
});

test('the shop hides disabled and sold out offers and filters by category with bounded eager loaded results', function () {
    $category = ItemCategory::factory()->create();
    $item = Item::factory()->for($category, 'category')->create();
    $visible = ShopOffer::factory()->for($item)->create(['sort_order' => 1]);
    ShopOffer::factory()->for($item)->create(['sort_order' => 2]);
    ShopOffer::factory()->for($item)->create(['is_active' => false]);
    ShopOffer::factory()->for($item)->create(['stock' => 0]);
    ShopOffer::factory()->create();
    ShopOffer::factory()->for(Item::factory()->for($category, 'category')->state(['is_active' => false]))->create();
    ShopOffer::factory()->for(Item::factory()->for(ItemCategory::factory()->state(['is_active' => false]), 'category'))->create();

    $page = app(GetShopOffers::class)->handle($category->id, 1);

    expect($page->items())->toHaveCount(1);
    expect($page->hasMorePages())->toBeTrue();
    expect($page->items()[0]->id)->toBe($visible->id);
    expect($page->items()[0]->relationLoaded('item'))->toBeTrue();
    expect($page->items()[0]->item->relationLoaded('category'))->toBeTrue();
    expect(app(GetShopOffers::class)->handle()->items())->toHaveCount(3);
});

test('inventory queries return only owned instances including delisted items', function () {
    $user = User::factory()->create();
    $owned = InventoryItem::factory()->for($user)->create();
    $owned->item->update(['is_active' => false]);
    InventoryItem::factory()->for($user)->create();
    InventoryItem::factory()->for($owned->item)->create();

    $page = app(GetPlayerInventory::class)->handle($user, $owned->item_id);

    expect($page->items())->toHaveCount(1);
    expect($page->items()[0]->id)->toBe($owned->id);
    expect($page->items()[0]->relationLoaded('item'))->toBeTrue();
});

test('catalogue and inventory queries reject unbounded page sizes', function (int $perPage) {
    $user = User::factory()->create();

    expect(fn () => app(GetShopOffers::class)->handle(perPage: $perPage))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(GetPlayerInventory::class)->handle($user, perPage: $perPage))->toThrow(InvalidArgumentException::class);
})->with([0, 101]);

test('one star single use items can be purchased and consumed', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->for(Item::factory()->state(['quality' => 1, 'usage_limit' => 1]))->create();
    $purchase = app(PurchaseItem::class)->handle($user, itemPurchaseData($offer));
    $instance = $purchase->inventoryItem;

    app(UseInventoryItem::class)->handle($user, $instance->id, (string) Str::uuid());

    expect($purchase->item_snapshot['quality'])->toBe(1);
    $this->assertModelMissing($instance);
});

test('deleting a player removes inventory while preserving purchase and usage history', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create();
    $purchase = app(PurchaseItem::class)->handle($user, itemPurchaseData($offer));
    $receipt = app(UseInventoryItem::class)->handle($user, $purchase->inventoryItem->id, (string) Str::uuid());

    $user->delete();

    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseHas('item_purchases', ['id' => $purchase->id, 'user_id' => null, 'price_paid' => 25]);
    $this->assertDatabaseHas('item_usages', ['id' => $receipt->id, 'user_id' => null, 'uses_after' => 4]);
});
