<?php

use App\Models\AssetUnlock;
use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests must sign in to visit a pet memorial', function (bool $detail) {
    $pet = Pet::factory()->retired()->create();
    $route = $detail
        ? route('players.memorial.show', ['user' => $pet->user->username, 'pet' => $pet])
        : route('players.memorial.index', $pet->user->username);

    $this->get($route)->assertRedirect(route('login'));
})->with(['list' => false, 'card' => true]);

test('signed in players can open a memorial with the current email verification configuration', function () {
    $player = User::factory()->unverified()->create();

    $this->actingAs($player)->get(route('players.memorial.index', $player->username))
        ->assertOk();
});

test('the public memorial lists only the named players deceased pets', function (string $locale, string $breedName) {
    $viewer = User::factory()->create(['locale' => $locale]);
    $owner = User::factory()->create(['username' => 'remembered_dogs']);
    $dog = Dog::factory()->create(['name' => ['ru' => 'Овчарка', 'en' => 'Shepherd']]);
    $retired = Pet::factory()->for($owner)->for($dog)->retired()->create([
        'name' => 'Рэй', 'born_at' => now()->subDays(120), 'retired_at' => now(),
    ]);
    $deceased = Pet::factory()->for($owner)->for($dog)->create([
        'name' => 'Луна', 'health' => 0, 'born_at' => '2026-07-01 12:00:00', 'died_at' => '2026-10-02 12:00:00',
    ]);
    Pet::factory()->for($owner)->for($dog)->create();
    Pet::factory()->for($viewer)->retired()->create();

    $this->actingAs($viewer)->get(route('players.memorial.index', ['user' => $owner->username, 'user_id' => $viewer->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('PetMemorial')
            ->where('isOwner', false)
            ->where('player.username', $owner->username)
            ->where('pets', [
                'data' => [
                    ['id' => $deceased->id, 'name' => 'Луна', 'breed' => $breedName,
                        'portraitId' => null, 'backgroundId' => null, 'bornAt' => '2026-07-01T12:00:00+00:00',
                        'archivedAt' => '2026-10-02T12:00:00+00:00', 'status' => 'deceased'],
                ],
                'nextCursor' => null, 'previousCursor' => null,
            ])
        )->assertDontSee($owner->email);
})->with([['ru', 'Овчарка'], ['en', 'Shepherd']]);

test('memorial cursor pagination keeps older pets without leaking active or other owners pets', function () {
    $player = User::factory()->create();
    $dog = Dog::factory()->create();
    $pets = Pet::factory()->for($player)->for($dog)->deceased()->count(13)->create();
    Pet::factory()->for($player)->for($dog)->create();
    Pet::factory()->for($dog)->deceased()->create();

    $first = $this->actingAs($player)->get(route('players.memorial.index', $player->username));
    $first->assertInertia(fn (Assert $page) => $page
        ->where('isOwner', true)->has('pets.data', 12)
        ->where('pets.data.0.id', $pets[12]->id)->where('pets.data.11.id', $pets[1]->id)
        ->where('pets.previousCursor', null)
    );
    $second = $this->get(route('players.memorial.index', ['user' => $player->username, 'cursor' => $first->inertiaProps('pets.nextCursor')]));
    $second->assertInertia(fn (Assert $page) => $page
        ->has('pets.data', 1)->where('pets.data.0.id', $pets[0]->id)->where('pets.nextCursor', null)
    );
    $this->get(route('players.memorial.index', ['user' => $player->username, 'cursor' => $second->inertiaProps('pets.previousCursor')]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pets.data', 12)->where('pets.data.0.id', $pets[12]->id)->where('pets.previousCursor', null)
        );
});

test('an empty memorial does not fabricate pets and missing players return not found', function () {
    $player = User::factory()->create();

    $this->actingAs($player)->get(route('players.memorial.index', $player->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pets', ['data' => [], 'nextCursor' => null, 'previousCursor' => null])
        );
    $this->get(route('players.memorial.index', 'missing_player'))->assertNotFound();
});

test('visiting an offline players memorial keeps seniors active while listing only deaths', function () {
    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(12, 0));
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $old = Pet::factory()->for($owner)->create(['born_at' => now()->subDays(120)]);
    $dead = Pet::factory()->for($owner)->create(['born_at' => '2026-08-01 12:00:00', 'health' => 0]);
    $active = Pet::factory()->for($owner)->create();

    $this->actingAs($viewer)->get(route('players.memorial.index', $owner->username))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pets.data', 1)
            ->where('pets.data.0.id', $dead->id)->where('pets.data.0.status', 'deceased')
            ->where('player.dogsCount', 2)
        );
    $this->assertDatabaseHas('pets', ['id' => $old->id, 'retired_at' => null, 'died_at' => null]);
    $this->assertDatabaseHas('pets', ['id' => $dead->id, 'died_at' => '2026-10-03 12:00:00']);
    $this->get(route('players.show', $owner->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('player.dogsCount', 2)->has('dogs', 2)->where('dogs.0.id', $old->id)->where('dogs.1.id', $active->id)
        );
});

test('an archived pet card preserves states attributes and learned levels across time', function (string $status) {
    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(12, 0));
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Старый друг', 'born_at' => '2026-05-01 12:00:00',
        'retired_at' => $status === 'deceased_after_retirement' ? '2026-10-01 12:00:00' : null,
        'died_at' => '2026-10-01 12:00:00',
        'state_updated_at' => '2026-10-01 12:00:00', 'stats_updated_at' => '2026-10-01 12:00:00',
        'health' => 0, 'health_max' => 100,
        'satiety' => 42, 'satiety_max' => 100, 'energy' => 17,
        'strength' => 43, 'strength_potential' => 80,
    ]);
    $skill = Skill::factory()->create(['name' => ['ru' => 'Найти предмет'], 'is_active' => false]);
    $pet->skills()->attach($skill, ['level' => 3, 'experience' => 45]);
    Skill::factory()->create();
    $snapshot = $pet->fresh()->getAttributes();

    $first = $this->actingAs($viewer)->get(route('players.memorial.show', ['user' => $owner->username, 'pet' => $pet]));
    $first->assertInertia(fn (Assert $page) => $page
        ->component('PetMemorialShow')
        ->where('isOwner', false)
        ->where('pet.id', $pet->id)->where('pet.name', 'Старый друг')
        ->where('pet.states.satiety', 42)
        ->where('pet.energy.value', 17)
        ->where('pet.stats.strength', ['value' => 43, 'potential' => 80])
        ->where('pet.lifecycle.status', 'deceased')
        ->where('pet.lifecycle.canRetire', false)
        ->where('appearance', ['assets' => [], 'portraitId' => null, 'backgroundId' => null])
        ->where('learnedSkills', [['id' => $skill->id, 'name' => 'Найти предмет', 'description' => '', 'level' => 3]])
        ->missing('pet.user_id')->missing('pet.activity_token')->missing('pet.buffs')
    )->assertDontSee($owner->email);

    $this->travel(45)->days();
    $this->get(route('players.memorial.show', ['user' => $owner->username, 'pet' => $pet]))
        ->assertInertia(fn (Assert $page) => $page->where('pet', $first->inertiaProps('pet')));
    expect($pet->fresh()->getAttributes())->toBe($snapshot);
})->with(['deceased_after_retirement', 'deceased']);

test('an active pet and a different owners pet cannot be opened through a memorial detail link', function () {
    $owner = User::factory()->create();
    $active = Pet::factory()->for($owner)->create();
    $other = Pet::factory()->deceased()->create();

    $this->actingAs($owner)->get(route('players.memorial.show', ['user' => $owner->username, 'pet' => $active]))
        ->assertNotFound();
    $this->get(route('players.memorial.show', ['user' => $owner->username, 'pet' => $other]))
        ->assertNotFound();
});

test('archived cards use selected artwork purchased by the owner', function () {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $pet = Pet::factory()->deceased()->create();
    $portrait = GameAsset::factory()->paid()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $background = GameAsset::factory()->background()->paid()->create();
    AssetUnlock::factory()->for($pet->user)->create(['game_asset_id' => $portrait->id]);
    AssetUnlock::factory()->for($pet->user)->create(['game_asset_id' => $background->id]);
    $pet->forceFill(['portrait_asset_id' => $portrait->id, 'background_asset_id' => $background->id])->save();

    $this->actingAs(User::factory()->create())->get(route('players.memorial.show', ['user' => $pet->user->username, 'pet' => $pet]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appearance', ['assets' => [], 'portraitId' => $portrait->id, 'backgroundId' => $background->id])
        );
});
