<?php

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\BreedingPartner;
use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\StartBreeding;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use App\Modules\Players\Services\PlayerWallet;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/** @return array{0:User,1:User,2:Pet,3:Pet,4:BreedingListing} */
function breedingPair(): array
{
    test()->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    $damOwner = User::factory()->create(['experience' => '1500', 'coins' => 1000]);
    $sireOwner = User::factory()->create(['experience' => '1500', 'coins' => 0]);
    $caps = [];
    foreach (PetStat::cases() as $stat) {
        $caps[$stat->value] = 90;
        $caps[$stat->potentialColumn()] = 100;
    }
    $dam = Pet::factory()->female()->for($damOwner)->for($dog)->create(['born_at' => now()->subDays(8), ...$caps]);
    $sire = Pet::factory()->for($sireOwner)->for($dog)->create(['born_at' => now()->subDays(8), ...$caps]);
    $listing = BreedingListing::factory()->for($sire, 'pet')->for($sireOwner)->create(['price' => 100, 'is_active' => true]);

    return [$damOwner, $sireOwner, $dam, $sire, $listing];
}

test('breeding pages and writes require authentication', function () {
    $this->get(route('breeding.index'))->assertRedirect(route('login'));
    $this->post(route('breeding.store'))->assertRedirect(route('login'));
    $this->assertDatabaseCount('breeding_litters', 0);
});

test('a paid mating stores an unborn litter and stable distribution then replays without a second charge', function () {
    $this->freezeTime();
    [$damOwner, $sireOwner, $dam, $sire, $listing] = breedingPair();
    $token = (string) Str::uuid();
    $data = ['pet_id' => $dam->id, 'kind' => 'listing', 'partner_id' => $listing->id, 'expected_price' => 100, 'operation_token' => $token];

    $this->actingAs($damOwner)->post(route('breeding.store'), $data)->assertRedirect(route('puppies.index'));
    $first = BreedingLitter::query()->sole();
    $original = $first->puppies()->orderBy('id')->get()->toArray();
    $this->post(route('breeding.store'), $data)->assertRedirect(route('puppies.index'));

    expect($damOwner->fresh()->coins)->toBe(900);
    expect($sireOwner->fresh()->coins)->toBe(100);
    expect($first->born_at->format('Y-m-d H:i:s'))->toBe(now()->addHours(24)->format('Y-m-d H:i:s'));
    expect($dam->fresh()->breeding_available_at->format('Y-m-d H:i:s'))->toBe(now()->addDays(7)->format('Y-m-d H:i:s'));
    expect($sire->fresh()->breeding_available_at->format('Y-m-d H:i:s'))->toBe(now()->addDays(7)->format('Y-m-d H:i:s'));
    expect($first->puppies()->count())->toBeBetween(2, 5);
    expect($first->puppies()->where('user_id', $sireOwner->id)->count())->toBe(1);
    expect($first->puppies()->where('status', 'unborn')->count())->toBe(count($original));
    expect($first->puppies()->orderBy('id')->get()->toArray())->toBe($original);
    $this->assertDatabaseCount('breeding_litters', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
});

test('reusing a mating token with another price refuses the request', function () {
    [$owner, , $dam, , $listing] = breedingPair();
    $token = (string) Str::uuid();
    app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, $token);

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 101, $token))->toThrow(BreedingUnavailable::class, 'breeding.errors.token');
    $this->assertDatabaseCount('breeding_litters', 1);
    expect($owner->fresh()->coins)->toBe(900);
});

test('a mating rejects unavailable parents without payment or litter', function (array $changes, string $reason) {
    [$owner, , $dam, , $listing] = breedingPair();
    $dam->forceFill($changes)->save();

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, $reason);

    $this->assertDatabaseCount('breeding_litters', 0);
    $this->assertDatabaseCount('puppies', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'too young' => [fn () => ['born_at' => now()->subDays(6)], 'breeding.errors.young'],
    'cooldown' => [fn () => ['breeding_available_at' => now()->addHour()], 'breeding.errors.cooldown'],
    'retired' => [fn () => ['retired_at' => now()], 'breeding.errors.archived'],
]);

test('level comes from earned experience and ownership is rechecked', function () {
    [$owner, , $dam, , $listing] = breedingPair();
    $owner->forceFill(['level' => 5, 'experience' => '1499'])->save();

    $this->actingAs($owner)->post(route('breeding.store'), ['pet_id' => $dam->id, 'kind' => 'listing', 'partner_id' => $listing->id, 'expected_price' => 100, 'operation_token' => (string) Str::uuid()])->assertSessionHasErrors('breeding');
    $this->assertDatabaseCount('breeding_litters', 0);
    expect($owner->fresh()->coins)->toBe(1000);
});

test('siblings and parent descendant matings are refused', function (string $relation) {
    [$owner, , $dam, $sire, $listing] = breedingPair();
    if ($relation === 'parent') {
        $dam->forceFill(['father_id' => $sire->id])->save();
    } else {
        $ancestor = Pet::factory()->for($sire->dog)->create();
        $dam->forceFill(['father_id' => $ancestor->id])->save();
        $sire->forceFill(['father_id' => $ancestor->id])->save();
    }

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, 'breeding.errors.related');
    $this->assertDatabaseCount('breeding_litters', 0);
})->with(['parent', 'sibling']);

test('listing price changes and insufficient coins cannot create a litter', function (int $price, int $coins, string $reason) {
    [$owner, , $dam, , $listing] = breedingPair();
    $listing->update(['price' => $price]);
    $owner->forceFill(['coins' => $coins])->save();

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, $reason);
    $this->assertDatabaseCount('breeding_litters', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['price changed' => [101, 1000, 'breeding.errors.price'], 'no coins' => [100, 99, 'breeding.errors.funds']]);

test('system partners work for a male owner and reserve the remaining puppies for the kennel', function () {
    [$owner, , $dam] = breedingPair();
    $dam->forceFill(['sex' => 'male'])->save();
    $partner = BreedingPartner::query()->whereHas('pet', fn ($query) => $query->where('dog_id', $dam->dog_id)->where('sex', 'female'))->firstOrFail();

    $litter = app(StartBreeding::class)->handle($owner, $dam->id, 'partner', $partner->id, 100, (string) Str::uuid());

    expect($litter->puppies()->where('user_id', $owner->id)->count())->toBe(1);
    expect($litter->puppies()->whereNull('user_id')->count())->toBe($litter->puppies()->count() - 1);
    expect($owner->fresh()->coins)->toBe(900);
    expect($partner->pet->fresh()->breeding_available_at)->toBeNull();
});

test('mating rolls back payment and parental cooldown if seller credit fails', function () {
    [$owner, $seller, $dam, $sire, $listing] = breedingPair();
    Event::listen('eloquent.creating: '.CurrencyTransaction::class, function (CurrencyTransaction $entry) use ($seller): void {
        if ($entry->user_id === $seller->id) {
            throw new RuntimeException('credit failed');
        }
    });
    try {
        expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(RuntimeException::class, 'credit failed');
    } finally {
        Event::forget('eloquent.creating: '.CurrencyTransaction::class);
    }

    expect($owner->fresh()->coins)->toBe(1000);
    expect($seller->fresh()->coins)->toBe(0);
    expect($dam->fresh()->breeding_available_at)->toBeNull();
    expect($sire->fresh()->breeding_available_at)->toBeNull();
    $this->assertDatabaseCount('breeding_litters', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the breeding board exposes the final inheritance forecast', function () {
    [$owner, , $dam, , $listing] = breedingPair();

    $this->actingAs($owner)->get(route('breeding.index', ['pet' => $dam->id, 'kind' => 'listing', 'partner' => $listing->id]))->assertInertia(fn (Assert $page) => $page->component('breeding/Index')->where('access.allowed', true)->has('preview.ranges', 6)->has('preview.colors', 4)->where('preview.reason', null));
});

test('a player cannot breed another players female dog', function () {
    [$owner, , $dam, , $listing] = breedingPair();
    $other = User::factory()->create(['experience' => '1500']);
    $dam->user()->associate($other);
    $dam->save();

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, 'breeding.errors.unavailable');
    $this->assertDatabaseCount('breeding_litters', 0);
    expect($owner->fresh()->coins)->toBe(1000);
});

test('dogs in an activity or of different breeds cannot mate', function (string $condition, string $reason) {
    [$owner, , $dam, $sire, $listing] = breedingPair();
    if ($condition === 'busy') {
        $dam->forceFill(['activity' => PetActivity::Walk, 'activity_started_at' => now(), 'activity_ends_at' => now()->addHour()])->save();
    } else {
        $sire->dog()->associate(Dog::query()->where('breed', 'pit_bull')->firstOrFail());
        $sire->save();
    }

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, $reason);
    $this->assertDatabaseCount('breeding_litters', 0);
})->with(['busy' => ['busy', 'breeding.errors.busy'], 'different breed' => ['breed', 'breeding.errors.incompatible']]);

test('breeding ones own pair costs no coins and all puppies belong to the same player', function () {
    [$owner, , $dam, $sire, $listing] = breedingPair();
    $owner->forceFill(['coins' => 0])->save();
    $sire->user()->associate($owner);
    $sire->save();
    $listing->update(['user_id' => $owner->id]);

    $litter = app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid());

    expect($litter->puppies()->where('user_id', $owner->id)->count())->toBe($litter->puppies()->count());
    expect($owner->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('players publish and withdraw only their own male breeding listing', function () {
    [$owner, $seller, $dam, $sire, $listing] = breedingPair();

    $this->actingAs($seller)->post(route('breeding.listings.store'), ['pet_id' => $sire->id, 'price' => 350])->assertRedirect(route('breeding.index'));
    expect($listing->fresh()->price)->toBe(350);
    $this->actingAs($owner)->delete(route('breeding.listings.destroy', $listing))->assertSessionHasErrors('listing');
    expect($listing->fresh()->is_active)->toBeTrue();
    $this->actingAs($seller)->delete(route('breeding.listings.destroy', $listing))->assertRedirect(route('breeding.index'));
    expect($listing->fresh()->is_active)->toBeFalse();
    $this->actingAs($owner)->post(route('breeding.listings.store'), ['pet_id' => $dam->id, 'price' => 100])->assertSessionHasErrors('listing');
});

test('invalid breeding form fields return validation errors before writes', function () {
    [$owner] = breedingPair();

    $this->actingAs($owner)->post(route('breeding.store'), ['kind' => 'invented', 'operation_token' => 'bad'])->assertSessionHasErrors(['pet_id', 'kind', 'partner_id', 'expected_price', 'operation_token']);
    $this->assertDatabaseCount('breeding_litters', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the mating market paginates offers and forecasts a selected offer beyond the first page', function () {
    [$owner, $seller, $dam] = breedingPair();
    $last = null;
    for ($index = 0; $index < 12; $index++) {
        $pet = Pet::factory()->for($seller)->for($dam->dog)->create(['born_at' => now()->subDays(8)]);
        $last = BreedingListing::factory()->for($pet, 'pet')->for($seller)->create(['price' => 100]);
    }

    $cursor = null;
    $this->actingAs($owner)->get(route('breeding.index', ['pet' => $dam->id]))->assertInertia(function (Assert $page) use (&$cursor): void {
        $page->has('listings', 12)->where('listingPagination.nextCursor', function (string $value) use (&$cursor): bool {
            $cursor = $value;

            return true;
        });
    });
    $this->get(route('breeding.index', ['pet' => $dam->id, 'cursor' => $cursor]))->assertInertia(fn (Assert $page) => $page->has('listings', 1));
    $this->get(route('breeding.index', ['pet' => $dam->id, 'kind' => 'listing', 'partner' => $last->id]))->assertInertia(fn (Assert $page) => $page->where('selection.partnerId', $last->id)->where('preview.reason', null)->has('preview.ranges', 6));
});

test('an orphan seller credit cannot be reused to issue a new litter', function () {
    [$owner, $seller, $dam, , $listing] = breedingPair();
    $token = (string) Str::uuid();
    app(PlayerWallet::class)->change($seller, 'coins', 100, 'breeding:'.$token, 'breeding_income');

    expect(fn () => app(StartBreeding::class)->handle($owner, $dam->id, 'listing', $listing->id, 100, $token))->toThrow(BreedingUnavailable::class, 'breeding.errors.token');
    expect($owner->fresh()->coins)->toBe(1000);
    expect($seller->fresh()->coins)->toBe(100);
    $this->assertDatabaseCount('breeding_litters', 0);
    $this->assertDatabaseCount('currency_transactions', 1);
});
