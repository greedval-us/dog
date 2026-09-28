<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemEffectRule;
use App\Models\Pet;
use App\Models\StatusEffect;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Queries\GetPetStatuses;
use Database\Seeders\ItemEffectRuleSeeder;
use Database\Seeders\ShopItemSeeder;
use Database\Seeders\StatusEffectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('need tiers replace one another at exact percentage boundaries', function (string $state, float $value, ?string $code) {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create([$state => $value * 2, $state.'_max' => 200]);

    $status = app(GetPetStatuses::class)->handle($pet, now());

    expect(array_column($status->debuffs, 'code'))->toBe($code === null ? [] : [$code]);
    expect($pet->fresh()->debuffs)->toBeNull();
})->with([
    'severe hunger' => ['satiety', 4.99, 'famished'],
    'mild hunger boundary' => ['satiety', 5, 'hungry'],
    'hunger recovery boundary' => ['satiety', 20, null],
    'severe thirst' => ['hydration', 4.99, 'dehydrated'],
    'mild thirst boundary' => ['hydration', 5, 'thirsty'],
    'thirst recovery boundary' => ['hydration', 20, null],
    'severe dirt' => ['cleanliness', 4.99, 'filthy'],
    'mild dirt boundary' => ['cleanliness', 5, 'dirty'],
    'dirt recovery boundary' => ['cleanliness', 20, null],
    'exhaustion' => ['energy', 4.99, 'exhausted'],
    'fatigue boundary' => ['energy', 5, 'tired'],
    'fatigue recovery boundary' => ['energy', 20, null],
]);

test('compound bonuses require every need and disappear as soon as one falls short', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['health' => 80, 'energy' => 80, 'satiety' => 80, 'hydration' => 80,
        'mood' => 80, 'cleanliness' => 80, 'bond' => 50]);
    $query = app(GetPetStatuses::class);

    expect(array_column($query->handle($pet, now())->buffs, 'code'))->toContain('thriving');
    $pet->hydration = 79.99;
    expect(array_column($query->handle($pet, now())->buffs, 'code'))->not->toContain('thriving', 'well_hydrated');
    $pet->hydration = 59.99;
    expect(array_column($query->handle($pet, now())->buffs, 'code'))->not->toContain('well_fed');
});

test('compound-only database conditions reject invalid clauses', function (array $conditions, bool $active) {
    StatusEffect::factory()->create(['conditions' => $conditions, 'duration_seconds' => null]);
    $pet = Pet::factory()->create(['mood' => 50]);

    expect(app(GetPetStatuses::class)->handle($pet, now())->buffs)->toHaveCount($active ? 1 : 0);
})->with([
    'two valid clauses' => [[['state' => 'mood', 'operator' => 'gte', 'threshold' => 50], ['state' => 'energy', 'operator' => 'gte', 'threshold' => 50]], true],
    'one fails' => [[['state' => 'mood', 'operator' => 'gt', 'threshold' => 50]], false],
    'unknown state' => [[['state' => 'missing', 'operator' => 'gte', 'threshold' => 0]], false],
    'unknown operator' => [[['state' => 'mood', 'operator' => 'eq', 'threshold' => 50]], false],
    'invalid threshold' => [[['state' => 'mood', 'operator' => 'lt', 'threshold' => 101]], false],
]);

test('free care previews and snapshots its buff and repeating completion cannot extend it', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['mood' => 50]);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('care.options.4.grantedEffects.0.code', 'companionship'));

    $this->post(route('pets.care.store', $pet), ['variant' => 'attention', 'items' => [], 'token' => $token])->assertSessionHasNoErrors();
    StatusEffect::query()->where('code', 'companionship')->update(['duration_seconds' => 1, 'is_active' => false]);
    $this->travel(120)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $expires = now()->addSeconds(1500)->timestamp;
    $this->travel(10)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();

    expect(collect($pet->fresh()->buffs)->firstWhere('code', 'companionship'))->toMatchArray([
        'expires_at' => $expires, 'modifiers' => ['bond_gain_percent' => 20],
    ]);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('item_usages', 0);
});

test('late completion does not revive a free care buff', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['hydration' => 50]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at->addSeconds(900));

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    expect(array_column($pet->fresh()->buffs, 'code'))->not->toContain('refreshed');
});

test('water relieves poisoning even at full hydration and recovery is applied only once', function () {
    $this->seed(StatusEffectSeeder::class);
    $poison = StatusEffect::query()->where('code', 'poisoning')->firstOrFail();
    $expires = now()->addSeconds(1800)->timestamp;
    $pet = Pet::factory()->create(['debuffs' => [[...$poison->snapshot(), 'expires_at' => $expires]]]);
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('care.options.1.reason', null)->where('care.options.1.statusRecovery.poisoning', 300));

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $poison->update(['recovery_actions' => ['water' => 1800]]);
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    expect($pet->fresh()->debuffs[0]['expires_at'])->toBe($expires - 300);
    expect($care->status_recovery)->toBe(['poisoning' => 300]);
});

test('failed completion rolls back recovery and its retry applies the reduction once', function () {
    $effect = StatusEffect::factory()->create(['kind' => 'debuff', 'recovery_actions' => ['water' => 300]]);
    $expires = now()->addHour()->timestamp;
    $pet = Pet::factory()->create(['hydration' => 50, 'debuffs' => [[...$effect->snapshot(), 'expires_at' => $expires]]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    DB::statement("CREATE TRIGGER reject_recovery BEFORE UPDATE ON pet_care_actions BEGIN SELECT RAISE(ABORT, 'Failure'); END");

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);
    expect($pet->fresh()->debuffs[0]['expires_at'])->toBe($expires);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 50, 'activity_token' => $care->activity_token]);
    DB::statement('DROP TRIGGER reject_recovery');
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->debuffs[0]['expires_at'])->toBe($expires - 300);
});

test('sleep can restore health at full energy and clear a timed incident', function () {
    $this->seed(StatusEffectSeeder::class);
    $injury = StatusEffect::query()->where('code', 'minor_injury')->firstOrFail();
    $pet = Pet::factory()->create(['health' => 20, 'energy' => 100,
        'debuffs' => [[...$injury->snapshot(), 'expires_at' => now()->addSeconds(2400)->timestamp]]]);

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'sleep', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 24, 'energy' => 100]);
    expect(array_column($pet->fresh()->debuffs, 'code'))->toBe(['unwell']);
    expect(array_column($pet->fresh()->buffs, 'code'))->toContain('deep_rest');
});

test('maximum combined penalties never make basic recovery cost energy', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['satiety' => 0, 'hydration' => 0, 'energy' => 0, 'health' => 0, 'mood' => 0, 'cleanliness' => 0]);

    $status = app(GetPetStatuses::class)->handle($pet, now());
    expect($status->modifiers['energy_cost_percent'])->toBe(50);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 25, 'health' => 1]);
});

test('catalogue reseeding keeps custom effects and purchased snapshots while adding new item choices', function () {
    $this->seed(ShopItemSeeder::class);
    $custom = StatusEffect::factory()->create(['code' => 'custom_effect', 'modifiers' => ['mood_gain_percent' => 7]]);
    $item = Item::query()->where('code', 'beef_treats')->firstOrFail();
    $instance = InventoryItem::factory()->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);
    $snapshot = $instance->effect_rules;
    StatusEffect::query()->where('code', 'eager')->update(['is_active' => false]);

    $this->seed([StatusEffectSeeder::class, ItemEffectRuleSeeder::class]);
    $this->seed([StatusEffectSeeder::class, ItemEffectRuleSeeder::class]);

    $this->assertDatabaseCount('status_effects', 34);
    expect($custom->fresh()->modifiers)->toBe(['mood_gain_percent' => 7]);
    expect($instance->fresh()->effect_rules)->toBe($snapshot);
    expect(array_column(array_column($snapshot, 'effect'), 'code'))->toContain('eager');
    $this->assertDatabaseHas('status_effects', ['code' => 'eager', 'is_active' => false]);
    expect($item->effectRules()->count())->toBe(2);
});

test('care and toys carry distinct mild risks that get shorter as quality improves', function (string $category, string $code, int $quality, int $duration) {
    $this->seed(StatusEffectSeeder::class);
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')->create();
    (new ItemEffectRuleSeeder)->seedFor($item);

    $outcomes = app(ItemEffectRules::class)->forItem($item->effectRuleSnapshots(), $quality, $item->name);

    expect($outcomes)->toHaveCount(1);
    expect($outcomes[0]['effect'])->toMatchArray(['code' => $code, 'duration_seconds' => $duration]);
    expect($outcomes[0]['chance'])->toBe($quality === 1 ? 300 : 50);
})->with([
    ['care', 'skin_irritation', 1, 1200], ['care', 'skin_irritation', 5, 600],
    ['toys', 'overstimulated', 1, 900], ['toys', 'overstimulated', 5, 450],
]);

test('the same guaranteed effect from care and an item uses one longest award', function () {
    $effect = StatusEffect::factory()->create(['care_variants' => ['toy'], 'duration_seconds' => 600]);
    $pet = Pet::factory()->create();
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    ItemEffectRule::factory()->for($item)->for($effect, 'statusEffect')->create(['duration_seconds' => 900]);
    $instance = InventoryItem::factory()->for($pet->user)->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $instance->id], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    expect($care->granted_effects)->toHaveCount(1);
    expect($pet->fresh()->buffs)->toHaveCount(1);
    expect($pet->fresh()->buffs[0]['expires_at'])->toBe(now()->addSeconds(900)->timestamp);
});

test('upgrading existing toy rules replaces the default injury risk without altering purchased snapshots', function () {
    $this->seed(StatusEffectSeeder::class);
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    $injury = StatusEffect::query()->where('code', 'minor_injury')->firstOrFail();
    $rule = ItemEffectRule::factory()->for($item)->for($injury, 'statusEffect')->create([
        'chance_percent' => 0, 'chance_by_quality' => [1 => 3, 2 => 2.5, 3 => 2, 4 => 1, 5 => 0.5],
        'duration_seconds' => null, 'duration_by_quality' => null,
    ]);
    $instance = InventoryItem::factory()->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);
    $original = $instance->effect_rules;

    $this->seed(ItemEffectRuleSeeder::class);
    $this->seed(ItemEffectRuleSeeder::class);

    expect($rule->fresh()->is_active)->toBeFalse();
    expect(array_column(array_column($item->fresh()->effectRuleSnapshots(), 'effect'), 'code'))->toBe(['overstimulated']);
    expect($instance->fresh()->effect_rules)->toBe($original);
});

test('older effect snapshots gain recovery options without replacing their modifiers or expiry', function () {
    $this->seed(StatusEffectSeeder::class);
    $effect = StatusEffect::query()->where('code', 'poisoning')->firstOrFail()->snapshot();
    unset($effect['recovery_actions']);
    $effect['modifiers'] = ['mood_gain_percent' => -7];
    $effect['expires_at'] = now()->addHour()->timestamp;
    $pet = Pet::factory()->create(['debuffs' => [$effect]]);

    $status = app(GetPetStatuses::class)->handle($pet, now());

    expect($status->debuffs[0])->toMatchArray([
        'modifiers' => ['mood_gain_percent' => -7], 'expires_at' => $effect['expires_at'],
        'recovery_actions' => ['water' => 300, 'nap' => 600, 'sleep' => 1800],
    ]);
    expect($pet->fresh()->debuffs)->toBe([$effect]);
});
