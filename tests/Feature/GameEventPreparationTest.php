<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Services\GameEventAdmission;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('preparation explains current care and inherited training potential using the performance calculation', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'speed' => 70, 'agility' => 70, 'speed_potential' => 140, 'agility_potential' => 140,
        'health' => 100, 'health_max' => 100, 'energy' => 100, 'energy_max' => 100,
        'hydration' => 50, 'hydration_max' => 100, 'satiety' => 100, 'satiety_max' => 100,
    ]);
    Pet::factory()->create();
    $event = GameEvent::factory()->create();

    $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->has('dogs', 1)
            ->where('dogs.0.id', $pet->id)
            ->where('dogs.0.preparation.careMultiplier', 0.972)
            ->where('dogs.0.preparation.initialFatigue', 6)
            ->where('dogs.0.preparation.stats.speed', 70)
            ->where('dogs.0.preparation.potentials.speed', 140)
            ->where('dogs.0.preparation.normalizedStats.speed', 50)
            ->where('dogs.0.preparation.stages.0.weights', ['speed' => 0.6, 'agility' => 0.4])
            ->where('dogs.0.preparation.stages.0.quality', 48.6)
            ->where('dogs.0.preparation.blockingReasons', [])
            ->missing('event.seed'));

    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    $snapshot = app(GameEventAdmission::class)->snapshot($event, $pet->fresh(), $plan, []);
    $result = app(GameEventSimulator::class)->simulate('agility', $snapshot, $plan, $event->rules, array_fill(0, 6, 0.9));

    expect($result['stages'][0]['factors']['quality'])->toBe(48.6);
    expect($result['preparation']['careMultiplier'])->toBe(0.972);
});

test('preparation exposes health energy and conflicting activity before a player pays', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'health' => 50, 'health_max' => 100, 'energy' => 10, 'energy_max' => 100,
        'activity' => PetActivity::Sleep, 'activity_started_at' => now(), 'activity_ends_at' => $event->ends_at,
    ]);

    $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('dogs.0.preparation.blockingReasons', [
            __('events.errors.health'), __('events.errors.energy'), __('events.errors.busy'),
        ]));

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $event->id, $pet->id,
        ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.health');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
});

test('preparation and direct registration reject a dog retiring exactly when the event ends', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'born_at' => $event->ends_at->subMonthsNoOverflow(6), 'health' => 100, 'health_max' => 100,
        'energy' => 100, 'energy_max' => 100,
    ]);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $event);

    expect($dogs[0]['preparation']['blockingReasons'])->toBe([__('events.errors.archived', [], 'en')]);
    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $event->id, $pet->id,
        ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.archived');
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
});

test('direct registration checks a disease acquired after an eligible preparation preview', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['health' => 100, 'energy' => 100]);
    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $event);
    expect($dogs[0]['preparation']['blockingReasons'])->toBe([]);
    PetDisease::factory()->for($pet)->create();

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $event->id, $pet->id,
        ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.health');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
});

test('an activity ending before registration closes is not presented as a conflict', function () {
    $owner = User::factory()->create();
    $event = GameEvent::factory()->create();
    Pet::factory()->for($owner)->create([
        'health' => 100, 'energy' => 100,
        'activity' => PetActivity::Sleep, 'activity_started_at' => now(), 'activity_ends_at' => $event->closes_at->subMinute(),
    ]);

    $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('dogs.0.isBusy', true)->where('dogs.0.preparation.blockingReasons', []));
});

test('documentary progeny judging does not tell the player to restore the parent for admission', function () {
    $owner = User::factory()->create();
    Pet::factory()->for($owner)->retired()->create(['health' => 40, 'energy' => 0, 'hydration' => 0, 'satiety' => 0]);
    $event = GameEvent::factory()->create(['discipline' => 'progeny']);

    $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('dogs.0.preparation.blockingReasons', [])
            ->where('dogs.0.preparation.careMultiplier', 1)->where('dogs.0.preparation.initialFatigue', 0));
});

test('participants default to the players heat and other heats can be inspected without exposing private plans', function () {
    $owner = User::factory()->create();
    $event = GameEvent::factory()->create();
    $own = GameEventEntry::factory()->for($event, 'event')->for($owner)->create(['division' => 'novice:small:heat-2']);
    $opponent = GameEventEntry::factory()->for($event, 'event')->create(['division' => 'novice:small']);
    GameEventEntry::factory()->for($event, 'event')->create(['division' => 'novice:small:heat-10']);
    GameEventEntry::factory()->for($event, 'event')->create(['division' => 'novice:small', 'status' => 'cancelled']);

    $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('event.activeDivision', 'novice:small:heat-2')
            ->where('event.humanCount', 3)->where('event.clubCount', 0)->where('event.entryCount', 3)
            ->has('event.divisions', 3)->where('event.divisions.1.key', 'novice:small:heat-2')
            ->has('event.entries', 1)->where('event.entries.0.id', $own->id));
    $this->get(route('game-events.show', ['gameEvent' => $event, 'division' => 'novice:small']))
        ->assertInertia(fn (Assert $page) => $page->where('event.activeDivision', 'novice:small')
            ->has('event.entries', 1)->where('event.entries.0.id', $opponent->id)->where('entry.id', $own->id)
            ->where('event.entries.0.plan.stages', [])->missing('event.entries.0.operation_token')->missing('event.entries.0.snapshot'));
    $this->get(route('game-events.show', ['gameEvent' => $event, 'division' => 'not-a-real-heat']))
        ->assertInertia(fn (Assert $page) => $page->where('event.activeDivision', 'novice:small:heat-2'));
});

test('adding dogs to preparation does not add a query for each dogs skills or sport record', function () {
    $owner = User::factory()->create();
    $event = GameEvent::factory()->create();
    Pet::factory()->for($owner)->count(4)->create();
    $queries = [];
    $diseaseQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries, &$diseaseQueries): void {
        if (str_contains($query->sql, 'from "skills"') || str_contains($query->sql, 'from "pet_sport_records"')) {
            $queries[] = $query->sql;
        }
        if (str_contains($query->sql, 'from "pet_diseases"')) {
            $diseaseQueries[] = $query->sql;
        }
    });

    $this->actingAs($owner)->get(route('game-events.show', $event))->assertInertia(fn (Assert $page) => $page->has('dogs', 4));

    expect($queries)->toHaveCount(2);
    expect($diseaseQueries)->toHaveCount(1);
});
