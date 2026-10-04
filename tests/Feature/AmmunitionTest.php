<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\Actions\RestockAmmunition;
use App\Modules\Inventory\Actions\UseInventoryItem;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Inventory\Queries\GetInventoryCatalogue;
use App\Modules\Inventory\Queries\GetShopCatalogue;
use App\Modules\Players\Exceptions\InsufficientFunds;
use Carbon\CarbonImmutable;
use Database\Seeders\AmmunitionSeeder;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Support\Str;

function ammunitionPurchase(ShopOffer $offer, ?string $token = null): PurchaseItemData
{
    return new PurchaseItemData($offer->id, $offer->item_id, 'coins', $offer->price, $token ?? (string) Str::uuid());
}

test('ammunition catalogue seeds finite usable variants without overriding shop changes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    $offer->update(['stock' => 3, 'price' => 123, 'is_active' => false]);
    $showItem = Item::query()->where('code', 'show_nylon_lead')->firstOrFail();
    $legacyCharacteristics = $showItem->characteristics;
    $legacyCharacteristics['competition']['disciplines'] = ['conformation', 'progeny'];
    $legacyCharacteristics['competition']['modifiers']['precision'] = 0.06;
    $showItem->update(['characteristics' => $legacyCharacteristics]);

    $this->seed(AmmunitionSeeder::class);

    $this->assertDatabaseCount('items', 36);
    $this->assertDatabaseCount('shop_offers', 36);
    $this->assertDatabaseCount('shop_deliveries', 36);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 3, 'price' => 123, 'is_active' => false]);
    expect(ShopOffer::query()->whereNull('stock')->exists())->toBeFalse();
    expect(Item::query()->get()->every(fn (Item $item): bool => (new CompetitionAmmunitionRules)->metadata($item->characteristics) !== null))->toBeTrue();
    $showMetadata = $showItem->refresh()->characteristics['competition'];
    expect($showMetadata['disciplines'])->toBe(['conformation']);
    expect($showMetadata['modifiers']['precision'])->toBe(0.06);
    expect((new CompetitionAmmunitionRules)->supports($showMetadata, 'conformation', 'medium', 'performance'))->toBeTrue();
    expect((new CompetitionAmmunitionRules)->supports($showMetadata, 'progeny', 'medium', 'performance'))->toBeFalse();
});

test('sold out ammunition remains visible with delivery and purchase limits while ordinary items disappear', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed([ShopItemSeeder::class, AmmunitionSeeder::class]);
    $player = User::factory()->create();
    $category = ItemCategory::query()->where('code', 'ammunition')->firstOrFail();
    $offer = ShopOffer::query()->whereHas('item', fn ($query) => $query->where('code', 'agility_light_collar'))->firstOrFail();
    $offer->update(['stock' => 0]);
    ShopOffer::query()->whereHas('item.category', fn ($query) => $query->where('code', 'food'))->update(['stock' => 0]);

    $catalogue = app(GetShopCatalogue::class)->handle($player, 'ru', $category->id);

    expect($catalogue['offers'][0]['id'])->toBe($offer->id);
    expect($catalogue['offers'][0]['soldOut'])->toBeTrue();
    expect($catalogue['offers'][0]['nextRestockAt'])->toBe('2026-10-05T09:00:00+00:00');
    expect($catalogue['offers'][0]['purchaseLimit'])->toBe(2);
    expect($catalogue['offers'][0]['competition']['phase'])->toBe('preparation');
    $food = ItemCategory::query()->where('code', 'food')->firstOrFail();
    expect(app(GetShopCatalogue::class)->handle($player, 'ru', $food->id)['offers'])->toBe([]);
});

test('ammunition purchase cap is durable and isolated per player and delivery', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $player = User::factory()->create(['coins' => 1000]);
    $other = User::factory()->create(['coins' => 1000]);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer));
    app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer));
    app(PurchaseItem::class)->handle($other, ammunitionPurchase($offer));

    expect(fn () => app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer)))
        ->toThrow(ItemUnavailable::class, 'The ammunition purchase limit for this delivery has been reached.');

    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 820]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 15]);
    $this->assertDatabaseCount('item_purchases', 3);
    $this->assertDatabaseCount('currency_transactions', 3);
    $catalogue = app(GetShopCatalogue::class)->handle($player, 'ru', $offer->item->item_category_id);
    expect($catalogue['offers'][0]['purchasedThisPeriod'])->toBe(2);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00', 'Europe/Moscow'));
    app(RestockAmmunition::class)->handle();
    app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer));
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 730]);
    expect(app(GetShopCatalogue::class)->handle($player, 'ru', $offer->item->item_category_id)['offers'][0]['purchasedThisPeriod'])->toBe(1);
});

test('equipment snapshots and purchase retries survive catalogue changes and destruction', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $player = User::factory()->create(['coins' => 1000]);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    $data = ammunitionPurchase($offer);
    $purchase = app(PurchaseItem::class)->handle($player, $data);
    $item = $offer->item;
    $changed = $item->characteristics;
    $changed['competition']['modifiers']['pace'] = 0.09;
    $item->update(['characteristics' => $changed]);

    expect($purchase->inventoryItem->characteristics['competition']['modifiers']['pace'])->toBe(0.04);
    expect(app(GetInventoryCatalogue::class)->handle($player, 'ru', $item->item_category_id)['items'][0]['competition']['modifiers']['pace'])->toBe(0.04);
    app(UseInventoryItem::class)->handle($player, $purchase->inventoryItem->id, (string) Str::uuid(), 40);
    $repeat = app(PurchaseItem::class)->handle($player, $data);

    expect($repeat->id)->toBe($purchase->id);
    expect($repeat->item_snapshot['characteristics']['competition']['modifiers']['pace'])->toBe(0.04);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 910]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 17]);
});

test('missed restocks catch up to the current target once without accumulated stock', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    $offer->update(['stock' => 1]);
    ShopOffer::query()->whereKeyNot($offer->id)->update(['is_active' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 13:00:00', 'Europe/Moscow'));

    expect(app(RestockAmmunition::class)->handle())->toBe(1);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 18, 'last_restock_at' => '2026-10-07 09:00:00', 'next_restock_at' => '2026-10-07 15:00:00']);
    $this->assertDatabaseCount('shop_deliveries', 37);
    $offer->refresh()->update(['stock' => 2]);
    expect(app(RestockAmmunition::class)->handle())->toBe(0);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 2]);
    $this->assertDatabaseCount('shop_deliveries', 37);
});

test('specialized equipment remains capped until its weekly delivery', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $offer = ShopOffer::query()->whereHas('item', fn ($query) => $query->where('code', 'agility_soft_grip'))->firstOrFail();
    $player = User::factory()->create(['coins' => 1000]);
    app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer));
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00', 'Europe/Moscow'));
    app(RestockAmmunition::class)->handle();

    expect(fn () => app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer)))->toThrow(ItemUnavailable::class);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 5]);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 00:00:00', 'Europe/Moscow'));
    $this->artisan('shop:restock-ammunition')->assertSuccessful();
    app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer));
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 5]);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 700]);
    $this->assertDatabaseCount('item_purchases', 2);
});

test('failed ammunition payments do not consume stock or the player delivery allowance', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    $player = User::factory()->create(['coins' => 0]);

    expect(fn () => app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer)))->toThrow(InsufficientFunds::class);

    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 18]);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 0]);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('invalid ammunition or a missing initial delivery cannot bypass stock and purchase caps', function (string $failure) {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Moscow'));
    $this->seed(AmmunitionSeeder::class);
    $offer = ShopOffer::query()->orderBy('sort_order')->firstOrFail();
    $player = User::factory()->create(['coins' => 1000]);
    if ($failure === 'metadata') {
        $offer->item->update(['characteristics' => ['competition' => ['modifiers' => ['pace' => 99]]]]);
    } else {
        $offer->deliveries()->delete();
    }

    expect(fn () => app(PurchaseItem::class)->handle($player, ammunitionPurchase($offer)))
        ->toThrow(ItemUnavailable::class, 'This item is not available in the shop.');

    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 18]);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 1000]);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['invalid modifiers' => 'metadata', 'no initial delivery' => 'delivery']);
