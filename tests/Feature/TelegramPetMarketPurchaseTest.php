<?php

use App\Http\Telegram\Menus\PetMarketPurchaseMenu;
use App\Models\Dog;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeSecond();
});

test('telegram market purchase confirms the visible dog with an owner bound price and safe return context', function (string $locale, string $breed) {
    $buyer = User::factory()->create(['coins' => 800, 'pet_slots' => 2]);
    Pet::factory()->for($buyer)->create();
    $seller = User::factory()->create();
    $dog = Dog::factory()->create(['name' => ['en' => 'Breed <A>', 'ru' => 'Порода <А>']]);
    $pet = Pet::factory()->for($seller)->for($dog)->create(['name' => '<Луна> & Рэй', 'born_at' => now()->subDays(31)]);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);

    $screen = app(PetMarketPurchaseMenu::class)->confirm($buyer, $locale, [
        'listingId' => $listing->id, 'sex' => 'male', 'cursor' => 'market-cursor', 'offset' => 6,
    ]);

    $quote = $screen->state['marketPurchase'];
    expect($quote)->toMatchArray([
        'userId' => $buyer->id, 'listingId' => $listing->id, 'petId' => $pet->id,
        'name' => '<Луна> & Рэй', 'expectedPrice' => 137, 'sex' => 'male', 'cursor' => 'market-cursor', 'offset' => 6,
    ]);
    expect(Str::isUuid($quote['token']))->toBeTrue();
    expect($screen->text)->toContain('&lt;Луна&gt; &amp; Рэй', $breed, '31', '137', '800',
        trans('telegram.market.space', ['count' => 1], $locale))->not->toContain('<Луна>', '<A>', '<А>');
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(
        ['action' => 'market.buy'],
        ['action' => 'market.show', 'listingId' => $listing->id, 'sex' => 'male', 'cursor' => 'market-cursor', 'offset' => 6],
    );
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'user_id' => $seller->id]);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['english' => ['en', 'Breed &lt;A&gt;'], 'russian' => ['ru', 'Порода &lt;А&gt;']]);

test('telegram market confirmation redraws preserve the original quote and token', function () {
    $buyer = User::factory()->create(['coins' => 800]);
    $seller = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $first = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);

    $redraw = $menu->confirm($buyer, 'en', ['listingId' => $listing->id], $first->state);

    expect($redraw->state)->toBe($first->state);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram market confirmation redraws reject a price change instead of replacing the confirmed price', function () {
    $buyer = User::factory()->create(['coins' => 800]);
    $seller = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $first = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);
    app(PublishPetSale::class)->handle($seller, $pet->id, 138);

    expect(fn () => $menu->confirm($buyer, 'en', ['listingId' => $listing->id], $first->state))
        ->toThrow(PetUnavailable::class, 'ownership.errors.price_changed');

    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram market purchase transfers the dog and pays its seller once with an escaped receipt', function () {
    $buyer = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create(['coins' => 20]);
    $pet = Pet::factory()->for($seller)->create(['name' => '<Луна> & Рэй', 'is_favorite' => true]);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $confirmation = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);

    $result = $menu->buy($buyer, 'en', $confirmation->state);
    $replay = $menu->buy($buyer, 'en', $confirmation->state);

    expect($result->text)->toContain('&lt;Луна&gt; &amp; Рэй', '137')->not->toContain('<Луна>');
    expect($replay->text)->toBe($result->text);
    expect($result->state)->toBeEmpty();
    expect(collect($result->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(
        ['action' => 'pet', 'petId' => $pet->id], ['action' => 'market'], ['action' => 'slots'],
    );
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'user_id' => $buyer->id, 'is_favorite' => false]);
    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 363]);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => 157]);
    $this->assertDatabaseHas('pet_sale_listings', ['id' => $listing->id, 'is_active' => false]);
    $this->assertDatabaseHas('pet_sale_purchases', [
        'listing_id' => $listing->id, 'pet_id' => $pet->id, 'buyer_id' => $buyer->id,
        'seller_id' => $seller->id, 'token' => $confirmation->state['marketPurchase']['token'], 'price_paid' => 137,
    ]);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $buyer->id, 'reason' => 'pet_purchase', 'amount' => -137]);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $seller->id, 'reason' => 'pet_sale', 'amount' => 137]);
    $this->assertDatabaseCount('pet_sale_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
    expect($seller->notifications()->get()->where('data.kind', 'pet_sold')->count())->toBe(1);
});

test('telegram market purchase replays its durable receipt after the dog is no longer active or owned', function (string $change) {
    $buyer = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create(['coins' => 20]);
    $pet = Pet::factory()->for($seller)->create(['name' => 'Original name']);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $confirmation = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);
    $menu->buy($buyer, 'en', $confirmation->state);
    $pet->refresh()->forceFill(match ($change) {
        'deceased' => ['died_at' => now(), 'health' => 0, 'name' => 'Changed name'],
        'surrendered' => ['surrendered_at' => now(), 'name' => 'Changed name'],
        'transferred' => ['user_id' => $seller->id, 'name' => 'Changed name'],
    })->save();
    $listing->refresh()->update(['price' => 999]);

    $result = $menu->buy($buyer, 'en', $confirmation->state);

    expect($result->text)->toContain('Original name', '137')->not->toContain('Changed name', '999');
    expect(collect($result->rows)->flatten(1)->pluck('command.action')->filter()->values()->all())->not->toContain('pet');
    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 363]);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => 157]);
    $this->assertDatabaseCount('pet_sale_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
})->with(['deceased', 'surrendered', 'transferred']);

test('telegram market purchase rechecks native conditions after confirmation without charging on refusal', function (string $change, string $reason) {
    $buyer = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create(['coins' => 20]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $confirmation = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);
    match ($change) {
        'price' => app(PublishPetSale::class)->handle($seller, $pet->id, 138),
        'withdrawn' => app(PublishPetSale::class)->withdraw($seller, $pet->id),
        'reserved' => GameEventEntry::factory()->for($pet)->for($seller)->create(['status' => 'registered']),
        'slots' => Pet::factory()->for($buyer)->create(),
        'funds' => $buyer->forceFill(['coins' => 136])->save(),
        'blocked' => $buyer->forceFill(['status' => PlayerStatus::Blocked])->save(),
    };

    expect(fn () => $menu->buy($buyer, 'en', $confirmation->state))->toThrow(PetUnavailable::class, $reason);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'user_id' => $seller->id]);
    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => $change === 'funds' ? 136 : 500]);
    $this->assertDatabaseHas('users', ['id' => $seller->id, 'coins' => 20]);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($seller->notifications()->get()->where('data.kind', 'pet_sold')->isNotEmpty())->toBeFalse();
})->with([
    'changed price' => ['price', 'ownership.errors.price_changed'],
    'withdrawn listing' => ['withdrawn', 'ownership.errors.unavailable'],
    'new reservation' => ['reserved', 'ownership.errors.event'],
    'occupied slots' => ['slots', 'You need a free dog slot. Unlock a place on the My dog page.'],
    'insufficient funds' => ['funds', 'ownership.errors.funds'],
    'persisted blocked buyer' => ['blocked', 'Your account is blocked.'],
]);

test('telegram market confirmation rejects the sellers own listing', function () {
    $seller = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);

    expect(fn () => app(PetMarketPurchaseMenu::class)->confirm($seller, 'en', ['listingId' => $listing->id]))
        ->toThrow(PetUnavailable::class, 'ownership.errors.unavailable');

    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram market confirmation refuses listings outside the visible market scope', function (string $change) {
    $buyer = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    match ($change) {
        'withdrawn' => app(PublishPetSale::class)->withdraw($seller, $pet->id),
        'blocked' => $seller->forceFill(['status' => PlayerStatus::Blocked])->save(),
        'inactive' => $pet->forceFill(['surrendered_at' => now()])->save(),
        'foreign owner' => $pet->forceFill(['user_id' => $buyer->id])->save(),
    };

    expect(fn () => app(PetMarketPurchaseMenu::class)->confirm($buyer, 'en', ['listingId' => $listing->id]))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['withdrawn', 'blocked', 'inactive', 'foreign owner']);

test('telegram market purchase rejects another players quote before transferring the dog', function () {
    $buyer = User::factory()->create(['coins' => 500]);
    $other = User::factory()->create(['coins' => 500]);
    $seller = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $menu = app(PetMarketPurchaseMenu::class);
    $confirmation = $menu->confirm($buyer, 'en', ['listingId' => $listing->id]);

    expect(fn () => $menu->buy($other, 'en', $confirmation->state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'user_id' => $seller->id]);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'coins' => 500]);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram market purchase rejects malformed saved quotes before gameplay runs', function (array $changes) {
    $buyer = User::factory()->create(['coins' => 500]);
    $quote = array_replace([
        'userId' => $buyer->id, 'listingId' => 1, 'petId' => 1, 'name' => 'Dog', 'expectedPrice' => 137,
        'token' => '00000000-0000-4000-8000-000000000001', 'sex' => null, 'cursor' => null, 'offset' => 0,
    ], $changes);

    expect(fn () => app(PetMarketPurchaseMenu::class)->buy($buyer, 'en', ['marketPurchase' => $quote]))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'coins' => 500]);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'invalid owner type' => [['userId' => '1']],
    'invalid listing type' => [['listingId' => []]],
    'invalid pet type' => [['petId' => false]],
    'invalid name type' => [['name' => []]],
    'invalid token' => [['token' => 'forged']],
    'zero price' => [['expectedPrice' => 0]],
    'invalid price type' => [['expectedPrice' => '137']],
    'above maximum price' => [['expectedPrice' => 1000001]],
    'invalid filter' => [['sex' => 'unknown']],
    'invalid cursor' => [['cursor' => []]],
    'negative offset' => [['offset' => -1]],
]);

test('telegram market purchase requires a saved confirmation', function () {
    $buyer = User::factory()->create(['coins' => 500]);

    expect(fn () => app(PetMarketPurchaseMenu::class)->buy($buyer, 'en', []))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('currency_transactions', 0);
});
