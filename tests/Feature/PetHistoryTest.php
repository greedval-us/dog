<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\PetHistoryPhrase;
use App\Models\PetThoughtState;
use App\Models\User;
use App\Modules\Pets\Actions\RecordPetThought;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Services\PetHistoryRecorder;
use Database\Seeders\PetHistorySeeder;
use Illuminate\Pagination\Cursor;

test('history shows only the selected owners dog with a week by default and a month on request', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $another = Pet::factory()->for($pet->user)->create();
    $recent = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(2)]);
    $older = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(10)]);
    PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(31)]);
    PetHistoryEntry::factory()->create(['pet_id' => $another->id]);
    PetHistoryEntry::factory()->create();

    $this->actingAs($pet->user)->getJson(route('pets.history.index', $pet))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recent->id)->assertJsonPath('period', 7);
    $this->getJson(route('pets.history.index', ['pet' => $pet, 'period' => 30]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.id', $older->id);
});

test('history has independent action and thought filters and localized snapshots', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    PetHistoryEntry::factory()->create(['pet_id' => $pet->id]);
    $thought = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'kind' => 'thought',
        'event_code' => 'thought.hungry', 'title' => ['ru' => 'Голод', 'en' => 'Hunger'],
        'message' => ['ru' => 'Хозяин, хочу есть!', 'en' => 'Human, I am hungry!'],
        'details' => ['name' => ['ru' => 'Обед', 'en' => 'Lunch']]]);
    $this->actingAs($pet->user)->getJson(route('pets.history.index', ['pet' => $pet, 'kind' => 'thought']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $thought->id)
        ->assertJsonPath('data.0.message', 'Хозяин, хочу есть!');
    $pet->user->forceFill(['locale' => 'en'])->save();

    $this->getJson(route('pets.history.index', ['pet' => $pet, 'kind' => 'thought', 'event' => 'thought.hungry']))
        ->assertOk()->assertJsonPath('data.0.title', 'Hunger')->assertJsonPath('data.0.message', 'Human, I am hungry!')
        ->assertJsonPath('data.0.details.name', 'Lunch')->assertJsonMissingPath('data.0.source_key');
    $this->getJson(route('pets.history.index', ['pet' => $pet, 'event' => 'work']))->assertOk()->assertJsonCount(0, 'data');
});

test('history cursors traverse entries with the same timestamp without missing or repeating them', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $entries = PetHistoryEntry::factory()->count(25)->create(['pet_id' => $pet->id]);
    $first = $this->actingAs($pet->user)->getJson(route('pets.history.index', $pet))->assertOk()->assertJsonCount(20, 'data');
    $second = $this->getJson(route('pets.history.index', ['pet' => $pet, 'cursor' => $first->json('nextCursor')]))
        ->assertOk()->assertJsonCount(5, 'data');
    $back = $this->getJson(route('pets.history.index', ['pet' => $pet, 'cursor' => $second->json('previousCursor')]))->assertOk();

    expect(array_column($first->json('data'), 'id'))->toBe(array_values($entries->reverse()->take(20)->modelKeys()));
    expect(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
    expect($back->json('data'))->toBe($first->json('data'));
});

test('foreign dogs and nonexistent dogs return 404 for reading or generating history', function (string $route, string $method) {
    $pet = Pet::factory()->create();
    $this->actingAs(User::factory()->create())->{$method}(route($route, $pet))->assertNotFound();
    $this->{$method}(route($route, ['pet' => 99999999]))->assertNotFound();
    $this->assertDatabaseCount('pet_history_entries', 0);
})->with([['pets.history.index', 'getJson'], ['pets.thoughts.store', 'postJson']]);

test('guests cannot read or generate dog history', function (string $route, string $method) {
    $pet = Pet::factory()->create();
    $this->{$method}(route($route, $pet))->assertUnauthorized();
})->with([['pets.history.index', 'getJson'], ['pets.thoughts.store', 'postJson']]);

test('invalid history filters return 422', function (array $query) {
    $pet = Pet::factory()->create();
    $this->actingAs($pet->user)->getJson(route('pets.history.index', ['pet' => $pet, ...$query]))->assertUnprocessable();
})->with([
    [['period' => 31]], [['kind' => 'anything']], [['event' => "'; DROP TABLE pets;--"]], [['cursor' => 'invalid']],
    [['cursor' => (new Cursor(['id' => 1, 'occurred_at' => 'bad-date']))->encode()]],
    [['cursor' => (new Cursor(['id' => '1e100', 'occurred_at' => '2026-10-03 12:00:00']))->encode()]],
]);

test('thoughts use projected needs without saving decay and respect priority and cooldown', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['satiety' => 50, 'satiety_max' => 100, 'state_updated_at' => now()->subHours(5)]);
    $event = PetHistoryEvent::factory()->thought()->create(['priority' => 100]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id]);
    $lower = PetHistoryEvent::factory()->thought()->create(['priority' => 1]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $lower->id]);

    $this->actingAs($pet->user)->postJson(route('pets.thoughts.store', $pet))->assertNoContent();
    $this->postJson(route('pets.thoughts.store', $pet))->assertNoContent();

    $this->assertDatabaseCount('pet_history_entries', 1);
    $this->assertDatabaseHas('pet_history_entries', ['pet_history_event_id' => $event->id]);
    expect($pet->refresh()->satiety)->toBe(50.0);
});

test('thought phrases cycle in the configured order and retain text snapshots after catalogue edits', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['satiety' => 10, 'satiety_max' => 100]);
    $event = PetHistoryEvent::factory()->thought()->create(['cooldown_minutes' => 15]);
    $second = PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id, 'sort_order' => 2, 'text' => ['ru' => 'Вторая']]);
    $first = PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id, 'sort_order' => 1, 'text' => ['ru' => 'Первая']]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id, 'sort_order' => 3, 'is_active' => false]);
    $action = app(RecordPetThought::class);
    $one = $action->handle($pet->user, $pet->id);
    $this->travel(15)->minutes();
    $two = $action->handle($pet->user, $pet->id);
    $this->travel(15)->minutes();
    $three = $action->handle($pet->user, $pet->id);
    $first->update(['text' => ['ru' => 'Изменённая']]);

    expect([$one->message, $two->message, $three->message])->toBe([['ru' => 'Первая'], ['ru' => 'Вторая'], ['ru' => 'Первая']]);
    expect($one->fresh()->message)->toBe(['ru' => 'Первая']);
    $this->assertDatabaseHas('pet_thought_states', ['pet_id' => $pet->id, 'pet_history_phrase_id' => $first->id]);
});

test('new database conditions and phrases work without changing the application code', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['mood' => 95, 'mood_max' => 100, 'activity' => PetActivity::Play, 'activity_ends_at' => now()->addHour()]);
    $event = PetHistoryEvent::factory()->thought()->create(['conditions' => [
        ['field' => 'mood', 'operator' => 'gte', 'value' => 90], ['field' => 'activity', 'operator' => 'eq', 'value' => 'play'],
        ['field' => 'has_disease', 'operator' => 'eq', 'value' => false],
    ]]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id, 'text' => ['ru' => 'Новая фраза из базы']]);

    $this->actingAs($pet->user)->postJson(route('pets.thoughts.store', $pet))->assertNoContent();

    $this->assertDatabaseHas('pet_history_entries', ['event_code' => $event->code]);
    expect(PetHistoryEntry::query()->sole()->message)->toBe(['ru' => 'Новая фраза из базы']);
});

test('retired pets and blocked owners do not produce new thoughts', function (string $case) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['satiety' => 5]);
    $event = PetHistoryEvent::factory()->thought()->create();
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $event->id]);
    if ($case === 'retired') {
        $pet->update(['retired_at' => now()]);
    } else {
        $pet->user->forceFill(['status' => 'blocked'])->save();
    }

    $this->actingAs($pet->user)->postJson(route('pets.thoughts.store', $pet))->assertNoContent();

    $this->assertDatabaseCount('pet_history_entries', 0);
})->with(['retired', 'blocked']);

test('inactive and malformed thought rules are skipped instead of breaking the scheduler', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['satiety' => 5]);
    $inactive = PetHistoryEvent::factory()->thought()->create(['is_active' => false]);
    $invalid = PetHistoryEvent::factory()->thought()->create(['conditions' => [['operator' => 'lt', 'value' => 100]]]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $inactive->id]);
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $invalid->id]);

    $this->artisan('pets:maintain-history')->assertSuccessful();

    $this->assertDatabaseCount('pet_history_entries', 0);
});

test('thought maintenance skips an unprocessed registration and continues with other owners', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['satiety' => 5]);
    $other = Pet::factory()->create(['satiety' => 5]);
    $event = GameEvent::factory()->create(['closes_at' => now()]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create();
    $thought = PetHistoryEvent::factory()->thought()->create();
    PetHistoryPhrase::factory()->create(['pet_history_event_id' => $thought->id]);
    $before = $pet->fresh()->getAttributes();

    $this->artisan('pets:maintain-history')->assertSuccessful();

    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($entry->fresh()->snapshot)->toBeNull();
    $this->assertDatabaseMissing('pet_history_entries', ['pet_id' => $pet->id]);
    $this->assertDatabaseHas('pet_history_entries', ['pet_id' => $other->id, 'event_code' => $thought->code]);
});

test('maintenance prunes only history older than 30 days and keeps action receipts and phrase memory', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $old = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(30)->subSecond()]);
    $boundary = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(30)]);
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id]);
    $event = PetHistoryEvent::factory()->thought()->create();
    $state = PetThoughtState::query()->create(['pet_id' => $pet->id, 'pet_history_event_id' => $event->id, 'last_occurred_at' => now()->subDays(31)]);

    $this->artisan('pets:maintain-history --prune-only')->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelExists($boundary);
    $this->assertModelExists($receipt);
    $this->assertModelExists($state);
});

test('catalogue seeding can be repeated without overwriting custom phrases', function () {
    $this->seed(PetHistorySeeder::class);
    $phrase = PetHistoryPhrase::query()->firstOrFail();
    $phrase->update(['text' => ['ru' => 'Своя фраза'], 'is_active' => false]);

    $this->seed(PetHistorySeeder::class);

    $this->assertDatabaseCount('pet_history_events', 43);
    $this->assertDatabaseCount('pet_history_phrases', 75);
    expect($phrase->fresh()->text)->toBe(['ru' => 'Своя фраза']);
    expect($phrase->fresh()->is_active)->toBeFalse();
});

test('expired delayed completions are not recreated in retained history', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    PetHistoryEvent::factory()->create(['code' => 'care.meal']);

    $result = app(PetHistoryRecorder::class)->record($pet, 'care.meal', 'old:completed', now()->subDays(31));

    expect($result)->toBeNull();
    $this->assertDatabaseCount('pet_history_entries', 0);
});
