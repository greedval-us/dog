<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemPurchase;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/** @return array{offer_id: int, item_id: int, expected_currency: string, expected_price: int, purchase_token: string} */
function shopPurchasePayload(ShopOffer $offer): array
{
    return [
        'offer_id' => $offer->id,
        'item_id' => $offer->item_id,
        'expected_currency' => $offer->currency,
        'expected_price' => $offer->price,
        'purchase_token' => (string) Str::uuid(),
    ];
}

test('guests cannot browse or purchase shop items', function () {
    $this->get(route('shop.index'))->assertRedirect(route('login'));
    $this->post(route('shop.store'))->assertRedirect(route('login'));
    $this->assertDatabaseCount('item_purchases', 0);
});

test('the shop localizes items and only counts inventory belonging to its player', function (string $locale, string $name) {
    $user = User::factory()->create(['locale' => $locale]);
    $offer = ShopOffer::factory()->create();
    InventoryItem::factory()->for($user)->for($offer->item)->count(2)->create();
    InventoryItem::factory()->for($offer->item)->create();

    $this->actingAs($user)->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Shop')
        ->has('categories', 1)
        ->has('offers', 1)
        ->where('offers.0.name', $name)
        ->where('offers.0.owned', 2)
        ->where('offers.0.price', 25)
        ->where('offers.0.quality', 3)
        ->where('offers.0.usageLimit', 5)
        ->where('offers.0.description', '')
        ->where('inventoryCount', 2)
        ->where('purchaseToken', fn (string $token): bool => Str::isUuid($token))
    );
})->with([['ru', 'Мяч'], ['en', 'Ball']]);

test('category filters and cursor pages keep catalogue results isolated', function () {
    $user = User::factory()->create();
    $category = ItemCategory::factory()->create();
    $item = Item::factory()->for($category, 'category')->create();
    $offers = ShopOffer::factory()->for($item)->count(13)->create();
    ShopOffer::factory()->create();

    $first = $this->actingAs($user)->get(route('shop.index', ['category' => $category->id]));
    $first->assertInertia(fn (Assert $page) => $page
        ->has('offers', 12)
        ->where('selectedCategory', $category->id)
        ->where('offers.0.id', $offers[0]->id)
        ->where('previousCursor', null)
    );
    $this->get(route('shop.index', ['category' => $category->id, 'cursor' => $first->inertiaProps('nextCursor')]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)->where('offers.0.id', $offers[12]->id)->where('nextCursor', null)
        );
});

test('the shop hides unavailable offers and supports empty categories', function () {
    $user = User::factory()->create();
    $category = ItemCategory::factory()->create();
    $item = Item::factory()->for($category, 'category')->create();
    ShopOffer::factory()->for($item)->create(['stock' => 0]);
    ShopOffer::factory()->create(['is_active' => false]);
    ShopOffer::factory()->for(Item::factory()->create(['is_active' => false]))->create();
    $inactiveCategory = ItemCategory::factory()->create(['is_active' => false]);
    ShopOffer::factory()->for(Item::factory()->for($inactiveCategory, 'category'))->create();

    $this->actingAs($user)->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page
        ->has('offers', 0)->where('nextCursor', null)
        ->where('categories', fn ($categories): bool => ! collect($categories)->contains('id', $inactiveCategory->id))
    );
});

test('shop purchases use the signed in player and repeated requests only charge once', function () {
    $user = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $other = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $offer = ShopOffer::factory()->create(['currency' => 'coins', 'stock' => 2]);
    $payload = [...shopPurchasePayload($offer), 'user_id' => $other->id];

    $this->actingAs($user)->from(route('shop.index'))->post(route('shop.store'), $payload)
        ->assertRedirect(route('shop.index'))->assertSessionHasNoErrors();
    $this->post(route('shop.store'), $payload)->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 75, 'gems' => 100]);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'coins' => 100, 'gems' => 100]);
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'stock' => 1]);
    $this->assertDatabaseHas('inventory_items', ['user_id' => $user->id, 'item_id' => $offer->item_id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('inventory_items', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('failed purchases return a readable error and leave the wallet unchanged', function (string $failure, string $message) {
    $user = User::factory()->create(['coins' => $failure === 'funds' ? 0 : 100, 'locale' => 'ru']);
    $offer = ShopOffer::factory()->create(['stock' => 1]);
    $payload = shopPurchasePayload($offer);
    match ($failure) {
        'stock' => $offer->update(['stock' => 0]),
        'price' => $offer->update(['price' => 26]),
        'blocked' => $user->forceFill(['status' => PlayerStatus::Blocked])->save(),
        default => null,
    };

    $this->actingAs($user)->from(route('shop.index'))->post(route('shop.store'), $payload)
        ->assertRedirect(route('shop.index'))->assertSessionHasErrors(['purchase' => $message]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => $failure === 'funds' ? 0 : 100]);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'funds' => ['funds', 'Не хватает монет для покупки предмета.'],
    'stock' => ['stock', 'Этот предмет сейчас недоступен в магазине.'],
    'price' => ['price', 'Предложение изменилось. Обнови магазин перед покупкой.'],
    'blocked' => ['blocked', 'Сейчас ты не можешь покупать предметы.'],
]);

test('a purchase token cannot be reused for a different purchase through HTTP', function () {
    $user = User::factory()->create(['coins' => 100, 'locale' => 'ru']);
    $offer = ShopOffer::factory()->create();
    $payload = shopPurchasePayload($offer);
    $this->actingAs($user)->post(route('shop.store'), $payload)->assertSessionHasNoErrors();

    $this->post(route('shop.store'), [...$payload, 'expected_price' => 30])
        ->assertSessionHasErrors(['purchase' => 'Обнови магазин перед следующей покупкой.']);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 75]);
    $this->assertDatabaseCount('inventory_items', 1);
});

test('invalid purchase input cannot create an item or debit the wallet', function (string $field, mixed $value) {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create();

    $this->actingAs($user)->post(route('shop.store'), [...shopPurchasePayload($offer), $field => $value])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'missing offer' => ['offer_id', null],
    'unknown offer' => ['offer_id', 999999],
    'unknown item' => ['item_id', 999999],
    'invalid currency' => ['expected_currency', 'experience'],
    'gems' => ['expected_currency', 'gems'],
    'zero price' => ['expected_price', 0],
    'invalid token' => ['purchase_token', 'not-a-uuid'],
]);

test('catalogue seeding fills each category and preserves existing changes on reruns', function () {
    $this->seed(ShopItemSeeder::class);
    $offer = ShopOffer::query()->firstOrFail();
    $offer->update(['price' => 123, 'stock' => 2, 'is_active' => false]);
    $offer->item->update(['quality' => 9]);

    $this->seed(ShopItemSeeder::class);

    $this->assertDatabaseCount('items', 21);
    $this->assertDatabaseCount('shop_offers', 21);
    expect(ShopOffer::query()->where('currency', '!=', 'coins')->exists())->toBeFalse();
    expect(ItemCategory::query()->withCount('items')->get()->pluck('items_count')->all())->toBe(array_fill(0, 7, 3));
    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'price' => 123, 'stock' => 2, 'is_active' => false]);
    $this->assertDatabaseHas('items', ['id' => $offer->item_id, 'quality' => 9]);
});

test('gem offers are hidden and cannot be purchased by spoofing their currency', function () {
    $player = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $offer = ShopOffer::factory()->create(['currency' => 'gems']);

    $this->actingAs($player)->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
    $this->post(route('shop.store'), [...shopPurchasePayload($offer), 'expected_currency' => 'coins'])
        ->assertSessionHasErrors('purchase');

    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 100, 'gems' => 100]);
    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('legacy offer prices convert to coins once while historical purchases remain intact', function () {
    $offer = ShopOffer::factory()->create(['currency' => 'gems', 'price' => 8, 'stock' => 3, 'is_active' => false]);
    $coinOffer = ShopOffer::factory()->create(['price' => 45]);
    $receipt = ItemPurchase::factory()->create(['shop_offer_id' => $offer->id, 'currency' => 'gems', 'price_paid' => 8]);
    $migration = require database_path('migrations/2026_09_26_145111_convert_shop_offers_to_coins.php');

    $migration->up();
    $migration->up();

    $this->assertDatabaseHas('shop_offers', ['id' => $offer->id, 'currency' => 'coins', 'price' => 80, 'stock' => 3, 'is_active' => false]);
    $this->assertDatabaseHas('shop_offers', ['id' => $coinOffer->id, 'currency' => 'coins', 'price' => 45]);
    $this->assertDatabaseHas('item_purchases', ['id' => $receipt->id, 'currency' => 'gems', 'price_paid' => 8]);
});
