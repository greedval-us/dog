<?php

use App\Models\DogWorkShift;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('running frozen events do not reacquire participant locks before settlement is due', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $event = GameEvent::factory()->create([
        'status' => 'frozen', 'closes_at' => now()->subMinutes(20), 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(5),
    ]);
    GameEventEntry::factory()->for($event, 'event')->for($user)->create(['status' => 'frozen']);
    GameEventEntry::factory()->for($event, 'event')->create(['status' => 'frozen']);
    DB::enableQueryLog();

    $processed = app(GameEventProcessor::class)->processForOwner($user);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect([
        'processed' => $processed,
        'queries' => count($queries),
        'lockingQueries' => count(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'for update'))),
    ])->toBe(['processed' => 0, 'queries' => 1, 'lockingQueries' => 0]);
    expect($event->fresh()->status)->toBe('frozen');
});

test('direct lifecycle and gameplay calls preserve a registration snapshot before overdue retirement', function (string $operation) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'born_at' => now()->subMonthsNoOverflow(6)->subHour(),
        'state_updated_at' => now()->subHours(3), 'stats_updated_at' => now()->subHours(3),
    ]);
    $event = GameEvent::factory()->create([
        'closes_at' => now()->subHours(2), 'starts_at' => now()->subMinutes(105), 'ends_at' => now()->subMinutes(95),
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $before = $pet->fresh()->getAttributes();
    $invoke = match ($operation) {
        'lifecycle' => fn () => app(PetLifecycle::class)->synchronizeOwner($owner),
        'care' => fn () => app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], (string) Str::uuid()),
        'retirement' => fn () => app(RetirePet::class)->handle($owner, $pet->id),
    };

    expect($invoke)->toThrow(PendingGameEventRegistration::class, 'events.errors.processing');

    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($entry->fresh()->snapshot)->toBeNull();
    expect($event->fresh()->status)->toBe('registration');
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('pet_history_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['lifecycle', 'care', 'retirement']);

test('lifecycle ignores future registrations and registrations belonging to another owner', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(7)]);
    $future = GameEvent::factory()->create();
    GameEventEntry::factory()->for($future, 'event')->for($owner)->for($pet)->create();
    $other = GameEvent::factory()->create([
        'closes_at' => now()->subMinute(), 'starts_at' => now()->addMinutes(14), 'ends_at' => now()->addMinutes(24),
    ]);
    GameEventEntry::factory()->for($other, 'event')->create();

    app(PetLifecycle::class)->synchronizeOwner($owner);

    expect($pet->fresh()->retired_at)->toEqual($pet->automaticRetirementAt());
    expect($future->fresh()->status)->toBe('registration');
    expect($other->fresh()->status)->toBe('registration');
});

test('direct completion cannot advance a dog when registration closes exactly now', function (string $operation) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $receipt = match ($operation) {
        'care' => PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $owner->id, 'ends_at' => now()->subMinutes(5)]),
        'work' => DogWorkShift::factory()->create([
            'pet_id' => $pet->id, 'user_id' => $owner->id, 'started_at' => now()->subHour(), 'ends_at' => now()->subMinutes(5),
        ]),
    };
    $pet->forceFill([
        'activity' => $operation === 'care' ? PetActivity::Play : PetActivity::Work,
        'activity_token' => $receipt->activity_token, 'activity_started_at' => now()->subHour(), 'activity_ends_at' => $receipt->ends_at,
    ])->save();
    $event = GameEvent::factory()->create(['closes_at' => now()]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $before = $pet->fresh()->getAttributes();
    $invoke = match ($operation) {
        'care' => fn () => app(CompletePetCare::class)->handle($owner, $pet->id, $receipt->token),
        'work' => fn () => app(CompleteDogWork::class)->handle($owner, $receipt->token, now()->subMinute()),
    };

    expect($invoke)->toThrow(PendingGameEventRegistration::class, 'events.errors.processing');

    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($receipt->fresh()->completed_at)->toBeNull();
    expect($receipt->fresh()->cancelled_at)->toBeNull();
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_history_entries', 0);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => 0]);
})->with(['care', 'work']);

test('a future lifecycle cutoff cannot pass a scheduled registration before it closes', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(6)->addHour()]);
    $event = GameEvent::factory()->create();
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $before = $pet->fresh()->getAttributes();

    expect(fn () => app(PetLifecycle::class)->synchronizeOwner($owner, $event->ends_at))
        ->toThrow(PendingGameEventRegistration::class, 'events.errors.processing');

    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseCount('pet_history_entries', 0);
});

test('gameplay rechecks registration when it closes while acquiring the write lock', function (string $operation) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(4)]);
    $event = GameEvent::factory()->create();
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $before = $pet->fresh()->getAttributes();
    $ownerLocks = 0;
    DB::listen(function (QueryExecuted $query) use ($event, &$ownerLocks): void {
        if (str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'for update') && ++$ownerLocks === 2) {
            $this->travelTo($event->closes_at);
        }
    });
    $invoke = match ($operation) {
        'care' => fn () => app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], (string) Str::uuid()),
        'retirement' => fn () => app(RetirePet::class)->handle($owner, $pet->id),
    };

    expect($invoke)->toThrow(PendingGameEventRegistration::class, 'events.errors.processing');

    expect($ownerLocks)->toBeGreaterThanOrEqual(2);
    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('pet_history_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['care', 'retirement']);

test('HTTP presents a registration that closes inside the action as a processing refusal', function (bool $inertia) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(4)]);
    $event = GameEvent::factory()->create();
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $before = $pet->fresh()->getAttributes();
    $ownerLocks = 0;
    DB::listen(function (QueryExecuted $query) use ($event, &$ownerLocks): void {
        if (str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'for update') && ++$ownerLocks === 2) {
            $this->travelTo($event->closes_at);
        }
    });
    $this->actingAs($owner);

    if ($inertia) {
        $this->from(route('dashboard'))->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->post(route('pets.retire', $pet))->assertStatus(303)->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors(['event' => __('events.errors.processing')]);
    } else {
        $this->postJson(route('pets.retire', $pet))->assertStatus(503)
            ->assertHeader('Retry-After', '60')->assertJsonPath('message', __('events.errors.processing'));
    }

    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseCount('pet_history_entries', 0);
})->with(['JSON' => false, 'Inertia' => true]);

test('lifecycle maintenance skips a newly closed registration and continues with other owners', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(7)]);
    $other = Pet::factory()->create(['born_at' => now()->subMonthsNoOverflow(7)]);
    $event = GameEvent::factory()->create();
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    $closed = false;
    DB::listen(function (QueryExecuted $query) use ($event, &$closed): void {
        if (! $closed && str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'for update')) {
            $closed = true;
            $this->travelTo($event->closes_at);
        }
    });

    $this->artisan('pets:sync-lifecycle')->assertSuccessful();

    expect($closed)->toBeTrue();
    expect($pet->fresh()->retired_at)->toBeNull();
    expect($entry->fresh()->snapshot)->toBeNull();
    expect($other->fresh()->retired_at)->toEqual($other->automaticRetirementAt());
});

test('ordinary catalogues do not settle events or advance dog lifetimes', function (string $route) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(7)]);
    $event = GameEvent::factory()->create(['closes_at' => now()->subHour(), 'starts_at' => now()->subMinutes(45), 'ends_at' => now()->subMinutes(35)]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();
    DB::enableQueryLog();

    $this->actingAs($owner)->get(route($route))->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'for update')))->toBe([]);
    expect($pet->fresh()->retired_at)->toBeNull();
    expect($entry->fresh()->snapshot)->toBeNull();
    expect($event->fresh()->status)->toBe('registration');
})->with(['shop.index', 'inventory.index']);

test('HTTP backlog guards preserve historical snapshots until the scheduler freezes entries before retirement', function (string $page, bool $otherViewer, int $status) {
    $this->freezeSecond();
    $deadline = now()->subHour();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'born_at' => $deadline->subMonthsNoOverflow(6), 'state_updated_at' => now()->subHours(3), 'stats_updated_at' => now()->subHours(3),
        'health' => 100, 'health_max' => 100, 'energy' => 100, 'energy_max' => 100, 'hydration' => 100, 'satiety' => 100,
    ]);
    $event = GameEvent::factory()->create(['closes_at' => now()->subHours(2), 'starts_at' => now()->subMinutes(105), 'ends_at' => now()->subMinutes(95)]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();

    $viewer = $otherViewer ? User::factory()->create() : $owner;
    $parameters = match ($page) {
        'dashboard' => [],
        'players.memorial.show' => ['user' => $owner->username, 'pet' => $pet],
        default => ['user' => $owner->username],
    };
    $this->actingAs($viewer)->get(route($page, $parameters))->assertStatus($status);
    $this->actingAs($owner);
    $this->postJson(route('pets.care.store', $pet), [])->assertStatus(503)
        ->assertHeader('Retry-After', '60')->assertJsonPath('message', __('events.errors.processing'));

    expect($pet->fresh()->retired_at)->toBeNull();
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->artisan('pets:sync-lifecycle')->assertSuccessful();
    expect($event->fresh()->status)->toBe('settled');
    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->fresh()->snapshot['name'])->toBe($pet->name);
    expect($pet->fresh()->retired_at)->toEqual($deadline);
})->with([
    'dashboard' => ['dashboard', false, 200],
    'own profile' => ['players.show', false, 200],
    'another player profile' => ['players.show', true, 200],
    'own achievements' => ['players.achievements', false, 200],
    'another player achievements' => ['players.achievements', true, 200],
    'own memorial' => ['players.memorial.index', false, 200],
    'another player memorial' => ['players.memorial.index', true, 200],
    'active pet memorial card' => ['players.memorial.show', true, 404],
]);

test('Inertia mutations show the localized backlog reason without changing the registration', function (string $action, string $method, string $page) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $event = GameEvent::factory()->create(['closes_at' => now()->subMinute()]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create();

    $returnUrl = route($page, $page === 'game-events.show' ? ['gameEvent' => $event] : []);
    $actionUrl = route($action, $action === 'game-events.update' ? ['gameEvent' => $event] : []);
    $this->actingAs($owner)->from($returnUrl)
        ->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html'])
        ->{$method}($actionUrl, [])
        ->assertStatus(303)->assertRedirect($returnUrl)
        ->assertSessionHasErrors(['event' => __('events.errors.processing')]);
    $this->flushHeaders()->get($returnUrl)->assertInertia(fn (Assert $response) => $response
        ->hasFlash('toast.type', 'error')->hasFlash('toast.message', __('events.errors.processing')));

    expect($entry->fresh()->status)->toBe('registered');
    expect($entry->fresh()->snapshot)->toBeNull();
    expect($event->fresh()->status)->toBe('registration');
})->with([
    'event plan' => ['game-events.update', 'put', 'game-events.show'],
    'kennel adoption' => ['kennel.store', 'post', 'kennel.index'],
    'breeding listing' => ['breeding.listings.store', 'post', 'breeding.index'],
]);

test('the event scheduler drains more than one batch without revisiting running frozen events', function () {
    $this->freezeSecond();
    config(['game-events.processing.background_batch_size' => 2]);
    $events = collect(range(1, 5))->map(fn (int $index): GameEvent => GameEvent::factory()->create([
        'closes_at' => now()->subHours($index), 'starts_at' => now()->subHours($index)->addMinutes(15), 'ends_at' => now()->subHours($index)->addMinutes(25),
    ]));
    $running = GameEvent::factory()->create([
        'status' => 'frozen', 'closes_at' => now()->subMinutes(20), 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(5),
    ]);

    $this->artisan('events:process')->assertSuccessful();

    expect(GameEvent::query()->whereIn('id', $events->pluck('id'))->where('status', 'cancelled')->count())->toBe(5);
    expect($running->fresh()->status)->toBe('frozen');
});

test('standalone lifecycle maintenance freezes shared events in global chronological order', function () {
    $this->freezeSecond();
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstPet = Pet::factory()->for($firstOwner)->create([
        'born_at' => now()->subDays(10), 'state_updated_at' => now()->subHours(4), 'stats_updated_at' => now()->subHours(4),
    ]);
    $secondPet = Pet::factory()->for($secondOwner)->create([
        'born_at' => now()->subDays(10), 'state_updated_at' => now()->subHours(4), 'stats_updated_at' => now()->subHours(4),
        'hydration' => 100, 'hydration_max' => 100, 'satiety' => 100, 'satiety_max' => 100, 'health' => 100, 'health_max' => 100,
    ]);
    $early = GameEvent::factory()->create(['closes_at' => now()->subHours(3), 'starts_at' => now()->subMinutes(165), 'ends_at' => now()->subMinutes(155)]);
    $shared = GameEvent::factory()->create(['closes_at' => now()->subHours(2), 'starts_at' => now()->subMinutes(105), 'ends_at' => now()->subMinutes(95)]);
    $earlyEntry = GameEventEntry::factory()->for($early, 'event')->for($secondOwner)->for($secondPet)->create();
    GameEventEntry::factory()->for($shared, 'event')->for($firstOwner)->for($firstPet)->create();
    GameEventEntry::factory()->for($shared, 'event')->for($secondOwner)->for($secondPet)->create();

    $this->artisan('pets:sync-lifecycle')->assertSuccessful();

    expect($earlyEntry->fresh()->status)->toBe('completed');
    expect($earlyEntry->fresh()->snapshot['states']['hydration'])->toEqual(95);
    expect($early->fresh()->status)->toBe('settled');
    expect($shared->fresh()->status)->toBe('settled');
});

test('event partial polling reads state without registration catalogues or lifecycle locks', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $event = GameEvent::factory()->create();
    $initial = $this->actingAs($owner)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->has('dogs', 1)->has('equipment')->where('serverNow', now()->toIso8601String()));
    DB::enableQueryLog();

    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'GameEventShow', 'X-Inertia-Partial-Data' => 'event,entry,serverNow',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('game-events.show', $event))->assertOk()
        ->assertJsonPath('props.event.id', $event->id)->assertJsonPath('props.entry', null)
        ->assertJsonPath('props.serverNow', now()->toIso8601String())->assertJsonMissingPath('props.dogs')->assertJsonMissingPath('props.equipment');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'for update')
        || str_contains($query['query'], 'from "inventory_items"') || str_contains($query['query'], '"father_id"') || str_contains($query['query'], '"mother_id"')))->toBe([]);
});

test('the scheduler continues when another worker settled the selected batch first', function () {
    $this->freezeSecond();
    config(['game-events.processing.background_batch_size' => 2]);
    $events = collect(range(1, 3))->map(fn (int $index): GameEvent => GameEvent::factory()->create([
        'closes_at' => now()->subHours($index), 'starts_at' => now()->subHours($index)->addMinutes(15), 'ends_at' => now()->subHours($index)->addMinutes(25),
    ]));
    $racedIds = $events->sortBy('starts_at')->take(2)->pluck('id');
    $raced = false;
    DB::listen(function (QueryExecuted $query) use ($racedIds, &$raced): void {
        if (! $raced && str_starts_with($query->sql, 'select "id" from "game_events"')) {
            $raced = true;
            GameEvent::query()->whereIn('id', $racedIds)->update(['status' => 'cancelled']);
        }
    });

    $this->artisan('events:process')->assertSuccessful();

    expect($raced)->toBeTrue();
    expect(GameEvent::query()->whereIn('id', $events->pluck('id'))->where('status', 'cancelled')->count())->toBe(3);
});

test('partial calendar polling does not create upcoming schedules', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $event = GameEvent::factory()->create();
    $initial = $this->actingAs($owner)->get(route('game-events.show', $event))->assertOk();

    $this->actingAs($owner)->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'GameEvents', 'X-Inertia-Partial-Data' => 'events,serverNow',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('game-events.index'))->assertOk();

    $this->assertDatabaseCount('game_events', 1);
});
