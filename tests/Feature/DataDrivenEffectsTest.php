<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemEffectRule;
use App\Models\ItemPurchase;
use App\Models\Pet;
use App\Models\ShopOffer;
use App\Models\StatusEffect;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Random\Engine;
use Random\Randomizer;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('new database effects and item links work without predefined codes and purchased rules stay unchanged', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 1000]))->create(['mood' => 20, 'mood_max' => 100]);
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create(['quality' => 4]);
    $effect = StatusEffect::factory()->create(['code' => 'new_database_effect', 'modifiers' => ['bond_gain_percent' => 40], 'duration_seconds' => 60]);
    $rule = ItemEffectRule::factory()->for($item)->for($effect, 'statusEffect')->create([
        'chance_percent' => 0, 'chance_by_quality' => [4 => 100], 'duration_seconds' => 120, 'duration_by_quality' => [4 => 900],
    ]);
    $offer = ShopOffer::factory()->for($item)->create();
    $this->actingAs($pet->user)->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page
        ->where('offers.0.grantedEffects.0.code', 'new_database_effect')->where('offers.0.grantedEffects.0.duration_seconds', 900));
    $data = new PurchaseItemData($offer->id, $item->id, 'coins', $offer->price, (string) Str::uuid());
    $purchase = app(PurchaseItem::class)->handle($pet->user, $data);
    $instance = $purchase->inventoryItem;
    $rule->update(['chance_by_quality' => [4 => 0], 'duration_by_quality' => [4 => 1]]);
    $effect->update(['modifiers' => ['bond_gain_percent' => -40], 'is_active' => false]);
    $this->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page
        ->where('items.0.grantedEffects.0.duration_seconds', 900)->where('items.0.grantedEffects.0.modifiers.bond_gain_percent', 40));
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $instance->id], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->buffs[0])->toMatchArray(['code' => 'new_database_effect', 'expires_at' => now()->addSeconds(900)->timestamp, 'modifiers' => ['bond_gain_percent' => 40]]);
    expect($instance->effect_rules)->toBe($purchase->item_snapshot['effect_rules']);
    expect(app(PurchaseItem::class)->handle($pet->user, $data)->id)->toBe($purchase->id);
});

test('random buffs and guaranteed debuffs use the same item rules and notify the player correctly', function (string $kind, int $chance, string $toast) {
    $engine = new class implements Engine
    {
        public int $calls = 0;

        public function generate(): string
        {
            $this->calls++;

            return pack('V', 0);
        }
    };
    $this->app->instance(Randomizer::class, new Randomizer($engine));
    $pet = Pet::factory()->create();
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    $effect = StatusEffect::factory()->create(['kind' => $kind]);
    ItemEffectRule::factory()->for($item)->for($effect, 'statusEffect')->create(['chance_percent' => $chance]);
    $instance = InventoryItem::factory()->for($pet->user)->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), ['variant' => 'toy', 'items' => ['toys' => $instance->id], 'token' => $token])->assertSessionHasNoErrors();
    $this->travel(180)->seconds();
    $this->followingRedirects()->post(route('pets.care.complete', $pet), ['token' => $token])->assertInertia(fn (Assert $initial) => $initial->hasFlash('toast.type', $toast)->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('care.'.$kind.'s.0.code', $effect->code)->where('care.recentIncidents.0.incidents.0.effect.code', $effect->code)));
    expect($engine->calls)->toBe($chance === 100 ? 0 : 1);
    expect($pet->fresh()->getAttribute($kind === 'buff' ? 'debuffs' : 'buffs'))->toBe([]);
})->with(['random buff' => ['buff', 25, 'success'], 'certain debuff' => ['debuff', 100, 'warning']]);

test('unlinked inactive or zero chance item effects never activate', function (string $disabled) {
    $pet = Pet::factory()->create();
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    $effect = StatusEffect::factory()->create(['is_active' => $disabled !== 'effect']);
    if ($disabled !== 'unlinked') {
        ItemEffectRule::factory()->for($item)->for($effect, 'statusEffect')->create([
            'is_active' => $disabled !== 'rule', 'chance_percent' => $disabled === 'chance' ? 0 : 100,
        ]);
    }
    $instance = InventoryItem::factory()->for($pet->user)->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $instance->id], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($care->incidents)->toBeNull();
    expect($pet->fresh()->buffs)->toBe([]);
})->with(['unlinked', 'rule', 'effect', 'chance']);

test('database conditions support buffs and comparison boundaries without code specific to an effect', function (string $operator, bool $active) {
    StatusEffect::factory()->create(['kind' => 'buff', 'condition_state' => 'health', 'condition_operator' => $operator,
        'condition_threshold' => 50, 'duration_seconds' => null, 'modifiers' => ['mood_gain_percent' => 30]]);
    $pet = Pet::factory()->create(['health' => 50, 'health_max' => 100]);
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page->has('care.buffs', $active ? 1 : 0)));
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    expect($care->effects['mood'])->toEqual($active ? 13 : 10);
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $pet->update(['health' => $operator === 'gte' ? 49 : 51]);
    if ($active) {
        $this->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page->has('care.buffs', 0)));
    }
})->with(['lt' => ['lt', false], 'lte' => ['lte', true], 'gt' => ['gt', false], 'gte' => ['gte', true]]);

test('generic state gain and loss modifiers alter care outcomes and are capped', function () {
    $effect = StatusEffect::factory()->create(['modifiers' => ['health_gain_percent' => 40, 'energy_gain_percent' => 20,
        'cleanliness_gain_percent' => 30, 'satiety_loss_percent' => -20, 'hydration_loss_percent' => 20,
        'bond_gain_percent' => 200, 'unknown_gain_percent' => 500]]);
    $pet = Pet::factory()->create(['buffs' => [[...$effect->snapshot(), 'expires_at' => now()->addHour()->timestamp]]]);
    $item = Item::factory()->for(ItemCategory::factory()->state(['code' => 'toys']), 'category')->create();
    $toy = InventoryItem::factory()->for($pet->user)->for($item)->create(['quality' => 1, 'bonuses' => ['health' => 10, 'energy' => 10, 'cleanliness' => 10]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    expect($care->effects)->toMatchArray(['health' => 14, 'energy' => 12, 'cleanliness' => 13, 'satiety' => -4, 'hydration' => -7.2, 'bond' => 6]);
    expect($care->effects)->not->toHaveKey('unknown');
});

test('equal chances for the same effect use one outcome with the longest duration', function () {
    $pet = Pet::factory()->create();
    $effect = StatusEffect::factory()->create();
    $items = [];
    foreach (['collars' => 120, 'leashes' => 900] as $category => $duration) {
        $item = Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')->create();
        ItemEffectRule::factory()->for($item)->for($effect, 'statusEffect')->create(['duration_seconds' => $duration]);
        $items[$category] = InventoryItem::factory()->for($pet->user)->for($item)->create(['effect_rules' => $item->effectRuleSnapshots()])->id;
    }
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'walk', $items, (string) Str::uuid());
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->buffs)->toHaveCount(1);
    expect($pet->fresh()->buffs[0]['expires_at'])->toBe(now()->addSeconds(900)->timestamp);
});

test('migration preserves older item grants and risk settings without replacing purchased properties', function () {
    $migration = require database_path('migrations/2026_09_28_083609_create_item_effect_rules.php');
    $migration->down();
    $category = ItemCategory::factory()->create(['code' => 'food']);
    $item = Item::factory()->for($category, 'category')->create();
    $instance = InventoryItem::factory()->for($item)->create();
    $snapshot = ['name' => ['en' => 'Purchased food'], 'quality' => 2, 'usage_limit' => 5, 'characteristics' => [], 'granted_effects' => ['old_grant']];
    $purchase = ItemPurchase::factory()->for($item)->create(['item_snapshot' => $snapshot]);
    foreach ([['old_grant', 'buff', []], ['old_risk', 'debuff', ['food']]] as [$code, $kind, $categories]) {
        DB::table('status_effects')->insert(['code' => $code, 'kind' => $kind, 'name' => json_encode(['en' => $code]),
            'description' => '{}', 'modifiers' => '{"energy_cost_percent":10}', 'duration_seconds' => 600,
            'risk_categories' => json_encode($categories), 'risk_chance_by_quality' => '{"2":1.25}']);
    }
    DB::table('items')->where('id', $item->id)->update(['granted_effects' => '[]']);
    DB::table('inventory_items')->where('id', $instance->id)->update(['granted_effects' => '["old_grant"]']);
    $migration->up();
    $this->assertDatabaseCount('item_effect_rules', 1);
    expect(array_column(array_column($instance->fresh()->effect_rules, 'effect'), 'code'))->toBe(['old_grant', 'old_risk']);
    expect($purchase->fresh()->item_snapshot)->toMatchArray(['name' => ['en' => 'Purchased food'], 'quality' => 2]);
    expect($purchase->fresh()->item_snapshot['effect_rules'][1]['chance_by_quality'])->toBe([2 => 1.25]);
    expect($purchase->fresh()->item_snapshot)->not->toHaveKey('granted_effects');
});
