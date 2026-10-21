<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetGameEventParticipation;
use App\Modules\Pets\Queries\GetPendingEventRegistrations;
use App\Modules\Pets\Queries\PetOwnershipEligibility;
use App\Modules\Pets\Services\PetEventReservation;

test('collective companions are reserved and protected even when another member anchors the entry', function (string $discipline) {
    $this->freezeSecond();
    $anchor = Pet::factory()->create();
    $companion = Pet::factory()->female()->for($anchor->dog)->create(['user_id' => $discipline === 'couple' ? $anchor->user_id : null]);
    if ($discipline === 'breeders') {
        $companion->update(['user_id' => User::factory()->create()->id]);
    }
    $event = GameEvent::factory()->create(['discipline' => $discipline,
        'closes_at' => now(), 'starts_at' => now()->addMinutes(20), 'ends_at' => now()->addMinutes(30),
        'rules' => ['version' => 3, 'linked_show_id' => 900, 'regulation' => ['version' => 2, 'format' => $discipline.'_ring']],
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($anchor)->for($anchor->user)->create(['plan' => ['member_ids' => [$companion->id]]]);

    expect(app(PetEventReservation::class)->nextStartsAt($companion, now()))->toEqual($event->closes_at);
    expect(fn () => app(PetEventReservation::class)->assertAvailable($companion, $event->closes_at, $event->ends_at))
        ->toThrow(PetUnavailable::class, 'events.errors.reserved');
    expect(app(PetOwnershipEligibility::class)->reason($companion))->toBe('ownership.errors.event');
    expect(app(GetPendingEventRegistrations::class)->handle($companion->user, now()))->toBeTrue();
    $later = GameEvent::factory()->create(['closes_at' => $event->ends_at->addMinutes(30),
        'starts_at' => $event->ends_at->addMinutes(45), 'ends_at' => $event->ends_at->addMinutes(55)]);
    expect(app(GetGameEventParticipation::class)->petReason($companion, $later))->toBe('events.errors.rest_period');

    $entry->update(['status' => 'cancelled']);

    expect(app(PetOwnershipEligibility::class)->reason($companion))->toBeNull();
    expect(app(GetPendingEventRegistrations::class)->handle($companion->user, now()))->toBeFalse();
})->with(['couple', 'breeders']);
