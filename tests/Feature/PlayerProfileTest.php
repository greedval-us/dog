<?php

use App\Models\Pet;
use App\Models\User;
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
        'coins' => 555, 'gems' => 99,
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
                'level' => 7, 'experience' => 4200, 'dogsCount' => 2,
                'exhibitionWins' => 3, 'competitionWins' => 4, 'walksCount' => 31, 'trainingsCount' => 12,
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
        ->where('player.experience', 0)
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
