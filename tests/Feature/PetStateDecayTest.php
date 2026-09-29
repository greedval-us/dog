<?php

use App\Models\Pet;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('dashboard projects offline decay and recovery without writing or counting reads twice', function () {
    $pet = Pet::factory()->create([
        'state_updated_at' => now()->subHours(2),
        'health' => 100, 'health_max' => 100, 'energy' => 10, 'energy_max' => 100,
        'satiety' => 160, 'satiety_max' => 200, 'hydration' => 240, 'hydration_max' => 300,
        'mood' => 80, 'mood_max' => 100, 'cleanliness' => 80, 'cleanliness_max' => 100,
        'bond' => 80, 'bond_max' => 100,
    ]);

    $this->actingAs($pet->user);
    foreach (range(1, 2) as $request) {
        $this->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
            ->where('pet.states.satiety', 70)->where('pet.states.hydration', 70)
            ->where('pet.states.mood', 76)->where('pet.states.cleanliness', 76)
            ->where('pet.states.bond', 79)->where('pet.states.health', 100)
            ->where('pet.states.energy', 20)->where('pet.energy.value', 20));
    }

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'satiety' => 160, 'energy' => 10, 'state_updated_at' => $pet->state_updated_at->toDateTimeString()]);
});

test('care preview and start use regenerated energy and persist elapsed time once', function () {
    $pet = Pet::factory()->create([
        'state_updated_at' => now()->subHours(2), 'energy' => 0, 'energy_max' => 100,
        'satiety' => 100, 'satiety_max' => 100, 'hydration' => 100, 'hydration_max' => 100,
        'mood' => 100, 'mood_max' => 100, 'cleanliness' => 100, 'cleanliness_max' => 100,
    ]);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('care.options.4.reason', null)));

    $payload = ['variant' => 'attention', 'items' => [], 'token' => $token];
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 4, 'satiety' => 90, 'state_updated_at' => now()->toDateTimeString()]);
    $this->assertDatabaseCount('pet_care_actions', 1);
});

test('care preview rejects activity based on decayed needs and leaves the snapshot untouched', function () {
    $pet = Pet::factory()->create(['state_updated_at' => now()->subHours(2), 'satiety' => 15, 'satiety_max' => 100]);
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('pet.states.satiety', 5)
        ->where('care.options.4.reason', 'Feed your dog and offer water before active play or a walk.')));

    $this->post(route('pets.care.store', $pet), ['variant' => 'attention', 'items' => [], 'token' => (string) Str::uuid()])->assertSessionHasErrors('care');

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'satiety' => 15, 'state_updated_at' => $pet->state_updated_at->toDateTimeString()]);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('finishing care settles decay before restoring water and retries never repeat either effect', function () {
    $pet = Pet::factory()->create(['hydration' => 25, 'hydration_max' => 100, 'health' => 100, 'health_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travel(2)->hours();

    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 50, 'health' => 95, 'activity' => null, 'state_updated_at' => now()->toDateTimeString()]);
    $this->travel(1)->hours();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('pet.states.hydration', 45)->where('pet.states.health', 95));
});

test('failed completion rolls back decay together with the care effect', function () {
    $pet = Pet::factory()->create(['hydration' => 25, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $savedAt = $pet->fresh()->state_updated_at;
    $this->travel(2)->hours();
    $this->rejectCareWrites('reject_decay_completion', 'UPDATE');

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 25, 'state_updated_at' => $savedAt->toDateTimeString(), 'activity_token' => $care->activity_token]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
});

test('retired pets and future snapshots do not accumulate elapsed state changes', function (bool $retired) {
    $pet = Pet::factory()->create([
        'satiety' => 80, 'satiety_max' => 100,
        'retired_at' => $retired ? now()->subDay() : null,
        'state_updated_at' => $retired ? now()->subDays(2) : now()->addHour(),
    ]);

    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page->where('pet.states.satiety', 80));
})->with([true, false]);

test('configured rates and thresholds control health loss and energy recovery', function () {
    config(['pet_states.decay_per_hour' => ['satiety' => 4, 'hydration' => 3, 'mood' => 1, 'cleanliness' => 1, 'bond' => 0.25],
        'pet_states.health_threshold' => 80, 'pet_states.health_loss_per_hour' => 10,
        'pet_states.energy_threshold' => 60, 'pet_states.energy_recovery_per_hour' => 8]);
    $pet = Pet::factory()->create([
        'state_updated_at' => now()->subHours(2), 'health' => 70, 'health_max' => 100, 'energy' => 0, 'energy_max' => 100,
        'satiety' => 70, 'satiety_max' => 100, 'hydration' => 100, 'hydration_max' => 100,
        'mood' => 100, 'mood_max' => 100, 'cleanliness' => 100, 'cleanliness_max' => 100, 'bond' => 100, 'bond_max' => 100,
    ]);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('pet.states.satiety', 62)->where('pet.states.hydration', 94)
        ->where('pet.states.mood', 98)->where('pet.states.cleanliness', 98)->where('pet.states.bond', 99.5)
        ->where('pet.states.health', 50)->where('pet.states.energy', 8));
});
