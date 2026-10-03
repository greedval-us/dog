<?php

use App\Models\Pet;
use App\Models\StatusEffect;
use App\Modules\Pets\Services\PetStateSynchronizer;

test('advancement applies elapsed modifiers before removing expired effects without saving the pet', function () {
    $this->freezeSecond();
    $effect = StatusEffect::factory()->make([
        'modifiers' => ['mood_decay_percent' => 50], 'duration_seconds' => 3600,
    ]);
    $pet = Pet::factory()->create([
        'mood' => 100, 'mood_max' => 100, 'state_updated_at' => now()->subHours(2),
        'buffs' => [[...$effect->snapshot(), 'expires_at' => now()->subHour()->timestamp]],
    ]);

    $status = app(PetStateSynchronizer::class)->advance($pet, now());

    expect($pet)->mood->toBe(95.0)->buffs->toBe([]);
    expect($pet->state_updated_at->equalTo(now()))->toBeTrue();
    expect($status)->buffs->toBe([]);
    expect($status->modifiers['mood_decay_percent'])->toBe(0);
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'mood' => 100, 'state_updated_at' => now()->subHours(2)->toDateTimeString(),
    ]);
    expect($pet->fresh()->buffs)->toHaveCount(1);
});

test('status synchronization observes changed needs without advancing or persisting them', function () {
    $this->freezeSecond();
    $effect = StatusEffect::factory()->create([
        'kind' => 'debuff', 'duration_seconds' => null,
        'condition_state' => 'mood', 'condition_threshold' => 10,
    ]);
    $pet = Pet::factory()->create([
        'mood' => 5, 'mood_max' => 100, 'state_updated_at' => now()->subHours(2),
        'debuffs' => [$effect->snapshot()],
    ]);
    $pet->mood = 50;

    $status = app(PetStateSynchronizer::class)->synchronize($pet, now());

    expect($pet)->mood->toBe(50.0)->debuffs->toBe([]);
    expect($pet->state_updated_at->equalTo(now()->subHours(2)))->toBeTrue();
    expect($status)->debuffs->toBe([]);
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'mood' => 5, 'state_updated_at' => now()->subHours(2)->toDateTimeString(),
    ]);
    expect($pet->fresh()->debuffs)->toHaveCount(1);
});
