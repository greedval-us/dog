<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Queries\GetPetEventProfile;
use App\Modules\Pets\Queries\GetTitleProgressEvents;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00:00', 'Europe/Moscow'));
});

function participationPreviewPet(User $owner, array $changes = []): Pet
{
    $breed = Dog::query()->where('breed', 'german_shepherd')->first() ?? Dog::factory()->create(['breed' => 'german_shepherd']);

    return Pet::factory()->for($owner)->for($breed)->create(['born_at' => now()->subDays(30),
        'event_profile' => ['registered' => true, 'recognized_rkf' => true, 'pedigree_verified' => true], ...$changes]);
}

function participationPreviewEvent(int $hour): GameEvent
{
    $starts = now()->startOfDay()->addHours($hour);

    return GameEvent::factory()->create(['discipline' => 'conformation', 'registration_opens_at' => now()->subHour(),
        'closes_at' => $starts->subMinutes(15), 'starts_at' => $starts, 'ends_at' => $starts->addMinutes(10),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 8,
            'stages' => ['type', 'structure', 'movement'],
            'regulation' => ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'region' => 'moscow', 'rank' => 'cac_cf', 'format' => 'conformation']]]);
}

test('preparation and title recommendations explain the same rest conflict as registration before payment', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = participationPreviewPet($owner);
    $first = participationPreviewEvent(9);
    $target = participationPreviewEvent(10);
    GameEventEntry::factory()->for($first, 'event')->for($owner)->for($pet)->create(['status' => 'registered']);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $target);
    $profile = app(GetPetEventProfile::class)->forPet($pet);
    $candidates = collect(app(GetTitleProgressEvents::class)->candidates($owner, $pet, $profile, 'en', now()))->filter(fn (array $candidate): bool => $candidate['event']->id === $target->id);

    expect($dogs[0]['preparation']['blockingReasons'])->toContain(__('events.errors.rest_period', ['pet_rest_hours' => 2], 'en'));
    expect($candidates)->not->toBeEmpty();
    expect($candidates->pluck('eligible')->unique()->all())->toBe([false]);
    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $target->id, $pet->id,
        ['exhibition_class' => 'open', 'stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.rest_period');
    $this->assertDatabaseCount('game_event_entries', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('preparation exposes the account daily limit even when the selected dog has no starts', function () {
    $owner = User::factory()->create();
    $pet = participationPreviewPet($owner);
    foreach ([9, 12, 15] as $hour) {
        GameEventEntry::factory()->for(participationPreviewEvent($hour), 'event')->for($owner)->create(['status' => 'registered']);
    }
    $target = participationPreviewEvent(18);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $target);

    expect(collect($dogs)->firstWhere('id', $pet->id)['preparation']['blockingReasons'])->toContain(__('events.errors.daily_limit', [], 'en'));
});

test('a dogs existing entry does not conflict with its own preparation', function () {
    $owner = User::factory()->create();
    $pet = participationPreviewPet($owner);
    $event = participationPreviewEvent(10);
    GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create(['status' => 'registered']);

    expect(app(GetGameEvents::class)->registrationDogs($owner, 'en', $event)[0]['preparation']['blockingReasons'])->toBe([]);
});

test('previously paid entries remain editable when old reservations exceed the current registration limits', function () {
    $owner = User::factory()->create();
    $pet = participationPreviewPet($owner);
    $target = participationPreviewEvent(10);
    foreach ([$target, participationPreviewEvent(11), participationPreviewEvent(12), participationPreviewEvent(13)] as $event) {
        GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create(['status' => 'registered']);
    }

    expect(app(GetGameEvents::class)->registrationDogs($owner, 'en', $target)[0]['preparation']['blockingReasons'])->toBe([]);
});

test('event links can select the recommended owned dog and eligible exhibition class', function () {
    $owner = User::factory()->create();
    participationPreviewPet($owner);
    $pet = participationPreviewPet($owner, ['born_at' => now()->subDays(16)]);
    $event = participationPreviewEvent(10);

    $this->actingAs($owner)->get(route('game-events.show', ['gameEvent' => $event, 'pet' => $pet->id, 'entry_option' => 'open']))
        ->assertInertia(fn (Assert $page) => $page->where('selectedPetId', $pet->id)->where('selectedEntryOptionKey', 'open'));
});

test('selection hints never expose or select another owners dog', function () {
    $owner = User::factory()->create();
    participationPreviewPet($owner);
    $foreign = participationPreviewPet(User::factory()->create());
    $event = participationPreviewEvent(10);

    $this->actingAs($owner)->get(route('game-events.show', ['gameEvent' => $event, 'pet' => $foreign->id, 'entry_option' => 'open']))
        ->assertInertia(fn (Assert $page) => $page->where('selectedPetId', null)->where('selectedEntryOptionKey', null)->has('dogs', 1));
});

test('an unavailable class hint does not override an owned dogs real admission options', function () {
    $owner = User::factory()->create();
    $pet = participationPreviewPet($owner);
    $event = participationPreviewEvent(10);

    $this->actingAs($owner)->get(route('game-events.show', ['gameEvent' => $event, 'pet' => $pet->id, 'entry_option' => 'junior']))
        ->assertInertia(fn (Assert $page) => $page->where('selectedPetId', $pet->id)->where('selectedEntryOptionKey', null));
});

test('preparation sees rest conflicts across midnight at both edges of a Moscow day', function (string $clock, string $targetStarts, string $otherStarts) {
    $this->travelTo(CarbonImmutable::parse($clock, 'Europe/Moscow'));
    $owner = User::factory()->create();
    $pet = participationPreviewPet($owner);
    $target = participationPreviewEvent(18);
    $reserved = participationPreviewEvent(19);
    foreach ([[$target, $targetStarts], [$reserved, $otherStarts]] as [$event, $date]) {
        $start = CarbonImmutable::parse($date, 'Europe/Moscow')->utc();
        $event->update(['starts_at' => $start, 'closes_at' => $start->subMinutes(15), 'ends_at' => $start->addMinutes(10)]);
    }
    GameEventEntry::factory()->for($reserved, 'event')->for($owner)->for($pet)->create(['status' => 'registered']);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $target);

    expect($dogs[0]['preparation']['blockingReasons'])->toContain(__('events.errors.rest_period', ['pet_rest_hours' => 2], 'en'));
})->with([
    'first minutes' => ['2026-10-20 15:00:00', '2026-10-21 00:00:00', '2026-10-20 21:40:00'],
    'last minutes' => ['2026-10-21 06:00:00', '2026-10-21 23:59:00', '2026-10-22 02:20:00'],
]);
