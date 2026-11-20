<?php

use App\Models\Dog;
use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Queries\GetPetMarket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeSecond();
});

test('the market separates public and own listings and includes localized breed image identities', function () {
    $viewer = User::factory()->create();
    $breed = Dog::factory()->create(['name' => ['ru' => 'Овчарка', 'en' => 'Shepherd']]);
    $foreignPet = Pet::factory()->for($breed)->retired()->create(['name' => 'Senior']);
    $foreign = PetSaleListing::factory()->for($foreignPet, 'pet')->create(['price' => 137]);
    $ownPet = Pet::factory()->for($viewer)->for($breed)->create();
    $own = PetSaleListing::factory()->for($ownPet, 'pet')->create();

    $market = app(GetPetMarket::class)->handle($viewer, 'ru');

    expect(array_column($market['listings'], 'id'))->toBe([$foreign->id]);
    expect(array_column($market['ownListings'], 'id'))->toBe([$own->id]);
    expect($market['listings'][0]['price'])->toBe(137);
    expect($market['listings'][0]['pet'])->toMatchArray([
        'id' => $foreignPet->id, 'dogId' => $breed->id, 'name' => 'Senior', 'breed' => 'Овчарка', 'retired' => true,
    ]);
    expect($market['listings'][0]['seller'])->toBe(['id' => $foreignPet->user_id, 'username' => $foreignPet->user->username]);
});

test('explicit market cursors traverse public listings in both directions', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $breed = Dog::factory()->create();
    $listings = Pet::factory()->count(14)->for($seller)->for($breed)->create()
        ->map(fn (Pet $pet): PetSaleListing => PetSaleListing::factory()->for($pet, 'pet')->create());
    $query = app(GetPetMarket::class);

    $first = $query->handle($viewer, 'en');
    $second = $query->handle($viewer, 'en', Cursor::fromEncoded($first['pagination']['nextCursor']));
    $previous = $query->handle($viewer, 'en', Cursor::fromEncoded($second['pagination']['previousCursor']));

    expect(array_column($first['listings'], 'id'))->toBe($listings->take(12)->pluck('id')->all());
    expect(array_column($second['listings'], 'id'))->toBe($listings->skip(12)->pluck('id')->all());
    expect($second['pagination']['nextCursor'])->toBeNull();
    expect(array_column($previous['listings'], 'id'))->toBe($listings->take(12)->pluck('id')->all());
    expect($previous['pagination']['previousCursor'])->toBeNull();
});

test('the website market still resolves the request cursor when no explicit cursor is given', function () {
    $this->withoutVite();
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $breed = Dog::factory()->create();
    $listings = Pet::factory()->count(13)->for($seller)->for($breed)->create()
        ->map(fn (Pet $pet): PetSaleListing => PetSaleListing::factory()->for($pet, 'pet')->create());
    $first = app(GetPetMarket::class)->handle($viewer, 'en');

    $this->actingAs($viewer)->get(route('pet-market.index', ['cursor' => $first['pagination']['nextCursor']]))
        ->assertInertia(fn (Assert $page) => $page->has('listings', 1)->where('listings.0.id', $listings->last()->id));
});

test('market sex filters apply to both public and own listings', function (PetSex $sex) {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $breed = Dog::factory()->create();
    $ownMale = PetSaleListing::factory()->for(Pet::factory()->for($viewer)->for($breed), 'pet')->create();
    $ownFemale = PetSaleListing::factory()->for(Pet::factory()->for($viewer)->for($breed)->female(), 'pet')->create();
    $publicMale = PetSaleListing::factory()->for(Pet::factory()->for($seller)->for($breed), 'pet')->create();
    $publicFemale = PetSaleListing::factory()->for(Pet::factory()->for($seller)->for($breed)->female(), 'pet')->create();

    $market = app(GetPetMarket::class)->handle($viewer, 'en', null, $sex);

    expect(array_column($market['ownListings'], 'id'))->toBe([$sex === PetSex::Male ? $ownMale->id : $ownFemale->id]);
    expect(array_column($market['listings'], 'id'))->toBe([$sex === PetSex::Male ? $publicMale->id : $publicFemale->id]);
})->with(['male' => PetSex::Male, 'female' => PetSex::Female]);

test('market details resolve a public listing beyond the current page and an own listing', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $breed = Dog::factory()->create(['name' => ['ru' => 'Овчарка', 'en' => 'Shepherd']]);
    $listings = Pet::factory()->count(13)->for($seller)->for($breed)->create()
        ->map(fn (Pet $pet): PetSaleListing => PetSaleListing::factory()->for($pet, 'pet')->create());
    $ownPet = Pet::factory()->for($viewer)->for($breed)->create();
    $own = PetSaleListing::factory()->for($ownPet, 'pet')->create();
    $query = app(GetPetMarket::class);

    $public = $query->find($viewer, $listings->last()->id, 'en');
    $owned = $query->find($viewer, $own->id, 'en');

    expect($public['id'])->toBe($listings->last()->id);
    expect($public['pet'])->toMatchArray(['dogId' => $breed->id, 'breed' => 'Shepherd']);
    expect($owned['id'])->toBe($own->id);
    expect($owned['pet']['id'])->toBe($ownPet->id);
    expect($owned['seller']['id'])->toBe($viewer->id);
});

test('market lists and details hide unavailable listings without persisting projected lifecycle changes', function (string $reason) {
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create();
    $listing = PetSaleListing::factory()->for($pet, 'pet')->create();
    match ($reason) {
        'withdrawn' => $listing->update(['is_active' => false]),
        'blocked' => $pet->user->forceFill(['status' => 'blocked'])->save(),
        'dead' => $pet->forceFill(['died_at' => now(), 'health' => 0])->save(),
        'surrendered' => $pet->forceFill(['surrendered_at' => now()])->save(),
        'changed owner' => $pet->update(['user_id' => $viewer->id]),
        'projected death' => $pet->forceFill(['born_at' => now()->subDays(144), 'lifespan_months' => 144])->save(),
    };
    $savedState = $pet->fresh()->only(['died_at', 'health', 'state_updated_at', 'stats_updated_at']);
    $query = app(GetPetMarket::class);

    $market = $query->handle($viewer, 'en');

    expect($market['listings'])->toBe([]);
    expect($market['ownListings'])->toBe([]);
    expect(fn () => $query->find($viewer, $listing->id, 'en'))->toThrow(ModelNotFoundException::class);
    expect($pet->fresh()->only(['died_at', 'health', 'state_updated_at', 'stats_updated_at']))->toEqual($savedState);
})->with(['withdrawn', 'blocked', 'dead', 'surrendered', 'changed owner', 'projected death']);
