<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Services\GameEventProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-04 05:00:00', 'UTC'));
});

function participationEvent(string $startsAt, string $discipline = 'agility', string $frequency = 'daily'): GameEvent
{
    $starts = CarbonImmutable::parse($startsAt, 'Europe/Moscow')->utc();

    return GameEvent::factory()->create([
        'discipline' => $discipline, 'frequency' => $frequency, 'starts_at' => $starts,
        'registration_opens_at' => now()->subDays(2), 'closes_at' => $starts->subMinutes(15), 'ends_at' => $starts->addMinutes(10),
    ]);
}

function enterParticipation(Pet $pet, GameEvent $event, ?string $token = null, ?array $plan = null): GameEventEntry
{
    return app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        $plan ?? ['stages' => ['balanced', 'balanced', 'balanced']], [], $event->rules['fee'], $token ?? (string) Str::uuid());
}

test('one dog can enter two physical events across disciplines and frequencies while its owners other dog can also enter', function (string $thirdDiscipline) {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create();
    $other = Pet::factory()->for($owner)->create();
    enterParticipation($pet, participationEvent('2026-10-04 10:00:00', 'agility', 'daily'));
    enterParticipation($pet, participationEvent('2026-10-04 12:25:00', 'nosework', 'weekly'));
    $third = participationEvent('2026-10-04 16:00:00', $thirdDiscipline, 'monthly');

    expect(fn () => enterParticipation($pet, $third))->toThrow(GameEventUnavailable::class, 'events.errors.pet_daily_limit');

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 450]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseCount('game_event_entries', 2);
    $separate = participationEvent('2026-10-04 17:00:00', 'conformation');
    expect(enterParticipation($other, $separate)->status)->toBe('registered');
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 425]);
})->with(['canicross' => 'canicross', 'conformation' => 'conformation']);

test('registration requires two full hours between an event ending and the next preparation in either booking order', function (bool $reverse, int $restSeconds, bool $accepted) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $early = participationEvent('2026-10-04 10:00:00');
    $laterStart = $early->ends_at->addSeconds($restSeconds)->addMinutes(15)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    $late = participationEvent($laterStart, 'nosework');
    enterParticipation($pet, $reverse ? $late : $early);

    if ($accepted) {
        expect(enterParticipation($pet, $reverse ? $early : $late)->status)->toBe('registered');
    } else {
        expect(fn () => enterParticipation($pet, $reverse ? $early : $late))->toThrow(GameEventUnavailable::class, 'events.errors.rest_period');
    }

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => $accepted ? 450 : 475]);
    $this->assertDatabaseCount('currency_transactions', $accepted ? 2 : 1);
    $this->assertDatabaseCount('game_event_entries', $accepted ? 2 : 1);
})->with([
    'exact two hours, chronological booking' => [false, 7200, true],
    'one second short, chronological booking' => [false, 7199, false],
    'exact two hours, reverse booking' => [true, 7200, true],
    'one second short, reverse booking' => [true, 7199, false],
]);

test('the rest boundary continues across Moscow midnight', function (int $restSeconds, bool $accepted) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $early = participationEvent('2026-10-04 22:00:00');
    enterParticipation($pet, $early);
    $starts = $early->ends_at->addSeconds($restSeconds)->addMinutes(15)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    $next = participationEvent($starts, 'nosework');

    if ($accepted) {
        expect(enterParticipation($pet, $next)->status)->toBe('registered');
    } else {
        expect(fn () => enterParticipation($pet, $next))->toThrow(GameEventUnavailable::class, 'events.errors.rest_period');
    }

    $this->assertDatabaseCount('currency_transactions', $accepted ? 2 : 1);
})->with(['exact two hours' => [7200, true], 'one second short' => [7199, false]]);

test('the dog daily count follows the Moscow start date when starts fall on different UTC dates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 15:00:00', 'UTC'));
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    enterParticipation($pet, participationEvent('2026-10-04 00:30:00'));
    enterParticipation($pet, participationEvent('2026-10-04 04:30:00', 'nosework'));
    $third = participationEvent('2026-10-04 08:30:00', 'conformation');

    expect(fn () => enterParticipation($pet, $third))->toThrow(GameEventUnavailable::class, 'events.errors.pet_daily_limit');

    $this->assertDatabaseCount('game_event_entries', 2);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 450]);
});

test('a new Moscow day permits another start even on the same UTC date after sufficient rest', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    enterParticipation($pet, participationEvent('2026-10-04 12:00:00'));
    enterParticipation($pet, participationEvent('2026-10-04 20:30:00', 'nosework'));
    $next = participationEvent('2026-10-05 00:30:00', 'conformation');

    expect(enterParticipation($pet, $next)->status)->toBe('registered');

    $this->assertDatabaseCount('game_event_entries', 3);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 425]);
});

test('cancelled and withdrawn entries and cancelled events do not use dog starts or rest windows', function (string $entryStatus, string $eventStatus) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    foreach (['2026-10-04 10:00:00', '2026-10-04 12:25:00'] as $starts) {
        $event = participationEvent($starts);
        $event->update(['status' => $eventStatus]);
        GameEventEntry::factory()->for($pet)->for($pet->user)->for($event, 'event')->create(['status' => $entryStatus]);
    }
    $candidate = participationEvent('2026-10-04 10:01:00', 'conformation');

    expect(enterParticipation($pet, $candidate)->status)->toBe('registered');

    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
})->with([
    'cancelled entries' => ['cancelled', 'registration'],
    'withdrawn entries' => ['withdrawn', 'registration'],
    'cancelled event' => ['registered', 'cancelled'],
]);

test('completed and frozen participations use the dog daily limit', function (string $entryStatus, string $eventStatus) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    foreach (['2026-10-04 00:30:00', '2026-10-04 04:30:00'] as $starts) {
        $event = participationEvent($starts);
        $event->update(['status' => $eventStatus]);
        GameEventEntry::factory()->for($pet)->for($pet->user)->for($event, 'event')->create([
            'status' => $entryStatus, 'completed_at' => $entryStatus === 'completed' ? $event->ends_at : null,
            'rank' => $entryStatus === 'completed' ? 4 : null, 'result' => $entryStatus === 'completed' ? ['eliminated' => false] : null,
        ]);
    }
    $candidate = participationEvent('2026-10-04 12:00:00', 'conformation');

    expect(fn () => enterParticipation($pet, $candidate))->toThrow(GameEventUnavailable::class, 'events.errors.pet_daily_limit');

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('game_event_entries', 2);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
})->with(['completed' => ['completed', 'settled'], 'frozen' => ['frozen', 'frozen']]);

test('changing the dog owner does not reset the dogs physical participation count', function () {
    $oldOwner = User::factory()->create(['coins' => 500]);
    $newOwner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($oldOwner)->create();
    enterParticipation($pet, participationEvent('2026-10-04 10:00:00'));
    enterParticipation($pet, participationEvent('2026-10-04 12:25:00', 'nosework'));
    $pet->update(['user_id' => $newOwner->id]);
    $pet->unsetRelation('user');
    $candidate = participationEvent('2026-10-04 16:00:00', 'conformation');

    expect(fn () => enterParticipation($pet, $candidate))->toThrow(GameEventUnavailable::class, 'events.errors.pet_daily_limit');

    $this->assertDatabaseHas('users', ['id' => $oldOwner->id, 'coins' => 450]);
    $this->assertDatabaseHas('users', ['id' => $newOwner->id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseMissing('game_event_entries', ['user_id' => $newOwner->id]);
});

test('original token replay returns its existing entry after the daily dog limit and registration close', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $first = participationEvent('2026-10-04 10:00:00');
    $token = (string) Str::uuid();
    $entry = enterParticipation($pet, $first, $token);
    enterParticipation($pet, participationEvent('2026-10-04 12:25:00', 'nosework'));
    $this->travelTo($first->closes_at);

    expect(enterParticipation($pet, $first, $token)->id)->toBe($entry->id);

    $this->assertDatabaseCount('game_event_entries', 2);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 450]);
});

test('previously paid physical entries keep their eligibility after the new daily and rest limits are introduced', function () {
    $owner = User::factory()->create(['coins' => 425]);
    $pet = Pet::factory()->for($owner)->create(['energy' => 100, 'energy_max' => 100, 'health' => 100, 'health_max' => 100]);
    foreach (['2026-10-04 10:00:00', '2026-10-04 11:00:00', '2026-10-04 12:00:00'] as $starts) {
        $event = participationEvent($starts);
        GameEventEntry::factory()->for($pet)->for($owner)->for($event, 'event')->create(['fee' => 25]);
    }
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:10:00', 'Europe/Moscow')->utc());
    $processor = app(GameEventProcessor::class);

    expect($processor->processDue())->toBe(3);

    $entries = GameEventEntry::query()->where('pet_id', $pet->id)->get();
    expect($entries->pluck('status')->all())->toBe(['completed', 'completed', 'completed']);
    expect($entries->whereNotNull('refunded_at'))->toHaveCount(0);
    expect($entries->pluck('snapshot')->filter())->toHaveCount(3);
    $this->assertDatabaseHas('pet_sport_records', ['pet_id' => $pet->id, 'discipline' => 'agility', 'starts' => 3]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 425 + $entries->sum('prize'), 'experience' => '60']);
    $balance = $owner->fresh()->coins;
    expect($processor->processDue())->toBe(0);
    expect($owner->fresh()->coins)->toBe($balance);
});

test('documentary judging bypasses dog participation and rest limits while still using the owners daily allowance', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $children = Pet::factory()->count(3)->create(['dog_id' => $pet->dog_id, 'father_id' => $pet->id]);
    enterParticipation($pet, participationEvent('2026-10-04 10:00:00'));
    enterParticipation($pet, participationEvent('2026-10-04 12:25:00', 'nosework'));
    $documentary = participationEvent('2026-10-04 10:00:00', 'progeny');
    $plan = ['stages' => ['balanced', 'balanced', 'balanced'], 'offspring_ids' => $children->modelKeys()];

    expect(enterParticipation($pet, $documentary, plan: $plan)->status)->toBe('registered');
    $fourth = participationEvent('2026-10-04 16:00:00', 'progeny');
    expect(fn () => enterParticipation($pet, $fourth, plan: $plan))->toThrow(GameEventUnavailable::class, 'events.errors.daily_limit');

    $this->assertDatabaseCount('game_event_entries', 3);
    $this->assertDatabaseCount('currency_transactions', 3);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 425]);
});

test('documentary entries and club participants do not reserve physical starts or rest time', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    foreach (['2026-10-04 10:00:00', '2026-10-04 10:01:00'] as $starts) {
        $event = participationEvent($starts, 'progeny');
        GameEventEntry::factory()->for($pet)->for($pet->user)->for($event, 'event')->create();
    }
    $candidate = participationEvent('2026-10-04 10:00:00');
    GameEventEntry::factory()->for($candidate, 'event')->create([
        'is_npc' => true, 'user_id' => null, 'pet_id' => null, 'fee' => 0,
    ]);

    expect(enterParticipation($pet, $candidate)->status)->toBe('registered');

    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
});

test('competition rest does not prevent ordinary recovery care after a completed event', function (string $variant) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'energy' => 60, 'energy_max' => 100, 'hydration' => 60, 'hydration_max' => 100,
    ]);
    $completed = participationEvent('2026-10-04 10:00:00');
    $completed->update(['status' => 'settled']);
    GameEventEntry::factory()->for($pet)->for($pet->user)->for($completed, 'event')->create([
        'status' => 'completed', 'completed_at' => $completed->ends_at, 'rank' => 4, 'result' => ['eliminated' => false],
    ]);
    $this->travelTo($completed->ends_at->addMinute());
    $tooSoon = participationEvent('2026-10-04 12:00:00', 'nosework');
    expect(fn () => enterParticipation($pet, $tooSoon))->toThrow(GameEventUnavailable::class, 'events.errors.rest_period');

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, $variant, [], (string) Str::uuid());

    expect($care->variant)->toBe($variant);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['drink' => 'water', 'nap' => 'nap']);

test('HTTP registration shows localized participation errors without charging or saving a new entry', function (string $error, string $locale) {
    $owner = User::factory()->create(['coins' => 500, 'locale' => $locale]);
    $pet = Pet::factory()->for($owner)->create();
    enterParticipation($pet, participationEvent('2026-10-04 10:00:00'));
    if ($error === 'pet_daily_limit') {
        enterParticipation($pet, participationEvent('2026-10-04 12:25:00', 'nosework'));
    }
    $candidate = participationEvent($error === 'pet_daily_limit' ? '2026-10-04 16:00:00' : '2026-10-04 12:24:59', 'conformation');
    $token = (string) Str::uuid();

    $this->actingAs($owner)->post(route('game-events.register', $candidate), [
        'pet_id' => $pet->id, 'fee' => 25, 'token' => $token,
        'plan' => ['stages' => ['balanced', 'balanced', 'balanced']], 'gear_ids' => [],
    ])->assertSessionHasErrors(['event' => __('events.errors.'.$error, [
        'pet_daily_limit' => config('game-events.pet_daily_limit'), 'pet_rest_hours' => config('game-events.pet_rest_hours'),
    ])]);

    $count = $error === 'pet_daily_limit' ? 2 : 1;
    $this->assertDatabaseCount('game_event_entries', $count);
    $this->assertDatabaseCount('currency_transactions', $count);
    $this->assertDatabaseMissing('game_event_entries', ['operation_token' => $token]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500 - 25 * $count]);
})->with([
    'daily limit in Russian' => ['pet_daily_limit', 'ru'],
    'daily limit in English' => ['pet_daily_limit', 'en'],
    'rest period in Russian' => ['rest_period', 'ru'],
    'rest period in English' => ['rest_period', 'en'],
]);
