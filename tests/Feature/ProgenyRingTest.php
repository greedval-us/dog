<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function progenyRingFamily(string $system = 'rkf'): array
{
    $owner = User::factory()->create(['coins' => 1000]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $parent = Pet::factory()->for($owner)->for($breed)->create(['born_at' => now()->subDays(40)]);
    $children = Pet::factory()->count(3)->for($breed)->create(['father_id' => $parent->id, 'born_at' => now()->subDays(18)]);
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 1,
        'stages' => ['type', 'structure', 'movement'],
        'regulation' => ['version' => 2, 'system' => $system, 'rank' => 'cac_cf', 'format' => 'conformation'],
    ]]);
    $ring = GameEvent::factory()->create([
        'discipline' => 'progeny', 'registration_opens_at' => $show->registration_opens_at, 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 1,
            'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => ['version' => 2, 'system' => $system, 'rank' => 'qualifying', 'format' => 'progeny_ring']],
    ]);
    foreach ([$parent, ...$children] as $member) {
        GameEventEntry::factory()->for($show, 'event')->for($member)->for($member->user)->create([
            'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')], 'status' => 'registered',
        ]);
    }

    return [$owner, $parent, $children, $show, $ring];
}

test('a ring group cannot substitute past titles for individual registration on its linked show', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $parent, $children, $show, $ring] = progenyRingFamily();
    $show->entries()->where('pet_id', $children[0]->id)->update(['status' => 'cancelled']);

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $ring->id, $parent->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.progeny_entry');

    expect($owner->fresh()->coins)->toBe(1000);
    expect($ring->entries()->count())->toBe(0);
});

test('a producer group waits for individual judging then refunds a failed member admission exactly once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $parent, $children, $show, $ring] = progenyRingFamily();
    $entry = app(RegisterGameEvent::class)->handle($owner, $ring->id, $parent->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $this->travelTo($ring->closes_at);
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('registered');
    expect($entry->fresh()->snapshot)->toBeNull();

    $show->update(['status' => 'settled', 'settled_at' => $show->ends_at]);
    foreach ($show->entries()->where('is_npc', false)->get() as $individual) {
        $individual->update(['status' => 'completed', 'result' => ['grade' => $individual->pet_id === $children[0]->id ? 'good' : 'excellent']]);
        $individual->pet->clearActivity();
        $individual->pet->save();
    }
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('withdrawn');
    expect($entry->fresh()->result['reason'])->toBe('events.errors.progeny_grade');
    expect($owner->fresh()->coins)->toBe(1000);
    expect($entry->titles()->count())->toBe(0);
});

test('each new exhibition offers its linked producer ring with one shared registration deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));

    app(GameEventSchedule::class)->ensureUpcoming();
    app(GameEventSchedule::class)->ensureUpcoming();

    $shows = GameEvent::query()->where('discipline', 'conformation')->get();
    $rings = GameEvent::query()->where('discipline', 'progeny')->where('rules->regulation->format', 'progeny_ring')->get();
    expect($rings)->toHaveCount($shows->count());
    foreach ($shows as $show) {
        $ring = $rings->firstWhere('rules.linked_show_id', $show->id);
        expect($ring)->not->toBeNull();
        expect($ring->closes_at->equalTo($show->closes_at))->toBeTrue();
        expect($ring->starts_at->equalTo($show->ends_at->addMinutes(10)))->toBeTrue();
        expect($ring->rules['stages'])->toBe(['type', 'uniformity', 'movement']);
    }
});

test('a real group freezes all ring members after their show and settles final awards without charging their leads twice', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $parent, $children, $show, $ring] = progenyRingFamily();
    foreach ([$parent, ...$children] as $member) {
        $member->update(['exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
            'obedience' => 100, 'bond' => 100, 'mood' => 100, 'cleanliness' => 100, 'health' => 100,
            'energy' => 100, 'hydration' => 100, 'satiety' => 100]);
    }
    $lead = InventoryItem::factory()->for($owner)->create(['remaining_uses' => 3, 'characteristics' => [
        'competition' => ['slot' => 'line', 'phase' => 'performance', 'disciplines' => ['conformation'],
            'sizes' => ['small', 'medium', 'large'], 'modifiers' => ['precision' => 0.03], 'description' => ['en' => 'Show lead', 'ru' => 'Ринговка']],
    ]]);
    $show->entries()->where('pet_id', $parent->id)->update(['gear_ids' => [$lead->id]]);
    $entry = app(RegisterGameEvent::class)->handle($owner, $ring->id, $parent->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $this->travelTo($show->closes_at);
    app(GameEventProcessor::class)->processDue();
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('frozen');
    expect($entry->fresh()->snapshot['members'])->toHaveCount(4);
    expect($entry->fresh()->snapshot['members'][0]['grade'])->toBeIn(['excellent', 'very_good']);
    expect($lead->fresh()->remaining_uses)->toBe(2);
    foreach ([$parent, ...$children] as $member) {
        expect($member->fresh()->activity_token)->toBe($entry->operation_token);
    }
    $coinsBeforeFinal = $owner->fresh()->coins;
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processForOwner($children[0]->user);
    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->fresh()->rank)->toBe(1);
    expect($entry->fresh()->result['format'])->toBe('progeny_ring');
    expect($entry->fresh()->result['members'])->toHaveCount(4);
    expect($entry->titles()->pluck('code')->sort()->values()->all())->toBe(['BEST_PROGENY_GROUP', 'PROGENY_BREED_WINNER', 'PROGENY_PLACE_1']);
    expect($owner->fresh()->coins)->toBe($coinsBeforeFinal + 100);
    expect($lead->fresh()->remaining_uses)->toBe(2);
    $this->assertDatabaseCount('item_usages', 1);
    foreach ([$parent, ...$children] as $member) {
        expect($member->fresh()->activity_token)->toBeNull();
        expect($member->fresh()->exterior)->toMatchArray(['type' => 100, 'structure' => 100, 'movement' => 100]);
    }
});

function prepareExcellentRingFamily(Pet $parent, iterable $children): void
{
    foreach ([$parent, ...$children] as $member) {
        $member->update(['exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
            'obedience' => 100, 'bond' => 100, 'mood' => 100, 'cleanliness' => 100, 'health' => 100,
            'energy' => 100, 'hydration' => 100, 'satiety' => 100]);
    }
}

test('shared offspring have the same preparation in both parent groups and spend group energy only once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $father, $children, $show, $ring] = progenyRingFamily();
    $mother = Pet::factory()->female()->for($father->dog)->for(User::factory()->state(['coins' => 1000]))->create(['born_at' => now()->subDays(40)]);
    foreach ($children as $child) {
        $child->update(['mother_id' => $mother->id]);
    }
    GameEventEntry::factory()->for($show, 'event')->for($mother)->for($mother->user)->create([
        'status' => 'registered', 'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')],
    ]);
    prepareExcellentRingFamily($father, [$mother, ...$children]);
    $plan = ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')];
    $first = app(RegisterGameEvent::class)->handle($owner, $ring->id, $father->id, $plan, [], 25, (string) Str::uuid());
    $second = app(RegisterGameEvent::class)->handle($mother->user, $ring->id, $mother->id, $plan, [], 25, (string) Str::uuid());
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($first->fresh()->status)->toBe('frozen');
    expect($second->fresh()->status)->toBe('frozen');
    foreach ($children as $child) {
        $a = collect($first->fresh()->snapshot['members'])->firstWhere('id', $child->id);
        $b = collect($second->fresh()->snapshot['members'])->firstWhere('id', $child->id);
        expect($b)->toBe($a);
        expect(round($child->fresh()->energy + 5, 4))->toBe(round($a['states']['energy'], 4));
    }
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($first->fresh()->status)->toBe('completed');
    expect($second->fresh()->status)->toBe('completed');
    foreach ($children as $child) {
        expect($child->fresh()->activity_token)->toBeNull();
    }
});

test('foreign offspring leave the ring even if the group applicant deletes their account after freezing', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $parent, $children, $show, $ring] = progenyRingFamily();
    prepareExcellentRingFamily($parent, $children);
    $entry = app(RegisterGameEvent::class)->handle($owner, $ring->id, $parent->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();
    expect($entry->fresh()->status)->toBe('frozen');
    $owner->delete();
    $this->travelTo($ring->ends_at);

    app(GameEventProcessor::class)->processForOwner($children[0]->user);

    expect($entry->fresh()->status)->toBe('completed');
    foreach ($children as $child) {
        expect($child->fresh()->activity_token)->toBeNull();
        expect($child->fresh()->activity)->toBeNull();
    }
});

test('a group cannot consume a foreign offspring rest period before an already entered future start', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $parent, $children, $show, $ring] = progenyRingFamily();
    $later = GameEvent::factory()->create(['closes_at' => $show->ends_at->addHours(2),
        'starts_at' => $show->ends_at->addHours(2)->addMinutes(15), 'ends_at' => $show->ends_at->addHours(2)->addMinutes(25)]);
    GameEventEntry::factory()->for($later, 'event')->for($children[0])->for($children[0]->user)->create(['status' => 'registered']);

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $ring->id, $parent->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.rest_period');

    expect($owner->fresh()->coins)->toBe(1000);
    expect($ring->entries()->count())->toBe(0);
});

test('a producer can also be an offspring in another group of the same exhibition', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $ancestor, $children, $show, $ring] = progenyRingFamily();
    $ancestor->update(['born_at' => now()->subDays(80)]);
    $producer = $children[0];
    $producer->update(['born_at' => now()->subDays(40)]);
    $producer->user->forceFill(['coins' => 1000])->save();
    $grandchildren = Pet::factory()->count(3)->for($ancestor->dog)->create(['father_id' => $producer->id, 'born_at' => now()->subDays(18)]);
    foreach ($grandchildren as $child) {
        GameEventEntry::factory()->for($show, 'event')->for($child)->for($child->user)->create([
            'status' => 'registered', 'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')],
        ]);
    }
    prepareExcellentRingFamily($ancestor, [...$children, ...$grandchildren]);
    app(RegisterGameEvent::class)->handle($owner, $ring->id, $ancestor->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $entry = app(RegisterGameEvent::class)->handle($producer->user, $ring->id, $producer->id,
        ['offspring_ids' => $grandchildren->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $this->travelTo($show->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('frozen');
});
