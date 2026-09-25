<?php

use App\Models\Pet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')->where('pet', null)->where('canClaimStarterPet', true)
    );
});

test('the dashboard shows only the current players dog and its saved characteristics', function () {
    $otherPet = Pet::factory()->create();
    $user = User::factory()->create(['locale' => 'ru']);
    $pet = Pet::factory()->for($user)->create([
        'name' => 'Рэй', 'endurance' => 12, 'endurance_potential' => 145,
        'born_at' => '2026-09-01 10:00:00', 'description' => 'Любит прогулки.',
        'is_purebred' => true, 'is_favorite' => false, 'traits' => ['friendly', 'active'],
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('pet.id', $pet->id)
        ->where('pet.name', 'Рэй')
        ->where('pet.coatColor', 'Чёрный')
        ->where('pet.bornAt', '2026-09-01T10:00:00+00:00')
        ->where('pet.description', 'Любит прогулки.')
        ->where('pet.isPurebred', true)
        ->where('pet.isFavorite', false)
        ->where('pet.traits', ['friendly', 'active'])
        ->where('pet.stats.endurance.value', 12)
        ->where('pet.stats.endurance.potential', 145)
        ->where('pet.states.health', 100)
        ->where('canClaimStarterPet', false)
        ->missing('pet.user_id')
    );

    $this->actingAs($otherPet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('pet.id', $otherPet->id)
        ->where('pet.description', null)
        ->where('pet.traits', [])
    );
});
