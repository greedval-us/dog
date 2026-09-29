<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;

test('care supplies are paginated and filtered by owner category and required uses', function () {
    $owner = User::factory()->create(['locale' => 'ru']);
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    $instances = InventoryItem::factory()->count(14)->for($owner)->for($item)->create([
        'remaining_uses' => 3, 'name' => ['ru' => 'Мяч', 'en' => 'Ball'],
    ]);
    InventoryItem::factory()->for($owner)->for($item)->create(['remaining_uses' => 1]);
    InventoryItem::factory()->for($item)->create();
    InventoryItem::factory()->for($owner)->create();

    $first = $this->actingAs($owner)->getJson(route('care-items', ['category' => 'toys', 'uses' => 2]))
        ->assertOk()->assertJsonCount(12, 'items')->assertJsonPath('items.0.name', 'Мяч');
    expect(array_column($first->json('items'), 'id'))->toBe($instances->take(12)->modelKeys());
    $second = $this->getJson(route('care-items', ['category' => 'toys', 'uses' => 2, 'cursor' => $first->json('nextCursor')]))
        ->assertOk()->assertJsonCount(2, 'items')->assertJsonPath('nextCursor', null);
    expect(array_column($second->json('items'), 'id'))->toBe($instances->skip(12)->values()->modelKeys());
});

test('care item selection requires authentication and valid filters', function () {
    $this->getJson(route('care-items', ['category' => 'toys']))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('care-items', ['category' => 'unknown', 'uses' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors(['category', 'uses']);
});

test('care item selection returns an empty page for an exhausted category', function () {
    $this->actingAs(User::factory()->create())->getJson(route('care-items', ['category' => 'food']))
        ->assertExactJson(['items' => [], 'nextCursor' => null]);
});
