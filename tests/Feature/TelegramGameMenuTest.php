<?php

use App\Http\Telegram\GameMenu;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\ShopOffer;
use App\Models\Training;
use App\Models\User;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Inventory\Queries\GetPlayerInventory;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** @return list<array<string, mixed>> */
function telegramMenuCommands(TelegramScreen $screen): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all();
}

function telegramCareItem(User $user, string $category): InventoryItem
{
    return InventoryItem::factory()->for($user)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['remaining_uses' => 3]);
}

test('telegram home and profile use the player language and escape names without disclosing email', function () {
    $user = User::factory()->create(['name' => '<b>Alice</b>', 'locale' => 'en']);
    $menu = app(GameMenu::class);

    $home = $menu->render($user);
    $profile = $menu->render($user, ['action' => 'profile']);

    expect($home->text)->toContain('&lt;b&gt;Alice&lt;/b&gt;')->not->toContain('<b>Alice</b>');
    expect($home->rows[0][0]['command'])->toBe(['action' => 'pets']);
    expect($profile->text)->toContain('Level')->not->toContain($user->email);
});

test('telegram pet cards refuse another players pet', function () {
    $user = User::factory()->create();
    $foreign = Pet::factory()->create();

    expect(fn () => app(GameMenu::class)->render($user, ['action' => 'pet', 'petId' => $foreign->id]))
        ->toThrow(ModelNotFoundException::class);
});

test('telegram care confirmation preserves its token and duplicate starts consume no additional resources', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['hydration' => 40]);
    $menu = app(GameMenu::class);
    $confirmation = $menu->render($pet->user, ['action' => 'care.select', 'petId' => $pet->id, 'variant' => 'water']);
    $state = $confirmation->state;

    expect(Str::isUuid($state['draft']['token']))->toBeTrue();
    $refreshed = $menu->render($pet->user, ['action' => 'care.confirm'], $state);
    expect($refreshed->state['draft']['token'])->toBe($state['draft']['token']);
    $started = $menu->render($pet->user, ['action' => 'care.start'], $state);
    $menu->render($pet->user, ['action' => 'care.start'], $state);

    $this->assertDatabaseCount('pet_care_actions', 1);
    expect($started->state['care'])->petId->toBe($pet->id)->token->toBe($state['draft']['token']);
    $this->travel(15)->seconds();
    $menu->render($pet->user, ['action' => 'care.complete', 'petId' => $pet->id, 'token' => $state['draft']['token']]);
    $again = $menu->render($pet->user, ['action' => 'care.complete', 'petId' => $pet->id, 'token' => $state['draft']['token']]);
    expect($again->state)->not->toHaveKey('care');
    expect($pet->fresh()->activity)->toBeNull();
});

test('telegram purchases use the confirmed quote and one durable token', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['price' => 25]);
    $menu = app(GameMenu::class);
    $detail = $menu->render($user, ['action' => 'shop.offer', 'offerId' => $offer->id]);
    $confirmation = $menu->render($user, ['action' => 'shop.confirm'], $detail->state);

    expect(Str::isUuid($confirmation->state['purchase']['token']))->toBeTrue();
    $menu->render($user, ['action' => 'shop.buy'], $confirmation->state);
    $menu->render($user, ['action' => 'shop.buy'], $confirmation->state);

    expect($user->fresh()->coins)->toBe(75);
    $this->assertDatabaseCount('item_purchases', 1);
    $this->assertDatabaseCount('inventory_items', 1);
});

test('telegram inventory details render acquired snapshots and exclude another players items', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $item = InventoryItem::factory()->for($user)->create(['name' => ['en' => '<snapshot>'], 'remaining_uses' => 2]);
    $menu = app(GameMenu::class);

    $screen = $menu->render($user, ['action' => 'inventory.item', 'itemId' => $item->id]);

    expect($screen->text)->toContain('&lt;snapshot&gt;', '2')->not->toContain('<snapshot>');
    expect(fn () => $menu->render(User::factory()->create(), ['action' => 'inventory.item', 'itemId' => $item->id]))
        ->toThrow(ModelNotFoundException::class);
});

test('telegram care walks select required equipment and may skip optional clothing before confirmation', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $collar = telegramCareItem($pet->user, 'collars');
    $leash = telegramCareItem($pet->user, 'leashes');
    $menu = app(GameMenu::class);
    $collars = $menu->render($pet->user, ['action' => 'care.select', 'petId' => $pet->id, 'variant' => 'walk']);
    expect(telegramMenuCommands($collars))->toContain(['action' => 'care.choose', 'itemId' => $collar->id]);
    $leashes = $menu->render($pet->user, ['action' => 'care.choose', 'itemId' => $collar->id], $collars->state);
    expect(telegramMenuCommands($leashes))->toContain(['action' => 'care.choose', 'itemId' => $leash->id]);
    $clothing = $menu->render($pet->user, ['action' => 'care.choose', 'itemId' => $leash->id], $leashes->state);
    expect(telegramMenuCommands($clothing))->toContain(['action' => 'care.skip']);

    $confirmation = $menu->render($pet->user, ['action' => 'care.skip'], $clothing->state);
    $menu->render($pet->user, ['action' => 'care.start'], $confirmation->state);

    expect($confirmation->state['draft']['token'])->toBe($collars->state['draft']['token']);
    expect($collar->fresh()->remaining_uses)->toBe(2);
    expect($leash->fresh()->remaining_uses)->toBe(2);
    $this->assertDatabaseCount('pet_care_actions', 1);
});

test('telegram trainings use the configured training variant and sports inventory', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['speed' => 10, 'speed_potential' => 100, 'endurance' => 10, 'endurance_potential' => 100]);
    $training = Training::factory()->create();
    $sports = telegramCareItem($pet->user, 'sports');
    $menu = app(GameMenu::class);
    $options = $menu->render($pet->user, ['action' => 'care.variants', 'petId' => $pet->id, 'group' => 'training']);
    expect(telegramMenuCommands($options))->toContain(['action' => 'care.select', 'petId' => $pet->id, 'variant' => 'training:'.$training->id]);
    $items = $menu->render($pet->user, ['action' => 'care.select', 'petId' => $pet->id, 'variant' => 'training:'.$training->id]);
    $confirmation = $menu->render($pet->user, ['action' => 'care.choose', 'itemId' => $sports->id], $items->state);

    $menu->render($pet->user, ['action' => 'care.start'], $confirmation->state);

    $this->assertDatabaseHas('pet_care_actions', ['variant' => 'training:'.$training->id, 'pet_id' => $pet->id]);
    expect($sports->fresh()->remaining_uses)->toBe(2);
});

test('telegram shop keeps its confirmed price when the offer changes and refuses payment', function () {
    $user = User::factory()->create(['coins' => 100]);
    $offer = ShopOffer::factory()->create(['price' => 25]);
    $menu = app(GameMenu::class);
    $detail = $menu->render($user, ['action' => 'shop.offer', 'offerId' => $offer->id]);
    $confirmation = $menu->render($user, ['action' => 'shop.confirm'], $detail->state);
    $offer->update(['price' => 30]);

    expect(fn () => $menu->render($user, ['action' => 'shop.buy'], $confirmation->state))->toThrow(ItemUnavailable::class);

    expect($user->fresh()->coins)->toBe(100);
    $this->assertDatabaseCount('item_purchases', 0);
    $this->assertDatabaseCount('inventory_items', 0);
});

test('telegram inventory pages keep all items accessible and restore normal request pagination', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $item = Item::factory()->create();
    $instances = InventoryItem::factory()->for($user)->for($item)->count(13)->create();
    $menu = app(GameMenu::class);
    $first = $menu->render($user, ['action' => 'inventory', 'categoryId' => $item->item_category_id]);
    $next = collect(telegramMenuCommands($first))->firstWhere('offset', 6);
    $second = $menu->render($user, $next);
    $nextCursor = collect(telegramMenuCommands($second))->first(fn (array $command): bool => ($command['cursor'] ?? null) !== null);

    $last = $menu->render($user, $nextCursor);

    expect(telegramMenuCommands($last))->toContain(['action' => 'inventory.item', 'itemId' => $instances->last()->id, 'categoryId' => $item->item_category_id, 'cursor' => $nextCursor['cursor']]);
    expect(app(GetPlayerInventory::class)->handle($user, perPage: 12)->first()->id)->toBe($instances->first()->id);
});

test('telegram deceased pet cards show lifecycle and no care controls', function () {
    $pet = Pet::factory()->deceased()->create();

    $screen = app(GameMenu::class)->render($pet->user, ['action' => 'pet', 'petId' => $pet->id]);

    expect(collect(telegramMenuCommands($screen))->pluck('action')->all())->not->toContain('care.groups', 'care.variants');
    expect($screen->state)->not->toHaveKey('care');
});

test('telegram treats a removed confirmed training as a stale screen without spending equipment', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['speed' => 10, 'speed_potential' => 100, 'endurance' => 10, 'endurance_potential' => 100]);
    $training = Training::factory()->create();
    $sports = telegramCareItem($pet->user, 'sports');
    $menu = app(GameMenu::class);
    $items = $menu->render($pet->user, ['action' => 'care.select', 'petId' => $pet->id, 'variant' => 'training:'.$training->id]);
    $confirmation = $menu->render($pet->user, ['action' => 'care.choose', 'itemId' => $sports->id], $items->state);
    $training->update(['is_active' => false]);

    expect(fn () => $menu->render($pet->user, ['action' => 'care.start'], $confirmation->state))->toThrow(ModelNotFoundException::class);

    expect($sports->fresh()->remaining_uses)->toBe(3);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('telegram long item snapshots remain bounded valid escaped text', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $instance = InventoryItem::factory()->for($user)->create([
        'name' => ['en' => str_repeat('&', 1000)],
    ]);

    $screen = app(GameMenu::class)->render($user, ['action' => 'inventory.item', 'itemId' => $instance->id]);

    expect(mb_strlen($screen->text))->toBeLessThan(4096);
    expect($screen->text)->not->toMatch('/&[^;]*…$/u');
    expect(telegramMenuCommands($screen))->toContain(['action' => 'pets']);
});
