<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('event registration and calendar require authentication', function () {
    $event = GameEvent::factory()->create();

    $this->get(route('game-events.index'))->assertRedirect(route('login'));
    $this->post(route('game-events.register', $event))->assertRedirect(route('login'));
    $this->assertDatabaseCount('game_event_entries', 0);
});

test('the calendar filters monthly shows and does not expose the event random seed', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('game-events.index', ['frequency' => 'monthly', 'kind' => 'exhibition']))
        ->assertInertia(fn (Assert $page) => $page->component('GameEvents')
            ->has('events', 4)->where('events.0.frequency', 'monthly')
            ->where('filters.kind', 'exhibition')->missing('events.0.seed')
            ->where('events.0.participationRules', ['playerDailyLimit' => 3, 'petDailyLimit' => 2, 'petRestHours' => 2])
            ->missing('dogs')->missing('equipment')->missing('disciplines'));
});

test('event participation rules use server configuration and remain present during partial polling', function () {
    $this->freezeTime();
    config(['game-events.daily_limit' => 7, 'game-events.pet_daily_limit' => 5, 'game-events.pet_rest_hours' => 4]);
    $user = User::factory()->create();
    $event = GameEvent::factory()->create();

    $initial = $this->actingAs($user)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->component('GameEventShow')
            ->where('event.participationRules', ['playerDailyLimit' => 7, 'petDailyLimit' => 5, 'petRestHours' => 4]));

    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'GameEventShow', 'X-Inertia-Partial-Data' => 'event,entry,serverNow',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('game-events.show', $event))
        ->assertJsonPath('props.event.participationRules', ['playerDailyLimit' => 7, 'petDailyLimit' => 5, 'petRestHours' => 4])
        ->assertJsonMissingPath('props.dogs')->assertJsonMissingPath('props.equipment');
});

test('an entry can be registered edited and cancelled with its full fee returned', function () {
    $this->freezeTime();
    $user = User::factory()->create(['coins' => 200]);
    $pet = Pet::factory()->for($user)->create();
    $event = GameEvent::factory()->create();
    $payload = ['pet_id' => $pet->id, 'fee' => 25, 'token' => (string) Str::uuid(), 'plan' => ['stages' => ['careful', 'balanced', 'bold']], 'gear_ids' => []];

    $this->actingAs($user)->post(route('game-events.register', $event), $payload)->assertRedirect(route('game-events.show', $event));
    $entry = GameEventEntry::query()->sole();
    expect($user->fresh()->coins)->toBe(175);
    $this->put(route('game-events.update', $event), ['plan' => ['stages' => ['bold', 'careful', 'balanced']], 'gear_ids' => []])->assertRedirect();
    expect($entry->fresh()->plan['stages'])->toBe(['bold', 'careful', 'balanced']);
    $this->post(route('game-events.cancel', $event))->assertRedirect();
    $this->post(route('game-events.cancel', $event))->assertRedirect();

    expect($user->fresh()->coins)->toBe(200);
    expect($entry->fresh()->status)->toBe('cancelled');
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->get(route('game-events.show', $event))->assertInertia(fn (Assert $page) => $page->where('entry.status', 'cancelled')->where('event.canRegister', false));
});

test('another player cannot register a dog or change its entry', function () {
    $this->freezeTime();
    $owner = User::factory()->create(['coins' => 200]);
    $other = User::factory()->create(['coins' => 200]);
    $pet = Pet::factory()->for($owner)->create();
    $event = GameEvent::factory()->create();
    $payload = ['pet_id' => $pet->id, 'fee' => 25, 'token' => (string) Str::uuid(), 'plan' => ['stages' => ['balanced', 'balanced', 'balanced']], 'gear_ids' => []];

    $this->actingAs($other)->post(route('game-events.register', $event), $payload)->assertNotFound();
    $this->put(route('game-events.update', $event), $payload)->assertNotFound();
    $this->post(route('game-events.cancel', $event))->assertNotFound();

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($other->fresh()->coins)->toBe(200);
});

test('invalid stage decisions are rejected without charging an entry fee', function () {
    $user = User::factory()->create(['coins' => 200]);
    $pet = Pet::factory()->for($user)->create();
    $event = GameEvent::factory()->create();

    $this->actingAs($user)->post(route('game-events.register', $event), [
        'pet_id' => $pet->id, 'fee' => 25, 'token' => (string) Str::uuid(),
        'plan' => ['stages' => ['impossible', 'bold', 'careful']], 'gear_ids' => [],
    ])->assertSessionHasErrors('plan.stages.0');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('invalid preparation updates preserve the registered plan and equipment', function () {
    $this->freezeTime();
    $user = User::factory()->create(['coins' => 200]);
    $entry = GameEventEntry::factory()->for($user)->create();
    $originalPlan = $entry->plan;

    $this->actingAs($user)->put(route('game-events.update', $entry->event), [
        'plan' => ['stages' => ['impossible', 'bold', 'careful']], 'gear_ids' => [10, 10],
    ])->assertSessionHasErrors(['plan.stages.0', 'gear_ids.0', 'gear_ids.1']);

    expect($entry->fresh()->plan)->toBe($originalPlan);
    expect($entry->fresh()->gear_ids)->toBe([]);
    expect($user->fresh()->coins)->toBe(200);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('closed entry operations return a localized error without changing the entry or wallet', function (string $method, string $route) {
    $this->freezeTime();
    $user = User::factory()->create(['coins' => 200]);
    $event = GameEvent::factory()->create(['status' => 'frozen']);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($user)->create(['status' => 'frozen']);
    $payload = ['pet_id' => $entry->pet_id, 'fee' => 25, 'token' => (string) Str::uuid(), 'plan' => ['stages' => ['balanced', 'balanced', 'balanced']], 'gear_ids' => []];

    $this->actingAs($user)->call($method, route($route, $event), $payload)
        ->assertSessionHasErrors(['event' => __('events.errors.closed')]);

    expect($entry->fresh()->status)->toBe('frozen');
    expect($user->fresh()->coins)->toBe(200);
    $this->assertDatabaseCount('game_event_entries', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'registration' => ['POST', 'game-events.register'],
    'preparation update' => ['PUT', 'game-events.update'],
    'cancellation' => ['POST', 'game-events.cancel'],
]);

test('the event page presents frozen replay stages and clearly marks club participants', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $event = GameEvent::factory()->create(['status' => 'settled']);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($user)->create([
        'status' => 'completed', 'rank' => 1, 'prize' => 100,
        'snapshot' => ['name' => 'Рей'],
        'result' => ['version' => 2, 'score' => -160, 'time' => 160, 'penalties' => 0, 'eliminated' => false, 'stages' => [
            ['key' => 'technical', 'decision' => 'careful', 'time' => 55, 'score' => -55, 'penalties' => 0, 'fatigue' => 16, 'focus' => 90, 'note' => 'controlled'],
        ]],
    ]);
    GameEventEntry::factory()->for($event, 'event')->create([
        'is_npc' => true, 'user_id' => null, 'pet_id' => null, 'fee' => 0,
        'status' => 'completed', 'rank' => 2, 'snapshot' => ['name' => 'Клубная собака №1'],
    ]);

    $this->actingAs($user)->get(route('game-events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->component('GameEventShow')
            ->where('entry.id', $entry->id)->where('entry.result.stages.0.decision', 'careful')
            ->where('entry.result.stages.0.reason', __('events.notes.controlled'))
            ->where('event.entries.1.isNpc', true)->where('event.entries.1.ownerName', null)
            ->missing('event.seed')->missing('event.entries.0.snapshot'));
});
