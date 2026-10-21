<?php

use App\Models\BreedingLitter;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Queries\GetPetCareer;

beforeEach(function () {
    $this->freezeSecond();
});

/** @return array{GameEvent,GameEvent} */
function queryShowGroup(string $discipline): array
{
    $rules = ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 5, 'field_size' => 8,
        'stages' => ['type', 'structure', 'movement'], 'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'cac_cf', 'format' => 'conformation']];
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'rules' => $rules]);
    $ring = GameEvent::factory()->create(['discipline' => $discipline, 'closes_at' => $show->closes_at,
        'starts_at' => $show->ends_at->addMinutes(10), 'ends_at' => $show->ends_at->addMinutes(20),
        'rules' => [...$rules, 'linked_show_id' => $show->id, 'stages' => ['type', 'uniformity', 'movement'],
            'regulation' => [...$rules['regulation'], 'rank' => $discipline, 'format' => $discipline.'_ring']]]);

    return [$show, $ring];
}

test('individual shows expose every linked collective contest and each group links back to its show', function () {
    $owner = User::factory()->create();
    [$show, $couple] = queryShowGroup('couple');
    $couple->update(['starts_at' => $show->ends_at->addMinutes(30), 'ends_at' => $show->ends_at->addMinutes(40)]);
    $progeny = GameEvent::factory()->create(['discipline' => 'progeny', 'starts_at' => $couple->starts_at->subMinutes(20),
        'ends_at' => $couple->starts_at->subMinutes(10),
        'rules' => [...$couple->rules, 'regulation' => [...$couple->rules['regulation'], 'format' => 'progeny_ring']]]);
    $breeders = GameEvent::factory()->create(['discipline' => 'breeders', 'starts_at' => $couple->starts_at->addMinutes(20),
        'ends_at' => $couple->starts_at->addMinutes(30),
        'rules' => [...$couple->rules, 'regulation' => [...$couple->rules['regulation'], 'format' => 'breeders_ring']]]);
    $query = app(GetGameEvents::class);

    $data = $query->showState($owner, $show, 'en');
    $group = $query->showState($owner, $couple, 'en');

    expect(array_column($data['event']['groupContests'] ?? [], 'id'))->toBe([$progeny->id, $couple->id, $breeders->id]);
    expect($data['event']['progenyContest']['id'] ?? null)->toBe($progeny->id);
    expect($group['event']['linkedShow']['id'] ?? null)->toBe($show->id);
});

test('couple candidates come from the linked show and retain a saved unavailable companion', function () {
    $owner = User::factory()->create();
    [$show, $ring] = queryShowGroup('couple');
    $anchor = Pet::factory()->for($owner)->create();
    $eligible = Pet::factory()->female()->for($owner)->for($anchor->dog)->create();
    $sameSex = Pet::factory()->for($owner)->for($anchor->dog)->create();
    $foreign = Pet::factory()->female()->for($anchor->dog)->create();
    $unregistered = Pet::factory()->female()->for($owner)->for($anchor->dog)->create();
    foreach ([$anchor, $eligible, $sameSex, $foreign] as $pet) {
        GameEventEntry::factory()->for($show, 'event')->for($pet)->for($pet->user)->create(['plan' => ['exhibition_class' => 'open']]);
    }
    GameEventEntry::factory()->for($ring, 'event')->for($anchor)->for($owner)->create(['plan' => ['member_ids' => [$unregistered->id]]]);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $ring);

    $dog = collect($dogs)->firstWhere('id', $anchor->id);
    expect($dog['groupEligibility']['eligible'] ?? null)->toBeTrue();
    expect(array_column($dog['groupMembers'] ?? [], 'id'))->toBe([$eligible->id, $unregistered->id]);
    expect($dog['groupMembers'][0]['eligible'])->toBeTrue();
    expect($dog['groupMembers'][1]['eligible'])->toBeFalse();
    expect($dog['groupMembers'][1]['reason'])->not->toBeEmpty();
});

test('breeder candidates include another owners dogs only when their immutable kennel matches', function () {
    $owner = User::factory()->create();
    [$show, $ring] = queryShowGroup('breeders');
    $origin = ['breeder_key' => 'player:42', 'kennel_key' => 'player:42', 'kennel_name' => 'DogLive #42'];
    $litter = BreedingLitter::factory()->create(['delivered_at' => now(), 'snapshots' => ['breeding_origin' => $origin]]);
    $anchor = Pet::factory()->for($owner)->for($litter->father->dog)->create(['father_id' => $litter->father_id, 'mother_id' => $litter->mother_id]);
    $foreign = Pet::factory()->for($anchor->dog)->create(['father_id' => $litter->father_id, 'mother_id' => $litter->mother_id]);
    foreach ([$anchor, $foreign] as $pet) {
        Puppy::factory()->for($litter, 'litter')->create(['pet_id' => $pet->id, 'status' => 'placed']);
        GameEventEntry::factory()->for($show, 'event')->for($pet)->for($pet->user)->create(['plan' => ['exhibition_class' => 'open']]);
    }
    $unknown = Pet::factory()->for($anchor->dog)->create();
    GameEventEntry::factory()->for($show, 'event')->for($unknown)->for($unknown->user)->create(['plan' => ['exhibition_class' => 'open']]);

    $dogs = app(GetGameEvents::class)->registrationDogs($owner, 'en', $ring);

    expect($dogs[0]['kennelName'] ?? null)->toBe('DogLive #42');
    expect(array_column($dogs[0]['groupMembers'] ?? [], 'id'))->toBe([$foreign->id]);
    expect($dogs[0]['groupMembers'][0])->toMatchArray(['eligible' => true, 'kennelName' => 'DogLive #42', 'ownerName' => $foreign->user->username]);
});

test('a companion career shows its own historical name and shared group awards without personal prize money', function (string $discipline, string $winner) {
    $anchor = Pet::factory()->create();
    $companion = Pet::factory()->for($anchor->dog)->create(['name' => 'Current companion']);
    [, $ring] = queryShowGroup($discipline);
    $ring->update(['status' => 'settled']);
    $entry = GameEventEntry::factory()->for($ring, 'event')->for($anchor)->for($anchor->user)->create([
        'status' => 'completed', 'rank' => 1, 'completed_at' => now(), 'prize' => 100,
        'division' => $discipline.':breed-'.$anchor->dog_id,
        'plan' => ['member_ids' => [$companion->id]], 'snapshot' => ['name' => 'Anchor history'],
        'result' => ['eliminated' => false, 'members' => [['id' => $anchor->id, 'name' => 'Anchor history'], ['id' => $companion->id, 'name' => 'Companion history']]],
    ]);
    PetTitle::factory()->for($entry, 'entry')->for($anchor)->create(['discipline' => $discipline, 'code' => $winner, 'kind' => 'comparison']);
    PetTitle::factory()->for($entry, 'entry')->for($anchor)->create(['discipline' => 'conformation', 'code' => 'CAC', 'kind' => 'certificate']);

    $career = app(GetPetCareer::class)->handle($companion->user, $companion->id, 'en');

    expect($career['results']['entries'])->toHaveCount(1);
    expect($career['results']['entries'][0])->toMatchArray(['petName' => 'Companion history', 'prize' => 0]);
    expect($career['results']['entries'][0]['divisionLabel'])->toStartWith($discipline === 'couple' ? 'Couple competition' : 'Breeders group competition');
    expect(array_column($career['titles'], 'code'))->toBe([$winner]);
    expect($career['summary']['exhibitionStarts'])->toBe(1);
})->with(['couple' => ['couple', 'BEST_COUPLE'], 'breeders' => ['breeders', 'BEST_BREEDERS_GROUP']]);
