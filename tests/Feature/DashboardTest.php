<?php

use App\Models\CharacterTrait;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Services\PetActivityManager;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('dashboard batches care and skills while appearance and polling stay independent', function () {
    $pet = Pet::factory()->create();
    $this->actingAs($pet->user);
    DB::enableQueryLog();

    $initial = $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('pet.id', $pet->id)->has('slots')->missing('care')->missing('skills')->missing('appearance')
    );
    $initialQueries = implode(' ', array_column(DB::getQueryLog(), 'query'));
    expect($initialQueries)->not->toContain('pet_care_actions', 'inventory_items', 'game_assets', 'status_effects');
    DB::flushQueryLog();

    $this->get(route('dashboard'), [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'pet,care',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->assertOk()->assertJsonPath('props.pet.id', $pet->id)->assertJsonCount(10, 'props.care.options')
        ->assertJsonMissingPath('props.care.items')->assertJsonMissingPath('props.appearance')
        ->assertJsonMissingPath('props.slots')->assertJsonMissingPath('props.canClaimStarterPet');
    $pollQueries = implode(' ', array_column(DB::getQueryLog(), 'query'));
    DB::disableQueryLog();
    expect($pollQueries)->not->toContain('inventory_items', 'game_assets', 'pet_slots');

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps('appearance', fn (Assert $deferred) => $deferred->has('appearance')->missing('care'))
        ->loadDeferredProps('care', fn (Assert $deferred) => $deferred->has('care')->has('skills')->missing('appearance'))
    );
});

test('the dashboard shows stored energy and the pets own maximum after spending energy', function (float $initial, int|float $remaining, int|float $percentage) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => $initial, 'energy_max' => 100]);
    app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Walk, now()->addHour(), energyCost: 10);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pet.energy.value', $remaining)
        ->where('pet.energy.maximum', 100)
        ->where('pet.states.energy', $percentage)
    );
})->with(['partly spent' => [50.5, 40.5, 40.5], 'fully spent' => [10.0, 0, 0]]);

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
