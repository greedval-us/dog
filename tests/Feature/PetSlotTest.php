<?php

use App\Models\Pet;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('a new player sees nine slots with only the first unlocked and increasing prices', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('slots', 9)->where('slots.0.unlocked', true)->where('slots.0.pet', null)
            ->where('slots.1.unlocked', false)->where('slots.1.purchasable', true)
            ->where('slots.1.coins', 100)->where('slots.1.gems', 10)
            ->where('slots.2.purchasable', false)
            ->where('slots.8.unlocked', false)->where('slots.8.coins', 800)->where('slots.8.gems', 80));
});

test('buying a slot charges only the selected currency and repeat requests do not charge twice', function (string $currency, int $price, int $coins, int $gems) {
    $user = User::factory()->create(['coins' => 1000, 'gems' => 100]);
    $other = User::factory()->create(['coins' => 1000, 'gems' => 100]);
    $payload = ['slot' => 2, 'currency' => $currency, 'expected_price' => $price, 'user_id' => $other->id];

    $this->actingAs($user)->from(route('dashboard'))->post(route('pet-slots.store'), $payload)
        ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
    $this->post(route('pet-slots.store'), $payload)->assertSessionHasNoErrors();

    expect($user->fresh()->pet_slots)->toBe(2);
    expect($user->fresh()->coins)->toBe($coins);
    expect($user->fresh()->gems)->toBe($gems);
    expect($other->fresh()->pet_slots)->toBe(1);
    expect($other->fresh()->coins)->toBe(1000);
    expect($other->fresh()->gems)->toBe(100);
    $this->assertDatabaseCount('pets', 0);
})->with(['coins' => ['coins', 100, 900, 100], 'gems' => ['gems', 10, 1000, 90]]);

test('invalid purchases preserve the balance and available slots', function (int $unlocked, int $slot, string $currency, int $price, int $coins, int $gems, string $error) {
    $user = User::factory()->create(['pet_slots' => $unlocked, 'coins' => $coins, 'gems' => $gems]);
    $this->actingAs($user)->post(route('pet-slots.store'), [
        'slot' => $slot, 'currency' => $currency, 'expected_price' => $price,
    ])->assertSessionHasErrors($error);
    expect($user->fresh()->pet_slots)->toBe($unlocked);
    expect($user->fresh()->coins)->toBe($coins);
    expect($user->fresh()->gems)->toBe($gems);
})->with([
    'insufficient coins' => [1, 2, 'coins', 100, 99, 100, 'slot'],
    'insufficient gems' => [1, 2, 'gems', 10, 1000, 9, 'slot'],
    'stale quote' => [2, 3, 'coins', 100, 1000, 100, 'slot'],
    'skip a slot' => [1, 3, 'coins', 200, 1000, 100, 'slot'],
    'tenth slot' => [9, 10, 'coins', 900, 1000, 100, 'slot'],
    'free first slot' => [1, 1, 'coins', 100, 1000, 100, 'slot'],
    'unknown currency' => [1, 2, 'experience', 100, 1000, 100, 'currency'],
    'free quote' => [1, 2, 'coins', 0, 1000, 100, 'expected_price'],
]);

test('all eight paid slots can be opened at increasing prices up to nine', function () {
    $user = User::factory()->create(['gems' => 360]);
    foreach ([2 => 10, 3 => 20, 4 => 30, 5 => 40, 6 => 50, 7 => 60, 8 => 70, 9 => 80] as $slot => $price) {
        $this->actingAs($user)->post(route('pet-slots.store'), [
            'slot' => $slot, 'currency' => 'gems', 'expected_price' => $price,
        ])->assertSessionHasNoErrors();
    }
    expect($user->fresh()->pet_slots)->toBe(9);
    expect($user->fresh()->gems)->toBe(0);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('slots.8.unlocked', true)->where('slots.8.purchasable', false));
});

test('guests and blocked players cannot buy slots', function () {
    $payload = ['slot' => 2, 'currency' => 'coins', 'expected_price' => 100];
    $this->post(route('pet-slots.store'), $payload)->assertRedirect(route('login'));
    $user = User::factory()->create(['status' => PlayerStatus::Blocked, 'coins' => 100]);
    $this->actingAs($user)->post(route('pet-slots.store'), $payload)->assertForbidden();
    expect($user->fresh()->pet_slots)->toBe(1);
    expect($user->fresh()->coins)->toBe(100);
});

test('players can switch between their own dogs but cannot select another players dog', function () {
    $user = User::factory()->create(['pet_slots' => 2]);
    $pets = Pet::factory()->count(2)->for($user)->create();
    $other = Pet::factory()->create();
    $this->actingAs($user)->get(route('dashboard', ['pet' => $pets[1]->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pet.id', $pets[1]->id)
        ->where('slots.0.pet.id', $pets[0]->id)
        ->where('slots.1.pet.id', $pets[1]->id)
        ->where('slots.2.pet', null));
    $this->get(route('dashboard', ['pet' => $other->id]))->assertNotFound();
});
