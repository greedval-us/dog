<?php

use App\Models\Pet;
use App\Modules\Pets\Queries\GetPetStatSnapshot;

test('attribute snapshots preserve all individual values and genetic limits independently of later pet changes', function () {
    $pet = Pet::factory()->create([
        'endurance' => 11, 'endurance_potential' => 101,
        'speed' => 12, 'speed_potential' => 102,
        'strength' => 13, 'strength_potential' => 103,
        'agility' => 14, 'agility_potential' => 104,
        'obedience' => 15, 'obedience_potential' => 105,
        'intelligence' => 16, 'intelligence_potential' => 106,
    ]);

    $snapshot = app(GetPetStatSnapshot::class)->handle($pet);
    $pet->intelligence = 100;
    $pet->endurance_potential = 999;

    expect($snapshot->values)->toBe([
        'endurance' => 11, 'speed' => 12, 'strength' => 13,
        'agility' => 14, 'obedience' => 15, 'intelligence' => 16,
    ]);
    expect($snapshot->potentials)->toBe([
        'endurance' => 101, 'speed' => 102, 'strength' => 103,
        'agility' => 104, 'obedience' => 105, 'intelligence' => 106,
    ]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'intelligence' => 16, 'endurance_potential' => 101]);
});
