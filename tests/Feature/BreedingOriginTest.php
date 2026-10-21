<?php

use App\Models\BreedingLitter;
use App\Models\BreedingPartner;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Actions\KeepPuppy;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Actions\StartBreeding;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Queries\GetPetBreedingOrigin;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeSecond();
});

test('a mating permanently identifies the dam breeder instead of the puppy recipient', function (string $sex, string $partnerSex) {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $breed = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    $owner = User::factory()->create(['experience' => '1500', 'coins' => 1000]);
    $stats = [];
    foreach (PetStat::cases() as $stat) {
        $stats[$stat->value] = 90;
    }
    $pet = Pet::factory()->for($owner)->for($breed)->create(['sex' => $sex, ...$stats]);
    $partner = BreedingPartner::query()->whereHas('pet', fn ($pets) => $pets->where('dog_id', $breed->id)->where('sex', $partnerSex))->firstOrFail();

    $litter = app(StartBreeding::class)->handle($owner, $pet->id, 'partner', $partner->id, 100, (string) Str::uuid());

    $expected = $sex === 'female'
        ? ['breeder_key' => 'player:'.$owner->id, 'kennel_key' => 'player:'.$owner->id, 'kennel_name' => 'DogLive #'.$owner->id]
        : ['breeder_key' => 'system:'.$partner->pet_id, 'kennel_key' => 'system:'.$partner->pet_id, 'kennel_name' => 'DogLive System #'.$partner->pet_id];
    expect($litter->fresh()->snapshots['breeding_origin'] ?? null)->toEqual($expected);
})->with([
    'player dam with system sire' => ['female', 'male'],
    'system dam with player sire' => ['male', 'female'],
]);

test('a placed puppy retains its original kennel after resale and breeder account deletion', function () {
    $breeder = User::factory()->create(['pet_slots' => 3]);
    $buyer = User::factory()->create(['coins' => 1000]);
    $mother = Pet::factory()->female()->for($breeder)->create();
    $father = Pet::factory()->for($mother->dog)->create();
    $origin = ['breeder_key' => 'player:'.$breeder->id, 'kennel_key' => 'player:'.$breeder->id, 'kennel_name' => 'DogLive #'.$breeder->id];
    $litter = BreedingLitter::factory()->create([
        'own_pet_id' => $mother->id, 'mother_id' => $mother->id, 'father_id' => $father->id, 'initiator_id' => $breeder->id,
        'born_at' => now()->subMinute(), 'delivered_at' => now()->subMinute(), 'snapshots' => ['breeding_origin' => $origin],
    ]);
    $puppy = Puppy::factory()->for($litter, 'litter')->for($breeder)->create(['status' => 'pending']);
    $placement = app(KeepPuppy::class)->handle($breeder, $puppy->id, 'Origin', (string) Str::uuid());
    $listing = app(PublishPetSale::class)->handle($breeder, $placement->pet_id, 100);

    $receipt = app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid());
    $breeder->delete();

    expect(app(GetPetBreedingOrigin::class)->forPet(Pet::query()->findOrFail($receipt->pet_id)))->toBe($origin);
    $this->assertDatabaseHas('pets', ['id' => $placement->pet_id, 'user_id' => $buyer->id]);
});

test('batch origins require a delivered source puppy with matching pedigree and a saved identity', function () {
    $origin = ['breeder_key' => 'player:42', 'kennel_key' => 'player:42', 'kennel_name' => 'DogLive #42'];
    $knownLitter = BreedingLitter::factory()->create(['delivered_at' => now(), 'snapshots' => ['breeding_origin' => $origin]]);
    $known = Pet::factory()->for($knownLitter->mother->dog)->create(['father_id' => $knownLitter->father_id, 'mother_id' => $knownLitter->mother_id]);
    Puppy::factory()->for($knownLitter, 'litter')->create(['pet_id' => $known->id, 'status' => 'placed']);
    $mismatched = Pet::factory()->for($known->dog)->create();
    Puppy::factory()->for($knownLitter, 'litter')->create(['pet_id' => $mismatched->id, 'status' => 'placed']);
    $undeliveredLitter = BreedingLitter::factory()->create(['snapshots' => ['breeding_origin' => $origin]]);
    $undelivered = Pet::factory()->for($undeliveredLitter->mother->dog)->create(['father_id' => $undeliveredLitter->father_id, 'mother_id' => $undeliveredLitter->mother_id]);
    Puppy::factory()->for($undeliveredLitter, 'litter')->create(['pet_id' => $undelivered->id, 'status' => 'placed']);
    $legacyLitter = BreedingLitter::factory()->create(['delivered_at' => now()]);
    $unknown = Pet::factory()->for($legacyLitter->mother->dog)->create(['father_id' => $legacyLitter->father_id, 'mother_id' => $legacyLitter->mother_id]);
    Puppy::factory()->for($legacyLitter, 'litter')->create(['pet_id' => $unknown->id, 'status' => 'placed']);
    $query = app(GetPetBreedingOrigin::class);

    $origins = $query->forPets([$known->id, $mismatched->id, $undelivered->id, $unknown->id]);

    expect($origins)->toBe([$known->id => $origin]);
    expect($query->forPet($unknown))->toBeNull();
    expect($query->forPets([]))->toBe([]);
});

test('historical origins are backfilled only when the litter proves the original dam owner', function () {
    $breeder = User::factory()->create();
    $laterOwner = User::factory()->create();
    $mother = Pet::factory()->female()->for($laterOwner)->create();
    $proved = BreedingLitter::factory()->create([
        'own_pet_id' => $mother->id, 'mother_id' => $mother->id, 'initiator_id' => $breeder->id,
        'snapshots' => ['colors' => ['black' => 100]],
    ]);
    $unknown = BreedingLitter::factory()->create();
    $unidentified = BreedingLitter::factory()->create(['own_pet_id' => $mother->id, 'mother_id' => $mother->id, 'initiator_id' => null]);
    $existingOrigin = ['breeder_key' => 'system:73', 'kennel_key' => 'system:73', 'kennel_name' => 'DogLive System #73'];
    $identified = BreedingLitter::factory()->create([
        'own_pet_id' => $mother->id, 'mother_id' => $mother->id,
        'snapshots' => ['breeding_origin' => $existingOrigin],
    ]);
    $migration = require database_path('migrations/2026_10_21_184442_backfill_provable_breeding_origins.php');

    $migration->up();
    $migration->up();
    $breeder->delete();

    expect($proved->fresh()->snapshots)->toEqual([
        'colors' => ['black' => 100],
        'breeding_origin' => ['breeder_key' => 'player:'.$breeder->id, 'kennel_key' => 'player:'.$breeder->id, 'kennel_name' => 'DogLive #'.$breeder->id],
    ]);
    expect($unknown->fresh()->snapshots)->toBe([]);
    expect($unidentified->fresh()->snapshots)->toBe([]);
    expect($identified->fresh()->snapshots['breeding_origin'])->toEqual($existingOrigin);
});
