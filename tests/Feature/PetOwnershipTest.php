<?php

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\GameAsset;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Actions\SurrenderPet;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('a retired dog can be sold once without changing its identity or age', function () {
    $seller = User::factory()->create(['coins' => 20]);
    $buyer = User::factory()->create(['coins' => 500]);
    $asset = GameAsset::factory()->create();
    $pet = Pet::factory()->for($seller)->create(['born_at' => now()->subDays(120), 'retired_at' => now(), 'is_favorite' => true]);
    $pet->forceFill(['portrait_asset_id' => $asset->id, 'background_asset_id' => $asset->id])->save();
    $bornAt = $pet->born_at;
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $token = (string) Str::uuid();
    $receipt = app(PurchasePetSale::class)->handle($buyer, $listing->id, 137, $token);
    $replay = app(PurchasePetSale::class)->handle($buyer, $listing->id, 137, $token);
    expect($receipt->pet_id)->toBe($pet->id)->and($replay->id)->toBe($receipt->id)
        ->and($pet->fresh()->user_id)->toBe($buyer->id)
        ->and($pet->fresh()->born_at->equalTo($bornAt))->toBeTrue()
        ->and($pet->fresh()->retired_at)->not->toBeNull()
        ->and($pet->fresh()->is_favorite)->toBeFalse()
        ->and($pet->fresh()->portrait_asset_id)->toBeNull()->and($pet->fresh()->background_asset_id)->toBeNull()
        ->and($seller->fresh()->coins)->toBe(157)->and($buyer->fresh()->coins)->toBe(363)
        ->and($listing->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('pet_sale_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
    expect(fn () => app(PurchasePetSale::class)->handle($buyer, $listing->id, 138, $token))->toThrow(PetUnavailable::class);
});

test('insufficient funds and occupied slots cannot transfer a dog or pay its seller', function (string $failure) {
    $seller = User::factory()->create(['coins' => 20]);
    $buyer = User::factory()->create(['coins' => $failure === 'funds' ? 99 : 500]);
    if ($failure === 'slots') {
        Pet::factory()->for($buyer)->create();
    }
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    expect(fn () => app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid()))->toThrow(PetUnavailable::class);
    expect($pet->fresh()->user_id)->toBe($seller->id)->and($seller->fresh()->coins)->toBe(20);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['funds', 'slots']);

test('self purchase and foreign changes are forbidden', function () {
    $seller = User::factory()->create(['coins' => 500]);
    $other = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    expect(fn () => app(PurchasePetSale::class)->handle($seller, $listing->id, 100, (string) Str::uuid()))->toThrow(PetUnavailable::class);
    $this->actingAs($other)->post(route('pets.sale.store', $pet), ['price' => 100])->assertForbidden();
    $this->post(route('pets.surrender', $pet))->assertForbidden();
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('surrender releases a pensioners slot without coins or death', function () {
    $owner = User::factory()->create(['coins' => 17]);
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subDays(120), 'retired_at' => now()]);
    $listing = app(PublishPetSale::class)->handle($owner, $pet->id, 100);
    app(SurrenderPet::class)->handle($owner, $pet->id);
    app(SurrenderPet::class)->handle($owner, $pet->id);
    expect($pet->fresh()->surrendered_at)->not->toBeNull()->and($pet->fresh()->died_at)->toBeNull()
        ->and($owner->pets()->active()->exists())->toBeFalse()
        ->and($owner->fresh()->canClaimStarterPet())->toBeTrue()->and($owner->fresh()->coins)->toBe(17)
        ->and($listing->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('busy dogs and dogs with a pending litter or event cannot be transferred', function (string $kind) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    if ($kind === 'busy') {
        $pet->forceFill(['activity' => 'walk', 'activity_started_at' => now(), 'activity_ends_at' => now()->addHour()])->save();
    } elseif ($kind === 'litter') {
        BreedingLitter::factory()->create(['father_id' => $pet->id, 'born_at' => now()->addMinute(), 'delivered_at' => null]);
    } else {
        GameEventEntry::factory()->for($pet)->for($owner)->create(['status' => 'registered']);
    }
    expect(fn () => app(PublishPetSale::class)->handle($owner, $pet->id, 100))->toThrow(PetUnavailable::class);
    expect(fn () => app(SurrenderPet::class)->handle($owner, $pet->id))->toThrow(PetUnavailable::class);
    expect($pet->fresh()->user_id)->toBe($owner->id)->and($pet->fresh()->surrendered_at)->toBeNull();
})->with(['busy', 'litter', 'event']);

test('transferring a dog closes its breeding advertisement', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $breeding = BreedingListing::factory()->for($pet)->for($seller)->create(['is_active' => true]);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid());
    expect($breeding->fresh()->is_active)->toBeFalse();
});

test('market cards include real age and exclude blocked sellers', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $pet = Pet::factory()->for($seller)->create(['born_at' => now()->subDays(120), 'retired_at' => now()]);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 137);
    $this->actingAs($viewer)->get(route('pet-market.index'))->assertInertia(fn (Assert $page) => $page
        ->component('PetMarket')->has('listings', 1)->where('listings.0.id', $listing->id)
        ->where('listings.0.pet.ageMonths', 120)->where('listings.0.pet.retired', true));
    $seller->forceFill(['status' => 'blocked'])->save();
    $this->get(route('pet-market.index'))->assertInertia(fn (Assert $page) => $page->has('listings', 0));
});

test('different buyers can reuse a client token without losing the sellers payment', function () {
    $seller = User::factory()->create(['coins' => 20, 'pet_slots' => 2]);
    $firstBuyer = User::factory()->create(['coins' => 500]);
    $secondBuyer = User::factory()->create(['coins' => 500]);
    $first = Pet::factory()->for($seller)->create();
    $second = Pet::factory()->for($seller)->create();
    $firstListing = app(PublishPetSale::class)->handle($seller, $first->id, 100);
    $secondListing = app(PublishPetSale::class)->handle($seller, $second->id, 100);
    $token = (string) Str::uuid();

    app(PurchasePetSale::class)->handle($firstBuyer, $firstListing->id, 100, $token);
    app(PurchasePetSale::class)->handle($secondBuyer, $secondListing->id, 100, $token);

    expect($seller->fresh()->coins)->toBe(220)->and($firstBuyer->fresh()->coins)->toBe(400)
        ->and($secondBuyer->fresh()->coins)->toBe(400);
    $this->assertDatabaseCount('pet_sale_purchases', 2);
    $this->assertDatabaseCount('currency_transactions', 4);
});

test('a changed price or new reservation invalidates a previously advertised purchase', function (string $change) {
    $seller = User::factory()->create(['coins' => 20]);
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    if ($change === 'price') {
        app(PublishPetSale::class)->handle($seller, $pet->id, 101);
    } else {
        GameEventEntry::factory()->for($pet)->for($seller)->create(['status' => 'registered']);
    }

    expect(fn () => app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid()))->toThrow(PetUnavailable::class);
    expect($pet->fresh()->user_id)->toBe($seller->id)->and($buyer->fresh()->coins)->toBe(500)
        ->and($seller->fresh()->coins)->toBe(20);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['price', 'reservation']);

test('sale and purchase endpoints validate their prices and tokens', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $this->actingAs($seller)->post(route('pets.sale.store', $pet), ['price' => 0])->assertSessionHasErrors('price');
    $this->assertDatabaseCount('pet_sale_listings', 0);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);

    $this->actingAs($buyer)->post(route('pet-market.purchase', $listing), ['expected_price' => 100, 'purchase_token' => 'forged'])
        ->assertSessionHasErrors('purchase_token');
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the dashboard exposes the current sale and redirects surrendered selections home', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $listing = app(PublishPetSale::class)->handle($owner, $pet->id, 137);

    $this->actingAs($owner)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->where('ownership.listing.id', $listing->id)->where('ownership.listing.price', 137)->where('ownership.reason', null));
    app(SurrenderPet::class)->handle($owner, $pet->id);
    $this->get(route('dashboard', ['pet' => $pet->id]))->assertRedirect(route('dashboard'));
});

test('transfer actions recheck natural death after acquiring the final pet lock', function (string $operation) {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    $pet->forceFill(['born_at' => now()->subDays(144)->addSecond(), 'lifespan_months' => 144])->save();
    $reads = 0;
    Event::listen('eloquent.retrieved: '.Pet::class, function (Pet $retrieved) use ($pet, &$reads): void {
        if ($retrieved->id === $pet->id && ++$reads === 2) {
            $this->travel(1)->seconds();
        }
    });

    $perform = match ($operation) {
        'publish' => fn () => app(PublishPetSale::class)->handle($seller, $pet->id, 101),
        'purchase' => fn () => app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid()),
        default => fn () => app(SurrenderPet::class)->handle($seller, $pet->id),
    };
    expect($perform)->toThrow(PetUnavailable::class);
    expect($pet->fresh()->user_id)->toBe($seller->id)->and($pet->fresh()->surrendered_at)->toBeNull();
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['publish', 'purchase', 'surrender']);

test('opening the market settles an offline death before counting free slots', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create([
        'health' => 1, 'satiety' => 0, 'hydration' => 0,
        'state_updated_at' => now()->subHour(), 'stats_updated_at' => now()->subHour(),
    ]);

    $this->actingAs($owner)->get(route('pet-market.index'))->assertInertia(fn (Assert $page) => $page->where('freeSlots', 1));
    expect($pet->fresh()->died_at)->not->toBeNull();
});
