<?php

use App\Models\BreedingPartner;
use App\Models\CharacterTrait;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Queries\GetPetPedigree;
use App\Modules\Pets\Queries\GetPetProfile;
use App\Modules\Pets\Queries\GetPrimaryPet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests must sign in to view a public dog card or pedigree', function (string $routeName) {
    $pet = Pet::factory()->create();

    $this->get(route($routeName, $pet))->assertRedirect(route('login'));
})->with(['card' => 'pets.show', 'pedigree' => 'pets.pedigree']);

test('missing public dog cards and pedigrees return not found', function (string $routeName) {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route($routeName, 999999999))->assertNotFound();
})->with(['card' => 'pets.show', 'pedigree' => 'pets.pedigree']);

test('malformed dog identifiers return not found before database lookup', function (string $routeName, string $identifier) {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route($routeName, $identifier))->assertNotFound();
})->with([
    ['pets.show', 'not-a-number'], ['pets.pedigree', 'not-a-number'],
    ['pets.show', '999999999999999999999'], ['pets.pedigree', '999999999999999999999'],
]);

test('other players can read localized dog information without owner secrets or care controls', function (string $locale, string $breed, string $coat) {
    $this->freezeTime();
    $viewer = User::factory()->create(['locale' => $locale]);
    $owner = User::factory()->create(['email' => 'private.owner@example.com', 'coins' => 918273]);
    $dog = Dog::factory()->create([
        'name' => ['ru' => 'Овчарка', 'en' => 'Shepherd'],
        'coat_colors' => ['black' => ['ru' => 'Чёрный', 'en' => 'Black']],
    ]);
    $father = Pet::factory()->for($dog)->create(['user_id' => null]);
    $pet = Pet::factory()->for($owner)->for($dog)->female()->create([
        'name' => 'Луна', 'description' => 'Любит долгие прогулки.', 'father_id' => $father->id,
        'coat_color' => 'black', 'generation' => 2, 'strength' => 38, 'strength_potential' => 107,
        'activity_token' => 'd945e3bc-86da-4fbb-a1a8-736c30cd152a',
    ]);
    $trait = CharacterTrait::factory()->create(['code' => 'curious']);
    $pet->characterTraits()->attach($trait);

    $response = $this->actingAs($viewer)->get(route('pets.show', $pet));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('PetProfile')
        ->where('profile.id', $pet->id)->where('profile.name', 'Луна')
        ->where('profile.breed', $breed)->where('profile.coatColor', $coat)
        ->where('profile.sex', 'female')->where('profile.generation', 2)
        ->where('profile.description', 'Любит долгие прогулки.')
        ->where('profile.bornAt', $pet->born_at->toIso8601String())
        ->where('profile.traits', ['curious'])
        ->where('profile.stats.strength', ['value' => 38, 'potential' => 107])
        ->has('profile.stats', 6)->where('profile.lifecycle.status', 'active')
        ->where('profile.hasPedigree', true)
        ->missing('profile.user_id')->missing('profile.user')->missing('profile.coins')
        ->missing('profile.activity_token')->missing('profile.buffs')
        ->missing('profile.states')->missing('profile.energy')->missing('profile.lifecycle.canRetire')
    )->assertDontSee('private.owner@example.com')->assertDontSee('d945e3bc-86da-4fbb-a1a8-736c30cd152a');
    expect(array_keys($response->inertiaProps('profile')))->toBe([
        'id', 'name', 'breed', 'sex', 'size', 'coatColor', 'generation', 'description',
        'bornAt', 'isPurebred', 'traits', 'stats', 'lifecycle', 'hasPedigree',
        'exterior', 'titles',
    ]);
})->with([['ru', 'Овчарка', 'Чёрный'], ['en', 'Shepherd', 'Black']]);

test('dogs without recorded parents have an honest empty pedigree', function () {
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create(['user_id' => null, 'name' => 'Первый']);

    $this->actingAs($viewer)->get(route('pets.pedigree', $pet))
        ->assertInertia(fn (Assert $page) => $page
            ->component('PetPedigree')->where('pedigree.hasAncestors', false)
            ->where('pedigree.generations', 3)->where('pedigree.root.pet.id', $pet->id)
            ->where('pedigree.root.pet.name', 'Первый')->where('pedigree.root.pet.hasPedigree', false)
            ->where('pedigree.root.father', null)->where('pedigree.root.mother', null)
        );
});

test('a partial pedigree preserves the known parent and leaves the other branch empty', function () {
    $viewer = User::factory()->create(['locale' => 'ru']);
    $dog = Dog::factory()->create();
    $father = Pet::factory()->for($dog)->retired()->create(['user_id' => null, 'name' => 'Отец']);
    $pet = Pet::factory()->for($dog)->create(['user_id' => null, 'father_id' => $father->id]);

    $this->actingAs($viewer)->get(route('pets.pedigree', $pet))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedigree.hasAncestors', true)->where('pedigree.root.pet.hasPedigree', true)
            ->where('pedigree.root.father.pet.id', $father->id)->where('pedigree.root.father.pet.name', 'Отец')
            ->where('pedigree.root.father.pet.status', 'retired')
            ->where('pedigree.root.father.father', null)->where('pedigree.root.father.mother', null)
            ->where('pedigree.root.mother', null)
        );
});

test('a full tree includes three generations and lets the oldest shown ancestor retain its own pedigree', function () {
    $this->freezeTime();
    $viewer = User::factory()->create();
    $dog = Dog::factory()->create();
    $root = makePedigreeTree($dog, 3);
    $father = $root->father;
    $grandfather = $father->father;
    $greatGrandfather = $grandfather->father;
    $older = Pet::factory()->for($dog)->create(['user_id' => null]);
    $greatGrandfather->update(['father_id' => $older->id]);

    $this->actingAs($viewer)->get(route('pets.pedigree', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedigree.root.father.pet.id', $father->id)
            ->where('pedigree.root.father.father.pet.id', $grandfather->id)
            ->where('pedigree.root.father.father.father.pet.id', $greatGrandfather->id)
            ->where('pedigree.root.father.father.father.pet.hasPedigree', true)
            ->where('pedigree.root.father.father.father.father', null)
            ->where('pedigree.root.father.father.father.mother', null)
            ->has('pedigree.root.mother.father.father.pet')
            ->has('pedigree.root.mother.mother.mother.pet')
        );
    $this->get(route('pets.pedigree', $greatGrandfather))
        ->assertInertia(fn (Assert $page) => $page->where('pedigree.root.father.pet.id', $older->id));
});

test('shared ancestors appear in both pedigree branches', function () {
    $viewer = User::factory()->create();
    $dog = Dog::factory()->create();
    $shared = Pet::factory()->for($dog)->create(['user_id' => null]);
    $father = Pet::factory()->for($dog)->create(['user_id' => null, 'father_id' => $shared->id]);
    $mother = Pet::factory()->for($dog)->female()->create(['user_id' => null, 'father_id' => $shared->id]);
    $root = Pet::factory()->for($dog)->create(['user_id' => null, 'father_id' => $father->id, 'mother_id' => $mother->id]);

    $this->actingAs($viewer)->get(route('pets.pedigree', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedigree.root.father.father.pet.id', $shared->id)
            ->where('pedigree.root.mother.father.pet.id', $shared->id)
        );
});

test('malformed cyclic ancestry stops each affected branch while preserving valid parents', function () {
    $viewer = User::factory()->create();
    $dog = Dog::factory()->create();
    $father = Pet::factory()->for($dog)->create(['user_id' => null]);
    $root = Pet::factory()->for($dog)->create(['user_id' => null, 'father_id' => $father->id]);
    $root->update(['mother_id' => $root->id]);
    $father->update(['father_id' => $root->id]);

    $this->actingAs($viewer)->get(route('pets.pedigree', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedigree.root.father.pet.id', $father->id)
            ->where('pedigree.root.father.father', null)->where('pedigree.root.mother', null)
        );
});

test('archived dogs retain their frozen characteristics in public cards', function (string $status) {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => null, 'born_at' => '2026-02-01 12:00:00',
        'retired_at' => $status === 'retired' ? '2026-09-01 12:00:00' : null,
        'died_at' => $status === 'deceased' ? '2026-09-01 12:00:00' : null,
        'strength' => 73, 'strength_potential' => 119,
        'stats_updated_at' => '2026-09-01 12:00:00', 'state_updated_at' => '2026-09-01 12:00:00',
    ]);
    $snapshot = $pet->fresh()->getAttributes();

    $this->actingAs($viewer)->get(route('pets.show', $pet))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.stats.strength', ['value' => 73, 'potential' => 119])
            ->where('profile.lifecycle', ['status' => $status, 'archivedAt' => '2026-09-01T12:00:00+00:00'])
        );
    expect($pet->fresh()->getAttributes())->toBe($snapshot);
})->with(['retired', 'deceased']);

test('historical kennel parents keep their fixed characteristics without aging in cards or trees', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    $viewer = User::factory()->create();
    $partner = Pet::factory()->create([
        'user_id' => null, 'born_at' => '2025-01-01 12:00:00',
        'strength' => 70, 'strength_potential' => 100,
        'state_updated_at' => '2025-01-01 12:00:00', 'stats_updated_at' => '2025-01-01 12:00:00',
    ]);
    BreedingPartner::factory()->create(['pet_id' => $partner->id, 'is_active' => false]);
    $child = Pet::factory()->for($partner->dog)->create(['user_id' => null, 'father_id' => $partner->id]);
    $snapshot = $partner->fresh()->getAttributes();

    $this->actingAs($viewer)->get(route('pets.show', $partner))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.stats.strength', ['value' => 70, 'potential' => 100])
            ->where('profile.lifecycle.status', 'active')
        );
    $this->get(route('pets.pedigree', $child))
        ->assertInertia(fn (Assert $page) => $page->where('pedigree.root.father.pet.status', 'active'));
    expect($partner->fresh()->getAttributes())->toBe($snapshot);
});

test('foreign cards and trees project overdue lifecycle transitions without writing the dog', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    $viewer = User::factory()->create();
    $father = Pet::factory()->create([
        'born_at' => '2026-04-04 12:00:00', 'stats_updated_at' => '2026-10-03 12:00:00',
        'state_updated_at' => '2026-10-04 12:00:00', 'strength' => 100, 'strength_potential' => 120,
    ]);
    $child = Pet::factory()->for($father->dog)->create(['user_id' => null, 'father_id' => $father->id]);
    $snapshot = $father->fresh()->getAttributes();

    $this->actingAs($viewer)->get(route('pets.show', $father))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.lifecycle.status', 'retired')
            ->where('profile.lifecycle.archivedAt', '2026-10-04T12:00:00+00:00')
            ->where('profile.stats.strength.value', fn (int $value): bool => $value < 100)
        );
    $this->get(route('pets.pedigree', $child))
        ->assertInertia(fn (Assert $page) => $page->where('pedigree.root.father.pet.status', 'retired'));
    expect($father->fresh()->getAttributes())->toBe($snapshot);
});

test('pedigree reads batch each generation instead of querying every ancestor', function () {
    $this->freezeTime();
    $dog = Dog::factory()->create();
    $root = makePedigreeTree($dog, 3);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $tree = app(GetPetPedigree::class)->handle($root->id, 'en');
        $queries = DB::getQueryLog();

        expect($tree['hasAncestors'])->toBeTrue();
        expect(count($queries))->toBeLessThanOrEqual(9);
        foreach ($queries as $query) {
            expect(strtolower(ltrim($query['query'])))->toStartWith('select');
        }
    } finally {
        DB::disableQueryLog();
    }
});

test('the existing owner card exposes pedigree availability alongside the new public card', function () {
    $this->freezeTime();
    $owner = User::factory()->create();
    $dog = Dog::factory()->create();
    $parent = Pet::factory()->for($dog)->create(['user_id' => null]);
    $child = Pet::factory()->for($owner)->for($dog)->create(['mother_id' => $parent->id]);
    $unrelated = Pet::factory()->for($owner)->for($dog)->create();

    expect(app(GetPrimaryPet::class)->handle($owner, 'en', $child->id)->toArray()['hasPedigree'])->toBeTrue();
    expect(app(GetPrimaryPet::class)->handle($owner, 'en', $unrelated->id)->toArray()['hasPedigree'])->toBeFalse();
    expect(app(GetPetProfile::class)->handle($child->id, 'en')['hasPedigree'])->toBeTrue();
});

function makePedigreeTree(Dog $dog, int $generations): Pet
{
    if ($generations === 0) {
        return Pet::factory()->for($dog)->create(['user_id' => null]);
    }

    $father = makePedigreeTree($dog, $generations - 1);
    $mother = makePedigreeTree($dog, $generations - 1);

    return Pet::factory()->for($dog)->create([
        'user_id' => null, 'father_id' => $father->id, 'mother_id' => $mother->id,
        'generation' => $generations + 1,
    ]);
}
