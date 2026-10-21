<?php

use App\Models\BreedingListing;
use App\Models\CoatInheritanceRule;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\User;
use App\Modules\Pets\Queries\GetBreedingBoard;

beforeEach(function () {
    $this->freezeSecond();
});

/** @return array{User, Pet, Pet, BreedingListing} */
function breedingBoardPair(): array
{
    $dog = Dog::factory()->createOne();
    CoatInheritanceRule::factory()->for($dog)->createOne();
    $owner = User::factory()->createOne(['experience' => '1500']);
    $seller = User::factory()->createOne(['experience' => '1500']);
    $mother = Pet::factory()->female()->for($owner)->for($dog)->createOne();
    $father = Pet::factory()->for($seller)->for($dog)->createOne();
    $listing = BreedingListing::factory()->for($father, 'pet')->for($seller)->createOne();

    return [$owner, $mother, $father, $listing];
}

test('the breeding board exposes the actual game month age limits', function () {
    [$owner] = breedingBoardPair();

    $board = app(GetBreedingBoard::class)->handle($owner, 'en');

    expect($board['breedingMinimumMonths'] ?? null)->toBe(18);
    expect($board['breedingMaximumMonths'] ?? null)->toBe(120);
});

test('breeding cards and forecast show untreated parent disease', function (string $parent) {
    [$owner, $mother, $father, $listing] = breedingBoardPair();
    $patient = $parent === 'mother' ? $mother : $father;
    PetDisease::factory()->for($patient)->createOne();

    $board = app(GetBreedingBoard::class)->handle($owner, 'en', $mother->id, 'listing', $listing->id);

    expect($board['preview']['reason'])->toBe('breeding.errors.unhealthy');
    expect($board['preview'][$parent]['reason'])->toBe('breeding.errors.unhealthy');
    $card = $parent === 'mother' ? $board['dogs'][0] : $board['listings'][0]['pet'];
    expect($card['reason'])->toBe('breeding.errors.unhealthy');
})->with(['own dog' => ['mother'], 'listed dog' => ['father']]);

test('a non purebred owned dog explains its breeding restriction without a selected mate', function () {
    [$owner, $mother] = breedingBoardPair();
    $mother->update(['is_purebred' => false]);

    $board = app(GetBreedingBoard::class)->handle($owner, 'en', $mother->id);

    expect($board['dogs'][0]['reason'])->toBe('breeding.errors.incompatible');
    expect($board['preview'])->toBeNull();
});

test('breeding cards and forecast respect an existing physical event reservation', function (string $parent) {
    [$owner, $mother, $father, $listing] = breedingBoardPair();
    $reserved = $parent === 'mother' ? $mother : $father;
    $event = GameEvent::factory()->createOne(['closes_at' => now()->subSecond()]);
    GameEventEntry::factory()->for($event, 'event')->for($reserved, 'pet')->createOne(['user_id' => $reserved->user_id]);

    $board = app(GetBreedingBoard::class)->handle($owner, 'en', $mother->id, 'listing', $listing->id);

    expect($board['preview']['reason'])->toBe('breeding.errors.unavailable');
    expect($board['preview'][$parent]['reason'])->toBe('breeding.errors.unavailable');
    $card = $parent === 'mother' ? $board['dogs'][0] : $board['listings'][0]['pet'];
    expect($card['reason'])->toBe('breeding.errors.unavailable');
})->with(['own dog' => ['mother'], 'listed dog' => ['father']]);

test('a reservation ending now or opening after the mating window does not block breeding', function (string $boundary) {
    [$owner, $mother, , $listing] = breedingBoardPair();
    $event = GameEvent::factory()->createOne($boundary === 'ended'
        ? ['closes_at' => now()->subHour(), 'starts_at' => now()->subMinutes(45), 'ends_at' => now()]
        : ['closes_at' => now()->addSeconds(2)]);
    GameEventEntry::factory()->for($event, 'event')->for($mother, 'pet')->for($owner)->createOne();

    $board = app(GetBreedingBoard::class)->handle($owner, 'en', $mother->id, 'listing', $listing->id);

    expect($board['preview']['reason'])->toBeNull();
    expect($board['dogs'][0]['reason'])->toBeNull();
})->with(['exact event end' => ['ended'], 'after one second mating window' => ['future']]);

test('a documentary progeny entry does not block a breeding forecast', function () {
    [$owner, $mother, , $listing] = breedingBoardPair();
    $event = GameEvent::factory()->createOne(['discipline' => 'progeny', 'closes_at' => now()->subSecond()]);
    GameEventEntry::factory()->for($event, 'event')->for($mother, 'pet')->for($owner)->createOne();

    $board = app(GetBreedingBoard::class)->handle($owner, 'en', $mother->id, 'listing', $listing->id);

    expect($board['preview']['reason'])->toBeNull();
    expect($board['dogs'][0]['reason'])->toBeNull();
});
