<?php

use App\Models\DogWorkShift;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Actions\CancelGameEventEntry;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Actions\UpdateGameEventEntry;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use App\Modules\Pets\Services\PetEventReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function gameEventPlan(): array
{
    return ['stages' => ['balanced', 'careful', 'bold']];
}

function registerDogEvent(Pet $pet, GameEvent $event, array $gear = []): GameEventEntry
{
    return app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, gameEventPlan(), $gear, $event->rules['fee'], (string) Str::uuid());
}

test('registration replay survives plan changes and cannot charge or reuse a token for other contents', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $pet->user->update(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $token = (string) Str::uuid();
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, gameEventPlan(), [], 25, $token);
    app(UpdateGameEventEntry::class)->handle($pet->user, $entry->id, ['stages' => ['careful', 'careful', 'careful']], []);

    $replay = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, gameEventPlan(), [], 25, strtoupper($token));

    expect($replay->id)->toBe($entry->id);
    expect($replay->plan['stages'])->toBe(['careful', 'careful', 'careful']);
    expect(fn () => app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, gameEventPlan(), [], 26, $token))->toThrow(GameEventUnavailable::class);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('cancellation refunds once and closes exactly fifteen minutes before start', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $pet->user->update(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);

    app(CancelGameEventEntry::class)->handle($pet->user, $entry->id);
    app(CancelGameEventEntry::class)->handle($pet->user, $entry->id);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $otherPet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $second = registerDogEvent($otherPet, $event);
    $this->travelTo($event->closes_at);
    expect(fn () => app(CancelGameEventEntry::class)->handle($otherPet->user, $second->id))->toThrow(GameEventUnavailable::class);
    expect(fn () => app(UpdateGameEventEntry::class)->handle($otherPet->user, $second->id, gameEventPlan(), []))->toThrow(GameEventUnavailable::class);
});

test('freeze fills a human division with marked NPCs and settlement is durable across retries', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['bond' => 100, 'energy' => 100]);
    $pet->user->update(['coins' => 500]);
    $event = GameEvent::factory()->create(['seed' => 'fixed-event-seed']);
    $entry = registerDogEvent($pet, $event);
    $processor = app(GameEventProcessor::class);
    $this->travelTo($event->closes_at);

    $processor->processForOwner($pet->user);

    $this->assertDatabaseCount('game_event_entries', 8);
    expect(GameEventEntry::query()->where('is_npc', true)->whereNotNull('user_id')->exists())->toBeFalse();
    expect($entry->fresh()->snapshot['name'])->toBe($pet->name);
    expect($pet->fresh()->isBusy())->toBeTrue();
    $this->travelTo($event->ends_at);
    $processor->processForOwner($pet->user);
    $completed = $entry->fresh();
    $balance = $pet->user->fresh()->coins;
    $experience = $pet->user->fresh()->experience;
    $result = $completed->result;
    $processor->processForOwner($pet->user);

    expect($completed->status)->toBe('completed');
    expect($completed->result['stages'])->toHaveCount(3);
    expect($entry->fresh()->result)->toBe($result);
    expect($pet->user->fresh()->coins)->toBe($balance);
    expect($pet->user->fresh()->experience)->toBe($experience);
    expect($pet->fresh()->isBusy())->toBeFalse();
    $this->assertDatabaseHas('pet_sport_records', ['pet_id' => $pet->id, 'starts' => 1]);
    expect(GameEventEntry::query()->where('is_npc', true)->sum('prize'))->toBe(0);
    expect(GameEventEntry::query()->where('is_npc', true)->whereNotNull('experience_awarded')->count())->toBe(0);
});

test('illness or retirement before freeze withdraws and refunds without NPC only tournaments', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $pet->user->update(['coins' => 500]);
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);
    $pet->update(['health' => 10]);
    $this->travelTo($event->closes_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);

    $this->assertDatabaseHas('game_event_entries', ['id' => $entry->id, 'status' => 'withdrawn']);
    $this->assertDatabaseHas('game_events', ['id' => $event->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
    $this->assertDatabaseCount('game_event_entries', 1);
    $this->assertDatabaseCount('pet_sport_records', 0);
});

test('a completed sleep is settled before the frozen competition snapshot', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['energy' => 40, 'energy_max' => 100]);
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'sleep', [], (string) Str::uuid());
    $this->travelTo($event->closes_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($entry->fresh()->status)->toBe('frozen');
    expect($entry->fresh()->snapshot['states']['energy'])->toBeGreaterThan(90);
});

test('canicross requires a compatible kit and gear is consumed only once at freeze', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create(['discipline' => 'canicross']);
    expect(fn () => registerDogEvent($pet, $event))->toThrow(GameEventUnavailable::class);
    $ids = [];
    foreach (['body', 'line', 'handler'] as $slot) {
        $ids[] = InventoryItem::factory()->for($pet->user)->create([
            'remaining_uses' => 2, 'characteristics' => ['competition' => [
                'slot' => $slot, 'phase' => 'performance', 'disciplines' => ['canicross'],
                'sizes' => [$pet->size->value], 'modifiers' => ['stamina' => 0.05, 'pace' => -0.02],
                'description' => ['ru' => 'Удобная посадка', 'en' => 'Comfortable fit'],
            ]],
        ])->id;
    }
    $entry = registerDogEvent($pet, $event, $ids);
    expect(InventoryItem::query()->whereIn('id', $ids)->sum('remaining_uses'))->toBe(6);
    $this->travelTo($event->closes_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);
    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect(InventoryItem::query()->whereIn('id', $ids)->sum('remaining_uses'))->toBe(3);
    expect($entry->fresh()->snapshot['gear'])->toHaveCount(3);
    $this->assertDatabaseCount('item_usages', 3);
});

test('registration reserves the future window and rejects overlapping activities', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    registerDogEvent($pet, $event);

    expect(fn () => app(PetEventReservation::class)->assertAvailable($pet, $event->starts_at, $event->ends_at))->toThrow(PetUnavailable::class);
    app(PetEventReservation::class)->assertAvailable($pet, now(), $event->closes_at->subSecond());
    expect($pet->fresh()->isBusy())->toBeFalse();
});

test('a promoted dog keeps its registered class while future entries use its new class', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);
    PetSportRecord::factory()->create(['pet_id' => $pet->id, 'discipline' => 'agility', 'experience' => 90, 'tier' => 2]);
    $division = $entry->division;
    $this->travelTo($event->closes_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect($entry->fresh()->division)->toBe($division);
    expect($entry->fresh()->status)->toBe('frozen');
});

test('progeny entries permit a retired parent and require three actual direct children', function () {
    $this->freezeSecond();
    $parent = Pet::factory()->retired()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create(['discipline' => 'progeny']);
    $children = Pet::factory()->count(3)->create(['dog_id' => $parent->dog_id, 'father_id' => $parent->id]);
    $winner = $children->first();
    $winningEntry = GameEventEntry::factory()->for($winner)->for($winner->user)->create(['status' => 'completed']);
    PetTitle::factory()->for($winner)->for($winningEntry, 'entry')->create([
        'discipline' => 'conformation', 'frequency' => 'weekly', 'code' => 'conformation_weekly_winner',
    ]);
    $plan = ['stages' => ['balanced', 'balanced', 'balanced'], 'offspring_ids' => $children->modelKeys()];
    $entry = app(RegisterGameEvent::class)->handle($parent->user, $event->id, $parent->id, $plan, [], 25, (string) Str::uuid());
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processForOwner($parent->user);

    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->fresh()->snapshot['offspring'])->toHaveCount(3);
    expect($entry->fresh()->snapshot['offspring'][0]['titles'])->toBe([
        ['discipline' => 'conformation', 'frequency' => 'weekly', 'code' => 'conformation_weekly_winner'],
    ]);
    expect($entry->fresh()->snapshot['offspring'][1]['titles'])->toBe([]);
    expect($parent->fresh()->isBusy())->toBeFalse();
});

test('event schedule repeats safely and uses Sunday and the calendar month end in Moscow', function () {
    $this->travelTo('2026-10-04 06:00:00 UTC');
    $schedule = app(GameEventSchedule::class);

    $schedule->ensureUpcoming();
    $count = GameEvent::query()->count();
    $schedule->ensureUpcoming();

    expect(GameEvent::query()->count())->toBe($count);
    $weekly = GameEvent::query()->where('frequency', 'weekly')->orderBy('starts_at')->first();
    expect($weekly->starts_at->setTimezone('Europe/Moscow')->format('Y-m-d H:i'))->toBe('2026-10-04 20:00');
    $monthly = GameEvent::query()->where('frequency', 'monthly')->orderBy('starts_at')->first();
    expect($monthly->starts_at->setTimezone('Europe/Moscow')->format('Y-m-d H:i'))->toBe('2026-10-31 20:30');
});

test('documentary progeny judging does not reserve the parent for physical activities', function () {
    $this->freezeSecond();
    $parent = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $children = Pet::factory()->count(3)->create(['dog_id' => $parent->dog_id, 'father_id' => $parent->id]);
    $documentary = GameEvent::factory()->create(['discipline' => 'progeny']);
    $physical = GameEvent::factory()->create();
    app(RegisterGameEvent::class)->handle($parent->user, $documentary->id, $parent->id,
        [...gameEventPlan(), 'offspring_ids' => $children->modelKeys()], [], 25, (string) Str::uuid());
    app(PetEventReservation::class)->assertAvailable($parent, $documentary->closes_at, $documentary->ends_at);
    expect(app(PetEventReservation::class)->nextStartsAt($parent, now()))->toBeNull();

    registerDogEvent($parent, $physical);

    expect(GameEventEntry::query()->where('pet_id', $parent->id)->count())->toBe(2);
    expect(app(PetEventReservation::class)->nextStartsAt($parent, now()))->toEqual($physical->closes_at);
    expect($parent->fresh()->energy)->toBe(100.0);
});

test('a physically reserved dog can enter documentary progeny judging at the same time', function () {
    $this->freezeSecond();
    $parent = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $children = Pet::factory()->count(3)->create(['dog_id' => $parent->dog_id, 'father_id' => $parent->id]);
    $physical = GameEvent::factory()->create();
    registerDogEvent($parent, $physical);
    $documentary = GameEvent::factory()->create(['discipline' => 'progeny']);

    $entry = app(RegisterGameEvent::class)->handle($parent->user, $documentary->id, $parent->id,
        [...gameEventPlan(), 'offspring_ids' => $children->modelKeys()], [], 25, (string) Str::uuid());

    expect($entry->status)->toBe('registered');
    expect(app(PetEventReservation::class)->nextStartsAt($parent, now()))->toEqual($physical->closes_at);
    expect($parent->fresh()->energy)->toBe(100.0);
});

test('three starts per Moscow day are enforced while a ninth competitor enters another heat', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 05:00:00', 'UTC'));
    $owner = User::factory()->create(['coins' => 500]);
    $pets = Pet::factory()->count(3)->for($owner)->create();
    foreach ([1, 3, 5] as $index => $hour) {
        $starts = now()->addHours($hour);
        $event = GameEvent::factory()->create(['starts_at' => $starts, 'closes_at' => $starts->subMinutes(15), 'ends_at' => $starts->addMinutes(10)]);
        registerDogEvent($pets[$index], $event);
    }
    $starts = now()->addHours(7);
    $fourth = GameEvent::factory()->create(['starts_at' => $starts, 'closes_at' => $starts->subMinutes(15), 'ends_at' => $starts->addMinutes(10)]);

    expect(fn () => registerDogEvent($pets->first(), $fourth))->toThrow(GameEventUnavailable::class, 'events.errors.daily_limit');

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 425]);
    $full = GameEvent::factory()->create(['frequency' => 'weekly']);
    foreach (range(1, 8) as $index) {
        $competitor = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
        registerDogEvent($competitor, $full);
    }
    $outsider = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $entry = registerDogEvent($outsider, $full);
    expect($entry->division)->toBe('novice:medium:heat-2');
    $this->assertDatabaseHas('users', ['id' => $outsider->user_id, 'coins' => 475]);
});

test('a wallet debit without its registration receipt cannot grant another entry', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);
    $token = $entry->operation_token;
    $entry->delete();

    expect(fn () => app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, gameEventPlan(), [], 25, $token))->toThrow(GameEventUnavailable::class, 'events.errors.token');

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('registration rejects insufficient coins and malformed decisions without a debit', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 24]))->create();
    $event = GameEvent::factory()->create();

    expect(fn () => registerDogEvent($pet, $event))->toThrow(GameEventUnavailable::class, 'events.errors.funds');
    expect(fn () => app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, ['stages' => [['bold'], 'balanced', 'careful']], [], 25, (string) Str::uuid()))->toThrow(GameEventUnavailable::class, 'events.errors.plan');

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 24]);
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('foreign gear and overlapping slot selections cannot be registered', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $spec = ['competition' => ['slot' => 'preparation', 'phase' => 'preparation', 'disciplines' => ['agility'], 'sizes' => [$pet->size->value], 'modifiers' => ['focus' => 0.1], 'description' => ['ru' => 'Подготовка', 'en' => 'Preparation']]];
    $foreign = InventoryItem::factory()->create(['characteristics' => $spec]);
    $gear = InventoryItem::factory()->for($pet->user)->count(2)->create(['characteristics' => $spec]);

    expect(fn () => registerDogEvent($pet, $event, [$foreign->id]))->toThrow(GameEventUnavailable::class, 'events.errors.gear');
    expect(fn () => registerDogEvent($pet, $event, $gear->modelKeys()))->toThrow(GameEventUnavailable::class, 'events.errors.gear');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('item_usages', 0);
});

test('NPC sporting profiles are independent of the human characteristic values', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create(['seed' => 'same-npc-seed']);
    registerDogEvent($pet, $event);
    $this->travelTo($event->closes_at);
    app(GameEventProcessor::class)->processForOwner($pet->user);
    $first = $event->entries()->where('is_npc', true)->orderBy('id')->first()->snapshot['stats'];

    $strongPet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['dog_id' => $pet->dog_id, 'speed' => 500, 'agility' => 500]);
    $secondEvent = GameEvent::factory()->create(['seed' => 'same-npc-seed']);
    registerDogEvent($strongPet, $secondEvent);
    $this->travelTo($secondEvent->closes_at);
    app(GameEventProcessor::class)->processForOwner($strongPet->user);

    $second = $secondEvent->entries()->where('is_npc', true)->orderBy('id')->first()->snapshot['stats'];
    expect($second)->toBe($first);
});

test('a first place receives one durable title and losing a later event cannot lower its tier', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'bond' => 100, 'exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
        'obedience' => 1000, 'obedience_potential' => 1000,
    ]);
    $event = GameEvent::factory()->create(['discipline' => 'conformation', 'seed' => 'show-winner-seed']);
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, ['stages' => ['careful', 'careful', 'careful']], [], 25, 'daea4b07-64b7-4f0c-8d8b-2280db7498d0');
    PetSportRecord::factory()->create(['pet_id' => $pet->id, 'discipline' => 'conformation', 'experience' => 90, 'tier' => 2]);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);
    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect($entry->fresh()->rank)->toBe(1);
    $this->assertDatabaseHas('pet_titles', ['pet_id' => $pet->id, 'game_event_entry_id' => $entry->id, 'code' => 'conformation_daily_winner']);
    $this->assertDatabaseCount('pet_titles', 1);
    $this->assertDatabaseHas('pet_sport_records', ['pet_id' => $pet->id, 'tier' => 2, 'wins' => 1]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 575, 'exhibition_wins' => 1]);
});

test('water finished before freeze unlocks energy recovery during the remaining preparation time', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['energy' => 40, 'hydration' => 40]);
    $event = GameEvent::factory()->create();
    $entry = registerDogEvent($pet, $event);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($event->closes_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect($care->fresh()->completed_at)->not->toBeNull();
    expect($entry->fresh()->snapshot['states']['hydration'])->toBe(71.25);
    expect($entry->fresh()->snapshot['states']['energy'])->toBeGreaterThan(43.5);
});

test('documentary progeny judging rejects equipment without charging or consuming it', function () {
    $this->freezeSecond();
    $parent = Pet::factory()->retired()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create(['discipline' => 'progeny']);
    $children = Pet::factory()->count(3)->create(['dog_id' => $parent->dog_id, 'father_id' => $parent->id]);
    $gear = InventoryItem::factory()->for($parent->user)->create(['remaining_uses' => 5]);
    $plan = ['stages' => ['balanced', 'balanced', 'balanced'], 'offspring_ids' => $children->modelKeys()];

    expect(fn () => app(RegisterGameEvent::class)->handle($parent->user, $event->id, $parent->id, $plan, [$gear->id], 25, (string) Str::uuid()))->toThrow(GameEventUnavailable::class, 'events.errors.gear');

    $this->assertDatabaseHas('users', ['id' => $parent->user_id, 'coins' => 500]);
    $this->assertDatabaseHas('inventory_items', ['id' => $gear->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('item_usages', 0);
});

test('the same equipment modifiers have the same capped effect in any acquisition order', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $admission = app(GameEventAdmission::class);
    $kits = [];
    foreach ([[0.10, 0.10, 0.10, -0.08], [-0.08, 0.10, 0.10, 0.10]] as $values) {
        $ids = [];
        foreach ($values as $index => $value) {
            $ids[] = InventoryItem::factory()->for($pet->user)->create([
                'characteristics' => ['competition' => [
                    'slot' => ['body', 'line', 'handler', 'preparation'][$index], 'phase' => 'preparation',
                    'disciplines' => ['agility'], 'sizes' => [$pet->size->value],
                    'modifiers' => ['precision' => $value], 'description' => ['ru' => 'Подготовка', 'en' => 'Preparation'],
                ]],
            ])->id;
        }
        $kit = $admission->gear($pet->user, $event, $pet, $ids);
        $kits[] = $admission->snapshot($event, $pet, gameEventPlan(), $kit)['modifiers'];
    }

    expect($kits[0]['precision'])->toBe(0.2);
    expect($kits[1])->toBe($kits[0]);
});

test('an elapsed work shift completes at the freeze deadline even when processing is delayed', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $shift = DogWorkShift::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id]);
    $pet->forceFill([
        'activity' => PetActivity::Work, 'activity_token' => $shift->activity_token,
        'activity_started_at' => $shift->started_at, 'activity_ends_at' => $shift->ends_at,
    ])->save();
    $entry = registerDogEvent($pet, $event);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processForOwner($pet->user);

    expect($shift->fresh()->completed_at->equalTo($event->closes_at))->toBeTrue();
    expect($entry->fresh()->snapshot['states']['satiety'])->toBe(96.25);
    expect($entry->fresh()->status)->toBe('completed');
});
