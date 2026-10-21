<?php

use App\Models\BreedingLitter;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetPendingEventRegistrations;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Queries\GetShowGroup;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{User,Pet,Pet,GameEvent,GameEvent} */
function enteredCouple(string $system = 'rkf'): array
{
    $owner = User::factory()->create(['coins' => 1000]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $attributes = ['born_at' => now()->subDays(20), 'exterior' => ['type' => 100, 'structure' => 100, 'movement' => 100],
        'obedience' => 100, 'bond' => 100, 'mood' => 100, 'cleanliness' => 100, 'health' => 100,
        'energy' => 100, 'hydration' => 100, 'satiety' => 100];
    $male = Pet::factory()->for($owner)->for($breed)->create([...$attributes, 'sex' => 'male']);
    $female = Pet::factory()->female()->for($owner)->for($breed)->create($attributes);
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 1,
        'stages' => ['type', 'structure', 'movement'],
        'regulation' => ['version' => 2, 'system' => $system, 'rank' => 'cac_cf', 'format' => 'conformation'],
    ]]);
    $ring = GameEvent::factory()->create(['discipline' => 'couple', 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 1,
            'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => ['version' => 2, 'system' => $system, 'rank' => 'couple', 'format' => 'couple_ring']],
    ]);
    foreach ([$male, $female] as $member) {
        GameEventEntry::factory()->for($show, 'event')->for($member)->for($owner)->create([
            'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')],
        ]);
    }

    return [$owner, $male, $female, $show, $ring];
}

test('a couple physically enters its linked show ring and both dogs retain the group result and awards', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $male, $female, $show, $ring] = enteredCouple();

    $entry = app(RegisterGameEvent::class)->handle($owner, $ring->id, $male->id,
        ['member_ids' => [$female->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    expect($owner->fresh()->coins)->toBe(975);
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('frozen');
    expect(array_column($entry->fresh()->snapshot['members'], 'id'))->toBe([$male->id, $female->id]);
    expect($female->fresh()->activity_token)->toBe($entry->operation_token);
    expect($male->fresh()->activity_token)->toBe($entry->operation_token);
    $coinsBefore = $owner->fresh()->coins;
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->fresh()->rank)->toBe(1);
    expect($entry->titles()->pluck('code')->sort()->values()->all())->toBe(['BEST_COUPLE', 'COUPLE_BREED_WINNER', 'COUPLE_PLACE_1']);
    expect($owner->fresh()->coins)->toBe($coinsBefore + 100);
    foreach ([$male, $female] as $member) {
        expect($member->fresh()->activity_token)->toBeNull();
        $career = app(GetPetCareer::class)->handle($owner, $member->id, 'en');
        expect(array_column($career['results']['entries'], 'eventId'))->toContain($ring->id);
        expect(array_column($career['titles'], 'code'))->toContain('BEST_COUPLE');
        expect($member->sportRecords()->where('discipline', 'couple')->value('starts'))->toBe(1);
    }
    expect($entry->titles()->whereIn('kind', ['certificate', 'championship'])->count())->toBe(0);
});

test('changing the anchor cannot enter the same couple twice', function () {
    $this->freezeSecond();
    [$owner, $male, $female, , $ring] = enteredCouple();
    app(RegisterGameEvent::class)->handle($owner, $ring->id, $male->id,
        ['member_ids' => [$female->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());

    expect(fn () => app(RegisterGameEvent::class)->handle($owner, $ring->id, $female->id,
        ['member_ids' => [$male->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class, 'events.errors.group_duplicate');

    expect($ring->entries()->count())->toBe(1);
    expect($owner->fresh()->coins)->toBe(975);
});

test('each individual exhibition has three sequential collective contests with a shared registration deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00:00', 'Europe/Moscow'));
    app(GameEventSchedule::class)->ensureUpcoming();
    $count = GameEvent::query()->count();
    app(GameEventSchedule::class)->ensureUpcoming();

    expect(GameEvent::query()->count())->toBe($count);
    config(['game-events.progeny_ring.delay_minutes' => 60]);
    foreach (GameEvent::query()->where('discipline', 'conformation')->get() as $show) {
        $rings = GameEvent::query()->where('rules->linked_show_id', $show->id)->orderBy('starts_at')->get();
        expect($rings->pluck('discipline')->all())->toBe(['progeny', 'couple', 'breeders']);
        foreach ($rings as $index => $ring) {
            expect($ring->closes_at)->toEqual($show->closes_at);
            expect($ring->starts_at)->toEqual($show->ends_at->addMinutes(10 + $index * 20));
            expect(app(GetShowGroup::class)->freezeAt($ring))->toEqual($show->ends_at->addMinutes($index * 20));
            if ($index > 0) {
                expect($ring->starts_at->isAfter($rings[$index - 1]->ends_at))->toBeTrue();
            }
        }
    }
});

test('a later collective ring does not block unrelated owner actions before its freeze boundary', function () {
    $this->freezeSecond();
    [$owner, $male, $female, $show, $ring] = enteredCouple();
    $show->update(['status' => 'settled']);
    $freezeAt = now()->addMinutes(20);
    $ring->update(['closes_at' => now()->subMinute(), 'rules' => [...$ring->rules, 'group_freezes_at' => $freezeAt->utc()->toIso8601String()]]);
    GameEventEntry::factory()->for($ring, 'event')->for($male)->for($owner)->create(['plan' => ['member_ids' => [$female->id]]]);

    expect(app(GetPendingEventRegistrations::class)->handle($owner, now()))->toBeFalse();
    expect(app(GetPendingEventRegistrations::class)->handle($owner, $freezeAt))->toBeTrue();
});

test('a breeders group admits sold dogs from one recorded kennel and releases every owner after the final', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $anchor, $second, $show, $ring] = enteredCouple();
    $secondOwner = User::factory()->create();
    $second->update(['user_id' => $secondOwner->id]);
    $show->entries()->where('pet_id', $second->id)->update(['user_id' => $secondOwner->id]);
    $third = Pet::factory()->for($anchor->dog)->create($anchor->only(['born_at', 'exterior', 'obedience', 'bond', 'mood', 'cleanliness', 'health', 'energy', 'hydration', 'satiety']));
    GameEventEntry::factory()->for($show, 'event')->for($third)->for($third->user)->create([
        'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')],
    ]);
    $mother = Pet::factory()->female()->for($anchor->dog)->create();
    $father = Pet::factory()->for($anchor->dog)->create();
    $litter = BreedingLitter::factory()->create(['mother_id' => $mother->id, 'father_id' => $father->id, 'delivered_at' => now(),
        'snapshots' => ['breeding_origin' => ['breeder_key' => 'player:42', 'kennel_key' => 'player:42', 'kennel_name' => 'DogLive #42']]]);
    foreach ([$anchor, $second, $third] as $member) {
        $member->update(['father_id' => $father->id, 'mother_id' => $mother->id]);
        Puppy::factory()->for($litter, 'litter')->create(['pet_id' => $member->id, 'status' => 'placed']);
    }
    $ring->update(['discipline' => 'breeders', 'rules' => [...$ring->rules,
        'regulation' => [...$ring->rules['regulation'], 'rank' => 'breeders', 'format' => 'breeders_ring']]]);

    DB::enableQueryLog();
    $entry = app(RegisterGameEvent::class)->handle($owner, $ring->id, $anchor->id,
        ['member_ids' => [$second->id, $third->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $lockedIds = static fn (string $table): array => array_merge(...array_column(array_values(array_filter($queries,
        static fn (array $query): bool => str_contains($query['query'], 'from "'.$table.'"') && str_contains($query['query'], 'for update'))), 'bindings'));
    expect($lockedIds('users'))->toContain($secondOwner->id, $third->user_id);
    expect($lockedIds('pets'))->toContain($second->id, $third->id);
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('frozen');
    foreach ([$anchor, $second, $third] as $member) {
        expect($member->fresh()->activity_token)->toBe($entry->operation_token);
    }
    $this->travelTo($ring->ends_at);
    app(GameEventProcessor::class)->processForOwner($secondOwner);
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->titles()->pluck('code')->sort()->values()->all())->toBe(['BEST_BREEDERS_GROUP', 'BREEDERS_BREED_WINNER', 'BREEDERS_PLACE_1']);
    foreach ([$anchor, $second, $third] as $member) {
        expect($member->fresh()->activity_token)->toBeNull();
        expect(array_column(app(GetPetCareer::class)->handle($member->user, $member->id, 'ru')['titles'], 'code'))->toContain('BEST_BREEDERS_GROUP');
        expect($member->sportRecords()->where('discipline', 'breeders')->value('starts'))->toBe(1);
    }
    expect($ring->entries()->where('is_npc', true)->count())->toBe(0);
});

test('one family can finish producer couple and breeders rings in sequence without a false rest or busy refusal', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 00:00:00', 'Europe/Moscow'));
    [$owner, $father, $mother, $show, $couple] = enteredCouple();
    $litter = BreedingLitter::factory()->create(['father_id' => $father->id, 'mother_id' => $mother->id, 'delivered_at' => now(),
        'snapshots' => ['breeding_origin' => ['breeder_key' => 'player:'.$owner->id, 'kennel_key' => 'player:'.$owner->id, 'kennel_name' => 'DogLive #'.$owner->id]]]);
    $children = Pet::factory()->count(3)->for($owner)->for($father->dog)->create([
        ...$father->only(['exterior', 'obedience', 'bond', 'mood', 'cleanliness', 'health', 'energy', 'hydration', 'satiety']),
        'father_id' => $father->id, 'mother_id' => $mother->id, 'born_at' => now()->subDays(18),
    ]);
    foreach ($children as $child) {
        Puppy::factory()->for($litter, 'litter')->create(['pet_id' => $child->id, 'status' => 'placed']);
        GameEventEntry::factory()->for($show, 'event')->for($child)->for($owner)->create([
            'plan' => ['exhibition_class' => 'open', 'stages' => array_fill(0, 3, 'balanced')],
        ]);
    }
    $rings = [];
    foreach (['progeny', 'couple', 'breeders'] as $index => $discipline) {
        $attributes = ['discipline' => $discipline, 'closes_at' => $show->closes_at,
            'starts_at' => $show->ends_at->addMinutes(10 + $index * 20), 'ends_at' => $show->ends_at->addMinutes(20 + $index * 20),
            'rules' => [...$couple->rules, 'group_freezes_at' => $show->ends_at->addMinutes($index * 20)->utc()->toIso8601String(),
                'regulation' => [...$couple->rules['regulation'], 'rank' => $discipline, 'format' => $discipline.'_ring']]];
        $ring = $discipline === 'couple' ? tap($couple)->update($attributes) : GameEvent::factory()->create($attributes);
        $rings[$discipline] = $ring;
    }
    $producerEntry = app(RegisterGameEvent::class)->handle($owner, $rings['progeny']->id, $father->id,
        ['offspring_ids' => $children->modelKeys(), 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $coupleEntry = app(RegisterGameEvent::class)->handle($owner, $couple->id, $father->id,
        ['member_ids' => [$mother->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $breedersEntry = app(RegisterGameEvent::class)->handle($owner, $rings['breeders']->id, $children[0]->id,
        ['member_ids' => [$children[1]->id, $children[2]->id], 'stages' => array_fill(0, 3, 'balanced')], [], 25, (string) Str::uuid());
    $this->travelTo($show->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($producerEntry->fresh()->status)->toBe('frozen');
    expect($coupleEntry->fresh()->status)->toBe('registered');
    expect($breedersEntry->fresh()->snapshot)->toBeNull();
    $this->travelTo($rings['progeny']->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($producerEntry->fresh()->status)->toBe('completed');
    expect($coupleEntry->fresh()->status)->toBe('frozen');
    expect($father->fresh()->activity_token)->toBe($coupleEntry->operation_token);
    $this->travelTo($couple->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($coupleEntry->fresh()->status)->toBe('completed');
    expect($breedersEntry->fresh()->status)->toBe('frozen');
    $this->travelTo($rings['breeders']->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($breedersEntry->fresh()->status)->toBe('completed');
    foreach ([$father, $mother, ...$children] as $member) {
        expect($member->fresh()->activity_token)->toBeNull();
    }
});
