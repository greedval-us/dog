<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetBreedingBoard;
use App\Modules\Pets\Queries\GetGameEventParticipation;
use App\Modules\Pets\Queries\GetPendingEventRegistrations;
use App\Modules\Pets\Queries\PetOwnershipEligibility;
use App\Modules\Pets\Services\PetEventReservation;
use Carbon\CarbonImmutable;

/** @return array{GameEvent,GameEventEntry,Pet,Pet} */
function reservedProgenyRing(array $eventChanges = []): array
{
    $breed = Dog::factory()->create();
    $parent = Pet::factory()->for($breed)->create();
    $child = Pet::factory()->female()->for($breed)->for(User::factory()->state(['experience' => '1500']))->create(['father_id' => $parent->id]);
    $siblings = Pet::factory()->count(2)->for($breed)->create(['father_id' => $parent->id]);
    $event = GameEvent::factory()->create([
        'discipline' => 'progeny', 'closes_at' => now()->addMinutes(15), 'starts_at' => now()->addMinutes(50), 'ends_at' => now()->addMinutes(70),
        'rules' => ['version' => 3, 'linked_show_id' => 900, 'regulation' => ['version' => 3, 'format' => 'progeny_ring']],
        ...$eventChanges,
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($parent)->for($parent->user)->create([
        'plan' => ['offspring_ids' => [$child->id, ...$siblings->pluck('id')->all()]],
    ]);

    return [$event, $entry, $parent, $child];
}

test('physical progeny membership reserves a foreign owned offspring for care and future starts', function () {
    $this->freezeSecond();
    [$event, , $parent, $child] = reservedProgenyRing();
    $reservations = app(PetEventReservation::class);

    expect($reservations->nextStartsAt($child, now()))->toEqual($event->closes_at);
    expect(fn () => $reservations->assertAvailable($child, $event->closes_at, $event->ends_at))->toThrow(PetUnavailable::class, 'events.errors.reserved');
    expect(fn () => $reservations->assertAvailable($parent, $event->closes_at, $event->ends_at))->toThrow(PetUnavailable::class, 'events.errors.reserved');
    $reservations->assertAvailable($child, now(), $event->closes_at->subSecond());
    $reservations->assertAvailable($child, $event->ends_at, $event->ends_at->addMinute());
});

test('a physical progeny group prevents transferring an offspring owned by another player', function () {
    $this->freezeSecond();
    [, , , $child] = reservedProgenyRing();

    expect(app(PetOwnershipEligibility::class)->reason($child))->toBe('ownership.errors.event');
});

test('closed unprocessed progeny registration also protects a foreign offspring owners lifecycle', function () {
    $this->freezeSecond();
    [, $entry, $parent, $child] = reservedProgenyRing(['closes_at' => now()]);
    $pending = app(GetPendingEventRegistrations::class);

    expect($pending->handle($parent->user, now()))->toBeTrue();
    expect($pending->handle($child->user, now()))->toBeTrue();
    expect($pending->handle($child->user, now()->subSecond()))->toBeFalse();
    $entry->update(['status' => 'cancelled']);
    expect($pending->handle($child->user, now()))->toBeFalse();
});

test('the breeding board blocks an offspring during its physical progeny reservation', function () {
    $this->freezeSecond();
    [, , , $child] = reservedProgenyRing(['closes_at' => now()->subSecond()]);

    $board = app(GetBreedingBoard::class)->handle($child->user, 'en', $child->id);

    expect($board['dogs'][0]['reason'])->toBe('breeding.errors.unavailable');
});

test('physical offspring participation follows a group entry belonging to another player', function () {
    $this->freezeSecond();
    [$ring, , , $child] = reservedProgenyRing();
    $other = GameEvent::factory()->create(['closes_at' => $ring->ends_at->addMinutes(30), 'starts_at' => $ring->ends_at->addMinutes(45), 'ends_at' => $ring->ends_at->addMinutes(55)]);

    expect(app(GetGameEventParticipation::class)->petReason($child, $other))->toBe('events.errors.rest_period');
});

test('legacy documentary offspring references do not reserve or freeze foreign owned dogs', function () {
    $this->freezeSecond();
    [$event, , , $child] = reservedProgenyRing(['closes_at' => now(), 'rules' => ['version' => 3, 'regulation' => ['version' => 2, 'format' => 'progeny']]]);

    expect(app(PetEventReservation::class)->nextStartsAt($child, now()))->toBeNull();
    expect(app(GetPendingEventRegistrations::class)->handle($child->user, now()))->toBeFalse();
    expect(app(PetOwnershipEligibility::class)->reason($child))->toBeNull();
    app(PetEventReservation::class)->assertAvailable($child, $event->closes_at, $event->ends_at);
});

test('a shared show and its progeny final count once without imposing rest between their own rings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00:00', 'Europe/Moscow'));
    [$ring, , $parent, $child] = reservedProgenyRing();
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'closes_at' => $ring->closes_at, 'starts_at' => $ring->starts_at->subMinutes(25), 'ends_at' => $ring->ends_at->subMinutes(20)]);
    $ring->update(['rules' => [...$ring->rules, 'linked_show_id' => $show->id]]);
    GameEventEntry::factory()->for($show, 'event')->for($parent)->for($parent->user)->create();
    GameEventEntry::factory()->for($show, 'event')->for($child)->for($child->user)->create();
    $later = GameEvent::factory()->create(['closes_at' => $ring->ends_at->addHours(3), 'starts_at' => $ring->ends_at->addHours(3)->addMinutes(15), 'ends_at' => $ring->ends_at->addHours(3)->addMinutes(25)]);
    $participation = app(GetGameEventParticipation::class);

    expect($participation->petReason($child, $ring))->toBeNull();
    expect($participation->petReason($parent, $ring))->toBeNull();
    expect($participation->petReason($child, $later))->toBeNull();
});

test('membership scopes keep surrounding entry constraints and an empty requested dog list matches nothing', function () {
    $this->freezeSecond();
    [, $entry, , $child] = reservedProgenyRing();

    expect(GameEventEntry::query()->involvingPets([$child->id])->pluck('id')->all())->toBe([$entry->id]);
    expect(GameEventEntry::query()->involvingPets([])->exists())->toBeFalse();
    $entry->update(['status' => 'cancelled']);
    expect(GameEventEntry::query()->where('status', 'registered')->involvingPets([$child->id])->exists())->toBeFalse();
});
