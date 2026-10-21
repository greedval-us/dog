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

function multipleOwnedShow(): GameEvent
{
    return GameEvent::factory()->create(['discipline' => 'conformation', 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 8,
        'stages' => ['type', 'structure', 'movement'],
        'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'cac_cf', 'format' => 'conformation',
            'country' => 'RU', 'region' => 'moscow', 'judgeId' => 'multi-show-judge', 'judgeName' => 'Judge'],
    ]]);
}

function multipleOwnedShowEntry(Pet $pet, GameEvent $event, string $class = 'open', ?string $token = null): GameEventEntry
{
    return app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['exhibition_class' => $class, 'stages' => ['balanced', 'balanced', 'balanced']], [], 25, $token ?? (string) Str::uuid());
}

test('one owner can enter four dogs in the same regulated show while each dog and registration token remain unique', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(4)->create();
    $event = multipleOwnedShow();
    $token = (string) Str::uuid();
    $first = multipleOwnedShowEntry($pets[0], $event, token: $token);
    foreach ($pets->slice(1) as $pet) {
        multipleOwnedShowEntry($pet, $event);
    }

    expect(multipleOwnedShowEntry($pets[0], $event, token: strtoupper($token))->id)->toBe($first->id);
    expect(fn () => multipleOwnedShowEntry($pets[0], $event))->toThrow(GameEventUnavailable::class, 'events.errors.entered');
    expect($owner->fresh()->coins)->toBe(400);
    $this->assertDatabaseCount('game_event_entries', 4);
    $this->assertDatabaseCount('currency_transactions', 4);
});

test('rolling back multiple show entries refuses to discard a second owned dog or its payment', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(2)->create();
    $event = multipleOwnedShow();
    multipleOwnedShowEntry($pets[0], $event);
    multipleOwnedShowEntry($pets[1], $event);
    $migration = require database_path('migrations/2026_10_21_174804_allow_multiple_owned_dogs_in_show_entries.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot restore one entry per owner');

    $this->assertDatabaseCount('game_event_entries', 2);
    $this->assertDatabaseCount('currency_transactions', 2);
    expect($owner->fresh()->coins)->toBe(450);
});

test('one owner can enter both parents as separate producer groups and manage only the selected group', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $father = Pet::factory()->for($owner)->for($breed)->create(['born_at' => now()->subDays(40)]);
    $mother = Pet::factory()->for($owner)->for($breed)->female()->create(['born_at' => now()->subDays(40)]);
    $children = Pet::factory()->for($owner)->for($breed)->count(3)->create([
        'father_id' => $father->id, 'mother_id' => $mother->id, 'born_at' => now()->subDays(18),
    ]);
    $show = multipleOwnedShow();
    foreach ([$father, $mother, ...$children] as $member) {
        multipleOwnedShowEntry($member, $show);
    }
    $ring = GameEvent::factory()->create([
        'discipline' => 'progeny', 'registration_opens_at' => $show->registration_opens_at, 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 8,
            'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'progeny', 'format' => 'progeny_ring']],
    ]);
    $plan = ['offspring_ids' => $children->modelKeys(), 'stages' => ['balanced', 'balanced', 'balanced']];
    $first = app(RegisterGameEvent::class)->handle($owner, $ring->id, $father->id, $plan, [], 25, (string) Str::uuid());
    $second = app(RegisterGameEvent::class)->handle($owner, $ring->id, $mother->id, $plan, [], 25, (string) Str::uuid());
    $state = app(GetGameEvents::class)->showState($owner, $ring, 'en', selectedPetId: $mother->id);

    expect($state['entry']['id'])->toBe($second->id);
    expect(array_column($state['ownEntries'], 'id'))->toBe([$first->id, $second->id]);
    $this->actingAs($owner)->post(route('game-events.cancel', $ring), ['pet_id' => $mother->id])
        ->assertRedirect(route('game-events.show', ['gameEvent' => $ring, 'pet' => $mother->id]));

    expect($first->fresh()->status)->toBe('registered');
    expect($second->fresh()->status)->toBe('cancelled');
    expect($owner->fresh()->coins)->toBe(350);
    $this->assertDatabaseCount('currency_transactions', 8);
});

test('selected show dogs resolve their own entries across divisions and an unregistered owned dog has an empty entry', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(3)->create(['born_at' => now()->subDays(16)]);
    $event = multipleOwnedShow();
    $first = multipleOwnedShowEntry($pets[0], $event, 'junior');
    $second = multipleOwnedShowEntry($pets[1], $event, 'open');

    $state = app(GetGameEvents::class)->showState($owner, $event, 'en', $first->division, $pets[1]->id);

    expect($state['entry']['id'])->toBe($second->id);
    expect(array_column($state['ownEntries'], 'id'))->toBe([$first->id, $second->id]);
    expect(array_column($state['event']['entries'], 'id'))->toBe([$first->id]);
    expect(app(GetGameEvents::class)->showState($owner, $event, 'en', selectedPetId: $pets[2]->id)['entry'])->toBeNull();
    $this->actingAs($owner)->get(route('game-events.show', ['gameEvent' => $event, 'pet' => $pets[1]->id]))
        ->assertInertia(fn (Assert $page) => $page->where('entry.id', $second->id)->has('ownEntries', 2));
});

test('show update and cancellation target the selected owned dog and refund only that entry once', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(2)->create();
    $event = multipleOwnedShow();
    $first = multipleOwnedShowEntry($pets[0], $event);
    $second = multipleOwnedShowEntry($pets[1], $event);
    $target = route('game-events.show', ['gameEvent' => $event, 'pet' => $pets[1]->id]);

    $this->actingAs($owner)->put(route('game-events.update', $event), [
        'pet_id' => $pets[1]->id, 'plan' => ['exhibition_class' => 'open', 'stages' => ['bold', 'careful', 'balanced']], 'gear_ids' => [],
    ])->assertRedirect($target);
    $this->post(route('game-events.cancel', $event), ['pet_id' => $pets[1]->id])->assertRedirect($target);
    $this->post(route('game-events.cancel', $event), ['pet_id' => $pets[1]->id])->assertRedirect($target);

    expect($first->fresh()->status)->toBe('registered');
    expect($first->fresh()->plan['stages'])->toBe(['balanced', 'balanced', 'balanced']);
    expect($second->fresh()->status)->toBe('cancelled');
    expect($second->fresh()->plan['stages'])->toBe(['bold', 'careful', 'balanced']);
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('currency_transactions', 3);
});

test('ambiguous or foreign show entry changes do not alter any owned entry or charge money', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(2)->create();
    $foreign = Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 500]))->create();
    $event = multipleOwnedShow();
    $first = multipleOwnedShowEntry($pets[0], $event);
    $second = multipleOwnedShowEntry($pets[1], $event);
    multipleOwnedShowEntry($foreign, $event);
    $plan = ['exhibition_class' => 'open', 'stages' => ['bold', 'bold', 'bold']];

    $this->actingAs($owner)->put(route('game-events.update', $event), ['plan' => $plan, 'gear_ids' => []])->assertSessionHasErrors('pet_id');
    $this->post(route('game-events.cancel', $event))->assertSessionHasErrors('pet_id');
    $this->put(route('game-events.update', $event), ['pet_id' => $foreign->id, 'plan' => $plan, 'gear_ids' => []])->assertNotFound();
    $this->post(route('game-events.cancel', $event), ['pet_id' => $foreign->id])->assertNotFound();

    expect($first->fresh()->status)->toBe('registered');
    expect($second->fresh()->status)->toBe('registered');
    expect($first->fresh()->plan['stages'])->toBe(['balanced', 'balanced', 'balanced']);
    expect($owner->fresh()->coins)->toBe(450);
    $this->assertDatabaseCount('currency_transactions', 3);
});

test('other disciplines and historical shows retain one owner entry per event', function (string $discipline) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pets = Pet::factory()->for($owner)->count(2)->create();
    $event = GameEvent::factory()->create(['discipline' => $discipline]);
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    if ($discipline === 'conformation') {
        $plan['exhibition_class'] = 'open';
    }
    app(RegisterGameEvent::class)->handle($owner, $event->id, $pets[0]->id, $plan, [], 25, (string) Str::uuid());

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $event->id, $pets[1]->id, $plan, [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.entered');
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('game_event_entries', 1);
})->with(['agility', 'conformation']);

test('another owned dogs show entry does not block a title recommendation for the selected dog', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pets = Pet::factory()->for($owner)->for($breed)->count(2)->create();
    $event = multipleOwnedShow();
    multipleOwnedShowEntry($pets[0], $event);
    $profile = app(GetPetEventProfile::class)->forPet($pets[1]);

    $candidates = app(GetTitleProgressEvents::class)->candidates($owner, $pets[1], $profile, 'en', CarbonImmutable::now());
    $open = collect($candidates)->first(fn (array $candidate): bool => ($candidate['plan']['exhibition_class'] ?? '') === 'open');

    expect($open['eligible'])->toBeTrue();
    expect($open['reason'])->toBeNull();
    multipleOwnedShowEntry($pets[1], $event);
    $candidates = app(GetTitleProgressEvents::class)->candidates($owner, $pets[1], $profile, 'en', CarbonImmutable::now());
    $open = collect($candidates)->first(fn (array $candidate): bool => ($candidate['plan']['exhibition_class'] ?? '') === 'open');
    expect($open['registered'])->toBeTrue();
});
