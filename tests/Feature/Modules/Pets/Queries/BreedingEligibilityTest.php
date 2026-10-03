<?php

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Queries\BreedingEligibility;

test('a breeding parent becomes eligible exactly at the age and cooldown boundaries', function () {
    $this->freezeSecond();
    $at = now();
    $pet = Pet::factory()->make(['born_at' => $at->subDays(7)->addSecond()]);
    $eligibility = app(BreedingEligibility::class);

    expect($eligibility->reason($pet, $at))->toBe('breeding.errors.young');
    expect($eligibility->reason($pet, $at->addSecond()))->toBeNull();
    $pet->breeding_available_at = $at->addDays(7);
    expect($eligibility->reason($pet, $pet->breeding_available_at->subSecond()))->toBe('breeding.errors.cooldown');
    expect($eligibility->reason($pet, $pet->breeding_available_at))->toBeNull();
});

test('only explicitly selected system partners bypass age and archival restrictions', function () {
    $this->freezeSecond();
    $at = now();
    $pet = Pet::factory()->make(['retired_at' => $at, 'born_at' => $at]);
    $eligibility = app(BreedingEligibility::class);

    expect($eligibility->reason($pet, $at))->toBe('breeding.errors.archived');
    expect($eligibility->reason($pet, $at, true))->toBeNull();
    $pet->health = 0;
    expect($eligibility->reason($pet, $at, true))->toBe('breeding.errors.unhealthy');
});

test('breeding rejects distant direct ancestry and half siblings while allowing unrelated cousins', function () {
    $dog = Dog::factory()->createOne();
    $owner = User::factory()->createOne();
    $ancestor = Pet::factory()->for($dog)->for($owner)->createOne();
    $child = Pet::factory()->for($dog)->for($owner)->createOne(['father_id' => $ancestor->id]);
    $grandchild = Pet::factory()->for($dog)->for($owner)->createOne(['father_id' => $child->id]);
    $greatGrandchild = Pet::factory()->for($dog)->for($owner)->createOne(['father_id' => $grandchild->id]);
    $halfSibling = Pet::factory()->for($dog)->for($owner)->createOne(['father_id' => $ancestor->id]);
    $cousin = Pet::factory()->for($dog)->for($owner)->createOne(['father_id' => $halfSibling->id]);
    $eligibility = app(BreedingEligibility::class);

    expect($eligibility->related($ancestor, $greatGrandchild))->toBeTrue();
    expect($eligibility->related($greatGrandchild, $ancestor))->toBeTrue();
    expect($eligibility->related($child, $halfSibling))->toBeTrue();
    expect($eligibility->related($grandchild, $cousin))->toBeFalse();
});

test('corrupted ancestral cycles terminate and make the affected parent unavailable for breeding', function () {
    $dog = Dog::factory()->createOne();
    $owner = User::factory()->createOne();
    $first = Pet::factory()->for($dog)->for($owner)->createOne();
    $second = Pet::factory()->for($dog)->for($owner)->createOne();
    $unrelated = Pet::factory()->for($dog)->for($owner)->createOne();
    $first->forceFill(['father_id' => $second->id])->save();
    $second->forceFill(['father_id' => $first->id])->save();

    expect(app(BreedingEligibility::class)->related($first, $unrelated))->toBeTrue();
});
