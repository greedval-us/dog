<?php

use App\Models\CharacterTrait;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Services\PetActivityManager;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the dashboard shows stored energy and the pets own maximum after spending energy', function (float $initial, int|float $remaining, int|float $percentage) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => $initial, 'energy_max' => 120]);
    app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Walk, now()->addHour(), energyCost: 10);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pet.energy.value', $remaining)
        ->where('pet.energy.maximum', 120)
        ->where('pet.states.energy', $percentage)
    );
})->with(['partly spent' => [50.5, 40.5, 33.8], 'fully spent' => [10.0, 0, 0]]);

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
        'is_purebred' => true, 'is_favorite' => false,
    ]);
    $traits = CharacterTrait::factory()->count(2)->sequence(['code' => 'friendly'], ['code' => 'active'])->create();
    $pet->characterTraits()->attach($traits->modelKeys());

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
