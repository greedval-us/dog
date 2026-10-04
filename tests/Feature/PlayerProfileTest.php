<?php

use App\Models\AssetUnlock;
use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests must sign in to view a player card', function () {
    $player = User::factory()->create();

    $this->get(route('players.show', $player->username))->assertRedirect(route('login'));
});

test('other players can view the card by username without receiving private account fields', function () {
    $viewer = User::factory()->create();
    $player = User::factory()->create([
        'name' => 'Анна', 'username' => 'anna_dogs', 'bio' => 'Люблю собак.',
        'level' => 7, 'experience' => 4200, 'exhibition_wins' => 3,
        'competition_wins' => 4, 'walks_count' => 31, 'trainings_count' => 12,
        'pet_statistics' => ['care.meal' => 8, 'care.water' => 9, 'care.attention' => 4, 'care.toy' => 6,
            'care.wash' => 3, 'care.care' => 5, 'care.nap' => 2, 'care.sleep' => 7, 'care.walk' => 31,
            'care.home' => 2, 'training' => 10, 'skill_training' => 2, 'work' => 4,
            'veterinary.checkup' => 1, 'veterinary.vaccination' => 1, 'veterinary.treatment' => 2],
        'active_days' => 11, 'last_pet_action_at' => '2026-09-30 18:00:00',
        'coins' => 555, 'gems' => 99,
        'created_at' => '2026-09-01 12:00:00',
    ]);
    Pet::factory()->for($player)->create();
    Pet::factory()->for($player)->retired()->create();
    Pet::factory()->for($viewer)->create();

    $this->actingAs($viewer)->get(route('players.show', $player->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PlayerProfile')
            ->where('isOwner', false)
            ->where('auth.user.id', $viewer->id)
            ->where('player', [
                'name' => 'Анна', 'username' => 'anna_dogs', 'bio' => 'Люблю собак.',
                'avatarVersion' => null,
                'joinedAt' => '2026-09-01',
                'level' => 6, 'experience' => '4200', 'dogsCount' => 1,
                'exhibitionWins' => 3, 'competitionWins' => 4, 'walksCount' => 31, 'trainingsCount' => 12,
                'progress' => ['levelExperience' => '1100', 'requiredExperience' => '3200',
                    'remainingExperience' => '2100', 'percent' => 34.37, 'nextLevel' => 7],
                'statistics' => ['actionsCount' => 97, 'feedingCount' => 8, 'wateringCount' => 9,
                    'playCount' => 10, 'groomingCount' => 8, 'restCount' => 9, 'skillLessonsCount' => 2,
                    'workCount' => 4, 'veterinaryCount' => 4, 'activeDays' => 11,
                    'lastActionAt' => '2026-09-30T18:00:00.000000Z',
                    'competitionStarts' => 0, 'competitionPodiums' => 0, 'competitionWins' => 4,
                    'exhibitionStarts' => 0, 'exhibitionPodiums' => 0, 'exhibitionWins' => 3,
                    'agilityWins' => 0, 'noseworkWins' => 0, 'canicrossWins' => 0, 'conformationWins' => 0,
                    'progenyStarts' => 0, 'progenyWins' => 0, 'weeklyEventWins' => 0, 'monthlyEventWins' => 0,
                    'titlesCount' => 0, 'titledDogsCount' => 0, 'eventPrizeCoins' => 0, 'eventFeesCoins' => 0,
                    'littersStarted' => 0, 'littersBorn' => 0, 'puppiesBorn' => 0, 'puppiesKept' => 0,
                    'puppiesPurchased' => 0, 'puppiesSold' => 0, 'puppySalesCoins' => 0,
                    'titledOffspring' => 0, 'ammunitionPurchases' => 0],
            ])
        )->assertDontSee($player->email);
});

test('a new player can view their own card with initial statistics', function () {
    $player = User::factory()->create();

    $this->actingAs($player)->get(route('players.show', $player->username))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('isOwner', true)
        ->where('player.bio', null)
        ->where('player.level', 1)
        ->where('player.dogsCount', 0)
        ->where('player.experience', '0')
        ->where('player.progress.levelExperience', '0')
        ->where('player.progress.requiredExperience', '100')
        ->where('player.progress.remainingExperience', '100')
        ->where('player.statistics.actionsCount', 0)
        ->where('player.statistics.activeDays', 0)
        ->where('player.statistics.lastActionAt', null)
        ->where('player.exhibitionWins', 0)
        ->where('player.competitionWins', 0)
        ->where('player.walksCount', 0)
        ->where('player.trainingsCount', 0)
        );
});

test('a missing player returns not found', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('players.show', 'missing_player'))->assertNotFound();
});

test('the card counts current ownership after a pet moves or loses its owner', function () {
    $player = User::factory()->create();
    $otherPlayer = User::factory()->create();
    $pet = Pet::factory()->for($player)->create();

    $this->actingAs($player)->get(route('players.show', $player->username))
        ->assertInertia(fn (Assert $page) => $page->where('player.dogsCount', 1));

    $pet->user()->associate($otherPlayer);
    $pet->save();

    $this->get(route('players.show', $player->username))
        ->assertInertia(fn (Assert $page) => $page->where('player.dogsCount', 0));
    $this->get(route('players.show', $otherPlayer->username))
        ->assertInertia(fn (Assert $page) => $page->where('player.dogsCount', 1));

    $pet->user()->dissociate();
    $pet->save();

    $this->get(route('players.show', $otherPlayer->username))
        ->assertInertia(fn (Assert $page) => $page->where('player.dogsCount', 0));
});

test('player dogs show only current active ownership and localized public fields', function (string $locale, string $breedName) {
    $player = User::factory()->create();
    $viewer = User::factory()->create(['locale' => $locale]);
    $dog = Dog::factory()->create([
        'breed' => 'german_shepherd', 'name' => ['ru' => 'Немецкая овчарка', 'en' => 'German Shepherd'],
    ]);
    $pet = Pet::factory()->for($player)->for($dog)->create(['name' => 'Рэй']);
    Pet::factory()->for($player)->retired()->create();
    Pet::factory()->for($viewer)->create();

    $this->actingAs($viewer)->get(route('players.show', $player->username))
        ->assertInertia(fn (Assert $page) => $page->where('dogs', [[
            'id' => $pet->id, 'name' => 'Рэй', 'breed' => $breedName, 'portraitId' => null, 'backgroundId' => null,
        ]]));
})->with([['ru', 'Немецкая овчарка'], ['en', 'German Shepherd']]);

test('player cards show each dogs selected pose and background using the owners purchases', function (bool $viewOwnCard) {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $player = User::factory()->create();
    $viewer = $viewOwnCard ? $player : User::factory()->create();
    $first = Pet::factory()->for($player)->create();
    $second = Pet::factory()->for($player)->create(['dog_id' => $first->dog_id, 'coat_color' => 'brown']);
    GameAsset::factory()->create(['dog_id' => $first->dog_id, 'coat_color' => $first->coat_color]);
    $portrait = GameAsset::factory()->paid()->create([
        'dog_id' => $first->dog_id, 'coat_color' => $first->coat_color, 'pose' => 'sitting',
    ]);
    $secondPortrait = GameAsset::factory()->create(['dog_id' => $second->dog_id, 'coat_color' => $second->coat_color]);
    $secondBackground = GameAsset::factory()->background()->create();
    $background = GameAsset::factory()->background()->paid()->create();
    AssetUnlock::factory()->for($player)->create(['game_asset_id' => $portrait->id]);
    AssetUnlock::factory()->for($player)->create(['game_asset_id' => $background->id]);
    $first->forceFill(['portrait_asset_id' => $portrait->id, 'background_asset_id' => $background->id])->save();
    $second->forceFill(['portrait_asset_id' => $secondPortrait->id, 'background_asset_id' => $secondBackground->id])->save();

    $this->actingAs($viewer)->get(route('players.show', $player->username))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dogs', 2)
        ->where('dogs.0.id', $first->id)
        ->where('dogs.0.portraitId', $portrait->id)
        ->where('dogs.0.backgroundId', $background->id)
        ->where('dogs.1.id', $second->id)
        ->where('dogs.1.portraitId', $secondPortrait->id)
        ->where('dogs.1.backgroundId', $secondBackground->id)
        );
})->with(['owner' => true, 'another player' => false]);

test('player cards use matching free artwork for dogs without a selection', function () {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $pet = Pet::factory()->create(['coat_color' => 'brown']);
    GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => 'black']);
    GameAsset::factory()->create(['coat_color' => $pet->coat_color]);
    $portrait = GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $background = GameAsset::factory()->background()->create();

    $this->actingAs($pet->user)->get(route('players.show', $pet->user->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dogs.0.portraitId', $portrait->id)
            ->where('dogs.0.backgroundId', $background->id)
        );
});

test('a viewers purchases do not unlock the artwork selected by a dogs owner', function () {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $pet = Pet::factory()->create();
    $viewer = User::factory()->create();
    $freePortrait = GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $freeBackground = GameAsset::factory()->background()->create();
    $portrait = GameAsset::factory()->paid()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $background = GameAsset::factory()->background()->paid()->create();
    AssetUnlock::factory()->for($viewer)->create(['game_asset_id' => $portrait->id]);
    AssetUnlock::factory()->for($viewer)->create(['game_asset_id' => $background->id]);
    $pet->forceFill(['portrait_asset_id' => $portrait->id, 'background_asset_id' => $background->id])->save();

    $this->actingAs($viewer)->get(route('players.show', $pet->user->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dogs.0.portraitId', $freePortrait->id)
            ->where('dogs.0.backgroundId', $freeBackground->id)
        );
});

test('unavailable selected artwork falls back to usable free artwork on player cards', function (array $attributes) {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $pet = Pet::factory()->create();
    $freePortrait = GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $freeBackground = GameAsset::factory()->background()->create();
    $portrait = GameAsset::factory()->create([
        'dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color, ...$attributes,
    ]);
    $background = GameAsset::factory()->background()->create($attributes);
    $pet->forceFill(['portrait_asset_id' => $portrait->id, 'background_asset_id' => $background->id])->save();

    $this->actingAs($pet->user)->get(route('players.show', $pet->user->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dogs.0.portraitId', $freePortrait->id)
            ->where('dogs.0.backgroundId', $freeBackground->id)
        );
})->with([
    'inactive' => [['is_active' => false]],
    'missing image' => [['image_path' => 'appearance/missing.png']],
    'invalid price' => [['coins_price' => 0]],
    'incompatible coat' => [['coat_color' => 'another_coat']],
]);
