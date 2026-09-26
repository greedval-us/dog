<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ShopOffer;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests must sign in to open the inventory', function () {
    $this->get(route('inventory.index'))->assertRedirect(route('login'));
});

test('inventory displays only the players localized snapshots including discontinued items', function (string $locale, string $name) {
    $player = User::factory()->create(['locale' => $locale]);
    $other = User::factory()->create();
    $category = ItemCategory::factory()->create(['is_active' => false]);
    $item = Item::factory()->for($category, 'category')->create(['name' => ['en' => 'Changed'], 'is_active' => false]);
    $instance = InventoryItem::factory()->for($player, 'user')->for($item)->create([
        'quality' => 7, 'usage_limit' => 10, 'remaining_uses' => 2,
        'characteristics' => ['mood' => 15], 'created_at' => '2026-09-20 12:00:00',
    ]);
    InventoryItem::factory()->for($other, 'user')->create();

    $this->actingAs($player)->get(route('inventory.index', ['user_id' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory')
            ->where('inventoryCount', 1)->where('itemTypesCount', 1)
            ->has('categories', 1)->where('categories.0.id', $category->id)
            ->has('items', 1)
            ->where('items.0.id', $instance->id)->where('items.0.name', $name)
            ->where('items.0.quality', 7)->where('items.0.usageLimit', 10)
            ->where('items.0.remainingUses', 2)->where('items.0.characteristics', ['mood' => 15])
            ->where('items.0.acquiredAt', '2026-09-20')
            ->where('previousCursor', null)->where('nextCursor', null)
        );
})->with([['ru', 'Мяч'], ['en', 'Ball']]);

test('inventory localizes snapshot names without falling back to current item names', function (array $translations, string $expectedName) {
    $player = User::factory()->create(['locale' => 'ru']);
    $category = ItemCategory::factory()->create(['name' => ['en' => 'Toys'], 'is_active' => false]);
    $item = Item::factory()->for($category, 'category')->create(['code' => 'ball', 'name' => ['ru' => 'Changed']]);
    InventoryItem::factory()->for($player, 'user')->for($item)->create(['name' => $translations]);

    $this->actingAs($player)->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)
        ->where('items.0.name', $expectedName)
        ->where('items.0.category', 'Toys')
        ->where('categories.0', ['id' => $category->id, 'code' => $category->code, 'name' => 'Toys'])
    );
})->with([
    'English snapshot' => [['en' => 'Original'], 'Original'],
    'null selected language' => [['ru' => null, 'en' => 'Original'], 'Original'],
    'empty selected language' => [['ru' => '', 'en' => 'Original'], ''],
    'empty English fallback' => [['en' => ''], ''],
    'missing translations' => [[], 'ball'],
    'null translations' => [['ru' => null, 'en' => null], 'ball'],
]);

test('inventory category pagination retains separate instances and global totals', function () {
    $player = User::factory()->create();
    $item = Item::factory()->create();
    $instances = InventoryItem::factory()->for($player, 'user')->for($item)->count(13)->create();
    InventoryItem::factory()->for($player, 'user')->create();
    InventoryItem::factory()->for($item)->create();

    $first = $this->actingAs($player)->get(route('inventory.index', ['category' => $item->item_category_id]));
    $first->assertInertia(fn (Assert $page) => $page
        ->has('categories', 2)->has('items', 12)
        ->where('items.0.id', $instances[0]->id)
        ->where('inventoryCount', 14)->where('itemTypesCount', 2)
        ->where('selectedCategory', $item->item_category_id)
        ->where('previousCursor', null)
    );
    $second = $this->get(route('inventory.index', ['category' => $item->item_category_id, 'cursor' => $first->inertiaProps('nextCursor')]));
    $second->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)->where('items.0.id', $instances[12]->id)
        ->where('nextCursor', null)->where('inventoryCount', 14)
    );
    $this->get(route('inventory.index', ['category' => $item->item_category_id, 'cursor' => $second->inertiaProps('previousCursor')]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 12)->where('items.0.id', $instances[0]->id)
            ->where('selectedCategory', $item->item_category_id)->where('previousCursor', null)
            ->where('inventoryCount', 14)
        );
});

test('an unowned inventory category cannot reveal another players items', function () {
    $player = User::factory()->create();
    $otherItem = InventoryItem::factory()->create();

    $this->actingAs($player)->get(route('inventory.index', ['category' => $otherItem->item->item_category_id]))->assertNotFound();
});

test('a new inventory is empty without fabricated records', function () {
    $this->actingAs(User::factory()->create())->get(route('inventory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 0)->has('categories', 0)
            ->where('inventoryCount', 0)->where('itemTypesCount', 0)
            ->where('selectedCategory', null)->where('nextCursor', null)
        );
});

test('a purchase appears in inventory and its count is visible only on the owners card', function () {
    $player = User::factory()->create(['coins' => 100]);
    $other = User::factory()->create();
    $offer = ShopOffer::factory()->create();

    $this->actingAs($player)->post(route('shop.store'), [
        'offer_id' => $offer->id, 'item_id' => $offer->item_id,
        'expected_currency' => 'coins', 'expected_price' => $offer->price,
        'purchase_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    $offer->item->update([
        'name' => ['ru' => 'Changed', 'en' => 'Changed'],
        'quality' => 9, 'usage_limit' => 20, 'characteristics' => ['mood' => 99],
    ]);

    $this->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)->where('items.0.remainingUses', 5)
        ->where('items.0.quality', 3)->where('items.0.usageLimit', 5)
        ->where('items.0.characteristics', ['mood' => 10, 'size' => 'medium'])
        ->where('inventoryCount', 1)
    );
    $this->get(route('players.show', $player->username))->assertInertia(fn (Assert $page) => $page
        ->where('inventoryCount', 1)->where('isOwner', true)
    );
    $this->actingAs($other)->get(route('players.show', $player->username))->assertInertia(fn (Assert $page) => $page
        ->where('inventoryCount', null)->where('isOwner', false)
    );
});
