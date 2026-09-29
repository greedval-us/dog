<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Pet;
use App\Models\StatusEffect;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use Database\Seeders\ItemEffectRuleSeeder;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('stats decay at configured rates without changing potentials or writing on reads', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 0.5, 'endurance' => 0], 'pet_stats.minimum' => 2]);
    $pet = Pet::factory()->create(['speed' => 10, 'speed_potential' => 100, 'endurance' => 10, 'stats_updated_at' => now()->subHours(5)]);
    $this->actingAs($pet->user);
    foreach (range(1, 2) as $read) {
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('pet.stats.speed.value', 8)->where('pet.stats.speed.potential', 100)
            ->where('pet.stats.endurance.value', 10));
    }
    expect($pet->fresh()->speed)->toBe(10);
    expect($pet->fresh()->stat_decay_remainders)->toBeNull();
});

test('frequent saved advances retain fractional loss and match a single offline advance', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 0.6]]);
    $pet = Pet::factory()->create(['speed' => 10]);
    $offline = $pet->replicate();
    $calculator = app(PetDecayCalculator::class);
    for ($i = 0; $i < 12; $i++) {
        $this->travel(10)->minutes();
        $pet->advanceTo(now(), $calculator);
        $pet->save();
        $pet->refresh();
    }
    $offline->advanceTo(now(), $calculator);
    expect($pet->speed)->toBe(9)->toBe($offline->speed);
    expect($pet->stat_decay_remainders['speed'])->toBe(0.2)->toBe($offline->stat_decay_remainders['speed']);
});

test('stats stop at the configured floor without reviving zero stats or accumulating debt', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 1, 'strength' => 1], 'pet_stats.minimum' => 2]);
    $pet = Pet::factory()->create(['speed' => 3, 'strength' => 0, 'stats_updated_at' => now()->subYear()]);
    $pet->advanceTo(now(), app(PetDecayCalculator::class));
    expect($pet->speed)->toBe(2);
    expect($pet->strength)->toBe(0);
    expect($pet->stat_decay_remainders['speed'])->toEqual(0);
});

test('retired dogs and future stat snapshots never decay', function (bool $retired) {
    $pet = Pet::factory()->create(['speed' => 10, 'retired_at' => $retired ? now() : null,
        'stats_updated_at' => $retired ? now()->subYear() : now()->addDay()]);
    $pet->advanceTo(now(), app(PetDecayCalculator::class));
    expect($pet->speed)->toBe(10);
    expect($pet->stat_decay_remainders)->toBeNull();
})->with([true, false]);

test('passive effects affect only their actual lifetime including expiry while offline', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 1, 'strength' => 1]]);
    $effect = StatusEffect::factory()->create(['duration_seconds' => 7200,
        'modifiers' => ['satiety_decay_percent' => -50, 'speed_decay_percent' => -50]])->snapshot();
    $pet = Pet::factory()->create(['satiety' => 100, 'satiety_max' => 100, 'speed' => 20, 'strength' => 20,
        'state_updated_at' => now()->subHours(4), 'stats_updated_at' => now()->subHours(4),
        'buffs' => [[...$effect, 'starts_at' => now()->subHours(3)->timestamp, 'expires_at' => now()->subHour()->timestamp]]]);
    $pet->advanceTo(now(), app(PetDecayCalculator::class));
    expect($pet->satiety)->toBe(85.0);
    expect($pet->speed)->toBe(17);
    expect($pet->strength)->toBe(16);
});

test('overlapping passive buffs are capped and debuffs offset them', function (int $debuff, int $expected) {
    config(['pet_stats.decay_per_hour' => ['speed' => 10]]);
    $buff = StatusEffect::factory()->create(['duration_seconds' => 3600, 'modifiers' => ['stats_decay_percent' => -50, 'speed_decay_percent' => -50]])->snapshot();
    $risk = StatusEffect::factory()->create(['kind' => 'debuff', 'duration_seconds' => 3600, 'modifiers' => ['stats_decay_percent' => $debuff, 'speed_decay_percent' => $debuff]])->snapshot();
    $pet = Pet::factory()->create(['speed' => 100, 'stats_updated_at' => now()->subHour(),
        'buffs' => [[...$buff, 'expires_at' => now()->timestamp]], 'debuffs' => [[...$risk, 'expires_at' => now()->timestamp]]]);
    $pet->advanceTo(now(), app(PetDecayCalculator::class));
    expect($pet->speed)->toBe($expected);
})->with([[0, 95], [40, 92], [50, 90]]);

test('starting care settles stats atomically and a repeated token does not repeat decay', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 1]]);
    $pet = Pet::factory()->create(['speed' => 10, 'hydration' => 50, 'stats_updated_at' => now()->subHours(2)]);
    $token = (string) Str::uuid();
    $action = app(StartPetCare::class);
    $action->handle($pet->user, $pet->id, 'water', [], $token);
    $action->handle($pet->user, $pet->id, 'water', [], $token);
    expect($pet->fresh()->speed)->toBe(8);
    expect($pet->fresh()->stats_updated_at->timestamp)->toBe(now()->timestamp);
    $this->assertDatabaseCount('pet_care_actions', 1);
});

test('late care completion applies the passive buff at the finish time and does not revive it', function () {
    config(['pet_stats.decay_per_hour' => ['speed' => 1]]);
    StatusEffect::factory()->create(['care_variants' => ['water'], 'duration_seconds' => 3600,
        'modifiers' => ['speed_decay_percent' => -50, 'satiety_decay_percent' => -50]]);
    $pet = Pet::factory()->create(['speed' => 10, 'hydration' => 50, 'satiety' => 100, 'satiety_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at->addHours(2));
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $saved = $pet->fresh();
    expect($saved->speed)->toBe(9);
    expect($saved->satiety)->toBe(92.4792);
    expect($saved->buffs)->toBe([]);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->stat_decay_remainders)->toBe($saved->stat_decay_remainders);
});

test('every shop item has a lasting purpose and catalogue updates preserve owned snapshots', function () {
    $this->seed(ShopItemSeeder::class);
    $items = Item::query()->with('effectRules.statusEffect')->get();
    foreach ($items as $item) {
        expect($item->effectRules->contains(fn ($rule): bool => $rule->statusEffect->kind === 'buff'
            && in_array($rule->statusEffect->duration_seconds, [21600, 43200, 86400], true)))->toBeTrue($item->code);
    }
    $item = $items->firstWhere('code', 'agility_bar');
    $owned = InventoryItem::factory()->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);
    $snapshot = $owned->effect_rules;
    $this->seed(ItemEffectRuleSeeder::class);
    expect($owned->fresh()->effect_rules)->toBe($snapshot);
    expect(array_column(array_column($snapshot, 'effect'), 'code'))->toContain('muscle_memory', 'muscle_soreness');
    expect($item->effectRules()->count())->toBe(2);
});
