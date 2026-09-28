<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemEffectRule;
use App\Models\Pet;
use App\Models\StatusEffect;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Queries\GetPetStatuses;
use Database\Seeders\ShopItemSeeder;
use Database\Seeders\StatusEffectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Random\Engine;
use Random\Randomizer;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    $this->app->instance(Randomizer::class, new Randomizer(new class implements Engine
    {
        public function generate(): string
        {
            return pack('V', 9999);
        }
    }));
});

function statusItem(Pet $pet, string $category, array $attributes = []): InventoryItem
{
    $codes = $attributes['effect_codes'] ?? [];
    unset($attributes['effect_codes']);
    $instance = InventoryItem::factory()->for($pet->user)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['quality' => 1, ...$attributes]);
    foreach (StatusEffect::query()->whereIn('code', $codes)->get() as $effect) {
        ItemEffectRule::factory()->for($instance->item)->for($effect, 'statusEffect')->create();
    }
    $instance->update(['effect_rules' => $instance->item->effectRuleSnapshots()]);

    return $instance;
}

test('purchased bonuses survive catalogue edits and appear in shop and inventory', function () {
    $this->seed(ShopItemSeeder::class);
    $user = User::factory()->create(['coins' => 1000]);
    $item = Item::query()->where('code', 'leather_collar')->firstOrFail();
    $offer = $item->offers()->firstOrFail();
    $purchase = app(PurchaseItem::class)->handle($user, new PurchaseItemData($offer->id, $item->id, 'coins', $offer->price, (string) Str::uuid()));
    $this->actingAs($user)->get(route('shop.index', ['category' => $item->item_category_id]))->assertInertia(fn (Assert $page) => $page
        ->where('offers.2.bonuses.mood', 5)->where('offers.2.grantedEffects.0.code', 'comfortable'));
    $item->update(['bonuses' => ['mood' => 1]]);
    $item->effectRules()->delete();

    expect($purchase->inventoryItem->bonuses)->toBe(['mood' => 5]);
    expect(array_column(array_column($purchase->item_snapshot['effect_rules'], 'effect'), 'code'))->toContain('comfortable');
    $this->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page
        ->where('items.0.bonuses.mood', 5)->where('items.0.grantedEffects.0.code', 'comfortable'));
});

test('item bonuses and timed buffs are saved at start and awarded only once after completion', function () {
    $effect = StatusEffect::factory()->create(['code' => 'comfort']);
    $pet = Pet::factory()->create(['mood' => 0, 'mood_max' => 100, 'energy' => 50]);
    $toy = statusItem($pet, 'toys', ['remaining_uses' => 1, 'bonuses' => ['mood' => 7], 'effect_codes' => ['comfort']]);
    $token = (string) Str::uuid();
    $payload = ['variant' => 'toy', 'items' => ['toys' => $toy->id], 'token' => $token];
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->assertModelMissing($toy);
    expect($pet->fresh()->buffs)->toBe([]);
    $effect->update(['modifiers' => ['energy_cost_percent' => -50], 'duration_seconds' => 60]);
    $this->travel(180)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 25, 'energy' => 40]);
    expect($pet->fresh()->buffs)->toHaveCount(1);
    expect($pet->fresh()->buffs[0])->toMatchArray(['expires_at' => now()->addSeconds(1800)->timestamp, 'modifiers' => ['energy_cost_percent' => -10]]);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('care.buffs.0.code', 'comfort'));
    $this->assertDatabaseCount('item_usages', 1);
});

test('active buffs reduce energy cost and increase mood while expired buffs do not', function (bool $expired, int $energy, float $mood) {
    $effect = StatusEffect::factory()->create(['modifiers' => ['energy_cost_percent' => -50, 'mood_gain_percent' => 20]]);
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 0, 'mood_max' => 100,
        'buffs' => [[...$effect->snapshot(), 'expires_at' => now()->addSeconds($expired ? 0 : 600)->timestamp]]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => $energy]);
    $this->travel(120)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => $mood]);
})->with(['active' => [false, 47, 12.0], 'expiry boundary' => [true, 44, 10.0]]);

test('late completion cannot restart an already expired buff', function () {
    $effect = StatusEffect::factory()->create(['duration_seconds' => 60]);
    $pet = Pet::factory()->create();
    $toy = statusItem($pet, 'toys', ['effect_codes' => [$effect->code]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    $this->travel(240)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->buffs)->toBe([]);
});

test('repeated sources refresh one buff without stacking its strength', function () {
    $effect = StatusEffect::factory()->create();
    $pet = Pet::factory()->create(['buffs' => [[...$effect->snapshot(), 'expires_at' => now()->addSeconds(600)->timestamp]]]);
    $collar = statusItem($pet, 'collars', ['effect_codes' => [$effect->code]]);
    $leash = statusItem($pet, 'leashes', ['effect_codes' => [$effect->code]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'walk', ['collars' => $collar->id, 'leashes' => $leash->id], (string) Str::uuid());
    $this->travel(300)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->buffs)->toHaveCount(1);
    expect($pet->fresh()->buffs[0]['expires_at'])->toBe(now()->addSeconds(1800)->timestamp);
});

test('low needs show persistent debuffs and recovering the need removes them', function (string $state, string $variant, string $code) {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create([$state => 15, $state.'_max' => 100]);
    $items = $variant === 'meal' ? ['food' => statusItem($pet, 'food')->id] : [];
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('care.debuffs.0.code', $code)->where('care.debuffs.0.expires_at', null));
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, $variant, $items, (string) Str::uuid());
    expect($pet->fresh()->debuffs[0]['code'])->toBe($code);
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->debuffs)->toBe([]);
})->with(['hunger' => ['satiety', 'meal', 'hungry'], 'thirst' => ['hydration', 'water', 'thirsty'], 'dirt' => ['cleanliness', 'wash', 'dirty']]);

test('debuffs change the displayed and charged energy and mood gain', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 0, 'mood_max' => 100, 'satiety' => 15, 'satiety_max' => 100,
        'hydration' => 15, 'hydration_max' => 100, 'cleanliness' => 15, 'cleanliness_max' => 100]);
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('care.options.4.energy', 9));
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 41]);
    $this->travel(120)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 7.5]);
});

test('optional sports supplies grant bonuses and are consumed with the toy', function () {
    $pet = Pet::factory()->create(['mood' => 0, 'mood_max' => 100]);
    $toy = statusItem($pet, 'toys');
    $sports = statusItem($pet, 'sports', ['bonuses' => ['mood' => 8], 'remaining_uses' => 1]);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), ['variant' => 'toy', 'items' => ['toys' => $toy->id, 'sports' => $sports->id], 'token' => $token])->assertSessionHasNoErrors();
    $this->assertModelMissing($sports);
    $this->travel(180)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 26]);
});

test('foreign optional items and optional items for another action are rejected without spending', function (bool $foreign) {
    $pet = Pet::factory()->create(['energy' => 50]);
    $toy = statusItem($pet, 'toys');
    $extra = statusItem($foreign ? Pet::factory()->create() : $pet, $foreign ? 'sports' : 'clothing');
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), ['variant' => 'toy', 'items' => ['toys' => $toy->id, $foreign ? 'sports' : 'clothing' => $extra->id], 'token' => (string) Str::uuid()])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50]);
    $this->assertDatabaseCount('item_usages', 0);
})->with([true, false]);

test('a failed completion rolls back buffs as well as state changes', function () {
    $effect = StatusEffect::factory()->create();
    $pet = Pet::factory()->create(['mood' => 0]);
    $toy = statusItem($pet, 'toys', ['effect_codes' => [$effect->code]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    $this->travel(180)->seconds();
    DB::statement("CREATE TRIGGER reject_status_completion BEFORE UPDATE ON pet_care_actions BEGIN SELECT RAISE(ABORT, 'Failure'); END");
    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);
    expect($pet->fresh()->buffs)->toBe([]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 0, 'activity_token' => $care->activity_token]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
});

test('combined modifiers and item bonuses stay within the balance limits', function () {
    $first = StatusEffect::factory()->create(['modifiers' => ['energy_cost_percent' => -40, 'mood_gain_percent' => 40]]);
    $second = StatusEffect::factory()->create(['modifiers' => ['energy_cost_percent' => -40, 'mood_gain_percent' => 40]]);
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 0, 'mood_max' => 100,
        'buffs' => [
            [...$first->snapshot(), 'expires_at' => now()->addHour()->timestamp],
            [...$second->snapshot(), 'expires_at' => now()->addHour()->timestamp],
        ]]);
    $toy = statusItem($pet, 'toys', ['bonuses' => ['mood' => 90]]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 45]);
    $this->travel(180)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 72]);
});

test('disabled and unknown effects cannot be granted by an item', function () {
    $effect = StatusEffect::factory()->create(['is_active' => false]);
    $pet = Pet::factory()->create();
    $toy = statusItem($pet, 'toys', ['effect_codes' => [$effect->code, 'missing']]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    $this->travel(180)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->buffs)->toBe([]);
});

test('the need threshold is inclusive for recovery and dashboard reads do not save statuses', function () {
    $this->seed(StatusEffectSeeder::class);
    $pet = Pet::factory()->create(['satiety' => 20, 'satiety_max' => 100, 'hydration' => 20, 'hydration_max' => 100,
        'cleanliness' => 20, 'cleanliness_max' => 100]);
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('care.debuffs', 0));
    expect($pet->fresh()->debuffs)->toBeNull();
});

test('status projection uses the supplied time and current catalogue without mutating the pet', function () {
    $timed = StatusEffect::factory()->create(['code' => 'comfort']);
    $conditional = StatusEffect::factory()->create([
        'code' => 'hungry', 'kind' => 'debuff', 'condition_state' => 'satiety',
        'condition_threshold' => 20, 'condition_operator' => 'lt', 'duration_seconds' => null,
        'modifiers' => ['energy_cost_percent' => 25],
    ]);
    $pet = Pet::factory()->create([
        'satiety' => 10, 'satiety_max' => 100,
        'buffs' => [[...$timed->snapshot(), 'expires_at' => now()->addMinute()->timestamp]],
    ]);
    $attributes = $pet->refresh()->getAttributes();
    $query = app(GetPetStatuses::class);

    $status = $query->handle($pet, now()->addMinute());

    expect($status->buffs)->toBe([]);
    expect(array_column($status->debuffs, 'code'))->toBe(['hungry']);
    expect($status->modifiers['energy_cost_percent'])->toBe(25);
    expect($pet->getAttributes())->toBe($attributes);
    expect($pet->fresh()->getAttributes())->toBe($attributes);

    $conditional->update(['is_active' => false]);
    expect($query->handle($pet, now()->addMinute())->debuffs)->toBe([]);
});
