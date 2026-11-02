<?php

use App\Http\Telegram\Menus\PetSlotMenu;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;

test('telegram dog slots show localized capacity and escape occupied names without buying', function (string $locale, string $title) {
    $user = User::factory()->create(['coins' => 1000, 'gems' => 100]);
    Pet::factory()->for($user)->create(['name' => '<Луна> & Рэй']);

    $screen = app(PetSlotMenu::class)->index($user, $locale);

    expect($screen->text)->toContain($title, '1/9', '&lt;Луна&gt; &amp; Рэй')->not->toContain('<Луна>');
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(
        ['action' => 'slots.confirm', 'slot' => 2, 'currency' => 'coins'],
        ['action' => 'slots.confirm', 'slot' => 2, 'currency' => 'gems'],
    );
    expect($screen->state)->toBeEmpty();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000, 'gems' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['english' => ['en', 'Dog slots'], 'russian' => ['ru', 'Места для собак']]);

test('telegram dog slots confirm the next place with an owner bound native price quote', function (string $currency, int $price) {
    $user = User::factory()->create(['coins' => 1000, 'gems' => 100]);

    $screen = app(PetSlotMenu::class)->confirm($user, 'en', ['slot' => 2, 'currency' => $currency]);

    expect($screen->state)->toBe(['slotPurchase' => [
        'userId' => $user->id, 'slot' => 2, 'currency' => $currency, 'expectedPrice' => $price,
    ]]);
    expect($screen->text)->toContain((string) $price, trans('telegram.'.$currency, [], 'en'));
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->toContain('slots.buy', 'slots', 'home');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000, 'gems' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['coins' => ['coins', 100], 'gems' => ['gems', 10]]);

test('telegram dog slot purchases charge only the confirmed currency', function (string $currency, int $coins, int $gems) {
    $user = User::factory()->create(['coins' => 1000, 'gems' => 100]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['slot' => 2, 'currency' => $currency]);

    $result = $menu->buy($user, 'en', $confirmation->state);

    expect($result->text)->toContain(trans('telegram.slots.opened', ['slot' => 2], 'en'));
    expect(collect($result->rows)->flatten(1)->pluck('command.action')->filter()->all())->toContain('kennel', 'slots', 'home');
    expect($result->state)->toBeEmpty();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 2, 'coins' => $coins, 'gems' => $gems]);
    $this->assertDatabaseHas('currency_transactions', [
        'user_id' => $user->id, 'currency' => $currency, 'operation_key' => 'pet-slot:2', 'reason' => 'pet_slot_purchase',
    ]);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('pets', 0);
})->with(['coins' => ['coins', 900, 100], 'gems' => ['gems', 1000, 90]]);

test('telegram dog slot confirmations never replace the price that was shown', function () {
    $user = User::factory()->create(['coins' => 1000]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['slot' => 2, 'currency' => 'coins']);
    config(['pet_slots.prices.2.coins' => 200]);

    expect(fn () => $menu->buy($user, 'en', $confirmation->state))
        ->toThrow(PetUnavailable::class, 'The price has changed. Refresh the page before purchasing.');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram dog slot purchase retries do not charge again after the catalogue price changes', function () {
    $user = User::factory()->create(['coins' => 100]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['slot' => 2, 'currency' => 'coins']);
    $menu->buy($user, 'en', $confirmation->state);
    config(['pet_slots.prices.2.coins' => 200]);

    $result = $menu->buy($user, 'en', $confirmation->state);

    expect($result->text)->toContain(trans('telegram.slots.opened', ['slot' => 2], 'en'));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 2, 'coins' => 0]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('telegram dog slot purchases preserve balances when the confirmed currency is insufficient', function (string $currency) {
    $user = User::factory()->create(['coins' => 99, 'gems' => 9]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['slot' => 2, 'currency' => $currency]);

    expect(fn () => $menu->buy($user, 'en', $confirmation->state))
        ->toThrow(PetUnavailable::class, 'You do not have enough currency for this dog slot.');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 99, 'gems' => 9]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['coins', 'gems']);

test('telegram dog slots reject confirmations for places outside the native next slot', function (array $command) {
    $user = User::factory()->create(['coins' => 1000, 'gems' => 100]);

    expect(fn () => app(PetSlotMenu::class)->confirm($user, 'en', $command))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000, 'gems' => 100]);
})->with([
    'already open' => [['slot' => 1, 'currency' => 'coins']],
    'skipped place' => [['slot' => 3, 'currency' => 'coins']],
    'above capacity' => [['slot' => 10, 'currency' => 'coins']],
    'unknown currency' => [['slot' => 2, 'currency' => 'experience']],
]);

test('telegram dog slots refuse another players saved confirmation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create(['coins' => 1000]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($owner, 'en', ['slot' => 2, 'currency' => 'coins']);

    expect(fn () => $menu->buy($other, 'en', $confirmation->state))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseHas('users', ['id' => $other->id, 'pet_slots' => 1, 'coins' => 1000]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram dog slots reject malformed saved quotes before purchasing', function (array $changes) {
    $user = User::factory()->create(['coins' => 1000]);
    $state = ['slotPurchase' => array_replace([
        'userId' => $user->id, 'slot' => 2, 'currency' => 'coins', 'expectedPrice' => 100,
    ], $changes)];

    expect(fn () => app(PetSlotMenu::class)->buy($user, 'en', $state))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'invalid slot' => [['slot' => []]],
    'zero price' => [['expectedPrice' => 0]],
    'unknown currency' => [['currency' => 'experience']],
    'above capacity' => [['slot' => 10]],
]);

test('telegram dog slot purchases check persisted account status', function () {
    $user = User::factory()->create(['coins' => 1000]);
    $menu = app(PetSlotMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['slot' => 2, 'currency' => 'coins']);
    User::query()->whereKey($user->id)->update(['status' => PlayerStatus::Blocked]);

    expect(fn () => $menu->buy($user, 'en', $confirmation->state))->toThrow(PetUnavailable::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'pet_slots' => 1, 'coins' => 1000]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram dog slots explain the maximum capacity without offering another purchase', function () {
    $user = User::factory()->create(['pet_slots' => 9]);

    $screen = app(PetSlotMenu::class)->index($user, 'en');

    expect($screen->text)->toContain(trans('telegram.slots.maximum', [], 'en'), '9/9');
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->not->toContain('slots.confirm', 'slots.buy');
});

test('telegram dog slots paginate occupied places and offer only the next available slot', function () {
    $user = User::factory()->create(['pet_slots' => 7]);
    $pets = Pet::factory()->for($user)->count(7)->sequence(fn ($sequence) => ['name' => 'Dog '.($sequence->index + 1)])->create();
    $menu = app(PetSlotMenu::class);

    $first = $menu->index($user, 'en');
    $last = $menu->index($user, 'en', ['offset' => 6]);

    expect($first->text)->toContain('Dog 6')->not->toContain($pets->last()->name);
    expect($last->text)->toContain('Dog 7')->not->toContain('Dog 1');
    expect(collect($first->rows)->flatten(1)->pluck('command')->filter()->values()->all())
        ->toContain(['action' => 'slots', 'offset' => 6, 'cursor' => null]);
    expect(collect($last->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(
        ['action' => 'slots.confirm', 'slot' => 8, 'currency' => 'coins'],
        ['action' => 'slots.confirm', 'slot' => 8, 'currency' => 'gems'],
    );
});

test('telegram dog slots do not offer a purchase with a missing native currency price', function () {
    $user = User::factory()->create();
    config(['pet_slots.prices.2.coins' => 0]);
    $menu = app(PetSlotMenu::class);

    $screen = $menu->index($user, 'en');

    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all())
        ->not->toContain(['action' => 'slots.confirm', 'slot' => 2, 'currency' => 'coins'])
        ->toContain(['action' => 'slots.confirm', 'slot' => 2, 'currency' => 'gems']);
    expect(fn () => $menu->confirm($user, 'en', ['slot' => 2, 'currency' => 'coins']))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');
});
