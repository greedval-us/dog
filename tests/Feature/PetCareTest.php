<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function careItem(User $owner, string $category, int $uses = 5, int $quality = 3): InventoryItem
{
    return InventoryItem::factory()->for($owner)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['remaining_uses' => $uses, 'quality' => $quality]);
}

test('feeding consumes a portion once and restores satiety according to size and individual capacity', function (string $size, int $maximum, float $initial, float $expected) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['size' => $size, 'satiety_max' => $maximum, 'satiety' => $initial]);
    $food = careItem($pet->user, 'food', 1);
    $token = (string) Str::uuid();
    $payload = ['variant' => 'meal', 'items' => ['food' => $food->id], 'token' => $token];
    $url = route('pets.care.store', $pet);

    $this->actingAs($pet->user)->post($url, $payload)->assertRedirect(route('dashboard', ['pet' => $pet->id]));
    $this->post($url, [...$payload, 'token' => strtoupper($token)])->assertSessionHasNoErrors();
    $this->assertModelMissing($food);
    $this->assertDatabaseCount('item_usages', 1);
    $this->assertDatabaseCount('pet_care_actions', 1);
    expect($pet->fresh()->satiety)->toBe($initial);

    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasErrors('care');
    $this->travel(30)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();

    expect($pet->fresh())->satiety->toBe($expected)->activity->toBeNull();
    $this->assertDatabaseHas('pet_care_actions', ['token' => $token, 'completed_at' => now()->toDateTimeString()]);
})->with([
    'small' => ['small', 200, 50.0, 109.9167],
    'medium' => ['medium', 200, 50.0, 93.9167],
    'large' => ['large', 200, 50.0, 79.9167],
    'cap at maximum' => ['small', 200, 190.0, 200.0],
]);

test('care variants apply their costs and effects after the saved duration', function (string $variant, array $categories, int $seconds, int $cost, array $expected) {
    $this->freezeSecond();
    $pet = Pet::factory()->create([
        'energy' => 50, 'energy_max' => 100, 'satiety' => 50, 'satiety_max' => 100,
        'hydration' => 50, 'hydration_max' => 100, 'mood' => 50, 'mood_max' => 100,
        'cleanliness' => 50, 'cleanliness_max' => 100, 'bond' => 50, 'bond_max' => 100,
    ]);
    $items = [];

    foreach ($categories as $category) {
        $items[$category] = careItem($pet->user, $category)->id;
    }

    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, $variant, $items, (string) Str::uuid());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50 - $cost]);
    $this->assertDatabaseCount('item_usages', count($categories));
    $this->travel($seconds)->seconds();

    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => null, ...$expected]);

    foreach ($items as $id) {
        $this->assertDatabaseHas('inventory_items', ['id' => $id, 'remaining_uses' => 4]);
    }
})->with([
    'water' => ['water', [], 15, 0, ['hydration' => 84.9792]],
    'walk' => ['walk', ['collars', 'leashes'], 300, 12, ['energy' => 38, 'mood' => 74.8333, 'bond' => 53.9583, 'satiety' => 41.5833, 'hydration' => 39.5833, 'cleanliness' => 37.8333]],
    'home alternative' => ['home', [], 120, 4, ['energy' => 46, 'mood' => 57.9333, 'bond' => 50.9833]],
    'play without toy' => ['attention', [], 120, 6, ['energy' => 44, 'mood' => 59.9333, 'bond' => 51.9833]],
    'toy' => ['toy', ['toys'], 180, 10, ['energy' => 40, 'mood' => 69.9, 'bond' => 53.975]],
    'basic wash' => ['wash', [], 60, 0, ['cleanliness' => 59.9667, 'bond' => 50.9917]],
    'care product' => ['care', ['care'], 180, 0, ['cleanliness' => 76.9, 'bond' => 52.975]],
    'nap' => ['nap', [], 300, 0, ['energy' => 75, 'satiety' => 45.5833, 'hydration' => 45.5833]],
    'long sleep capped' => ['sleep', [], 1200, 0, ['energy' => 100, 'satiety' => 36.3333, 'hydration' => 36.3333]],
]);

test('cooldowns survive completion and cannot be bypassed by changing variant or token', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 40, 'mood' => 0]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->travel(120)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $toy = careItem($pet->user, 'toys');

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'toy', 'items' => ['toys' => $toy->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    expect($toy->fresh()->remaining_uses)->toBe(5);
    $this->assertDatabaseCount('pet_care_actions', 1);

    $this->travel(599)->seconds();
    expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid()))->toThrow(PetUnavailable::class);
    $this->travel(1)->seconds();
    app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->assertDatabaseCount('pet_care_actions', 2);
});

test('a different action and a different owned dog have independent cooldowns', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 40]);
    $other = Pet::factory()->for($pet->user)->create(['energy' => 40]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    app(StartPetCare::class)->handle($pet->user, $other->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    app(StartPetCare::class)->handle($pet->user, $pet->id, 'home', [], (string) Str::uuid());
    $this->assertDatabaseCount('pet_care_actions', 3);
});

test('unavailable items never consume other equipment or start a walk', function (string $invalid) {
    $pet = Pet::factory()->create(['energy' => 50]);
    $collar = careItem($pet->user, 'collars', 1);
    $leash = careItem($invalid === 'foreign' ? User::factory()->create() : $pet->user, $invalid === 'wrong category' ? 'food' : 'leashes');
    $items = ['collars' => $collar->id, 'leashes' => $leash->id];

    if ($invalid === 'missing') {
        unset($items['leashes']);
    }
    if ($invalid === 'deleted') {
        $leash->delete();
    }

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'walk', 'items' => $items, 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');

    $this->assertDatabaseHas('inventory_items', ['id' => $collar->id, 'remaining_uses' => 1]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50, 'activity' => null]);
    $this->assertDatabaseCount('item_usages', 0);
    $this->assertDatabaseCount('pet_care_actions', 0);
})->with(['missing', 'foreign', 'wrong category', 'deleted']);

test('both pieces of walking equipment disappear on their last use', function () {
    $pet = Pet::factory()->create();
    $collar = careItem($pet->user, 'collars', 1);
    $leash = careItem($pet->user, 'leashes', 1);
    app(StartPetCare::class)->handle($pet->user, $pet->id, 'walk', ['collars' => $collar->id, 'leashes' => $leash->id], (string) Str::uuid());

    $this->assertModelMissing($collar);
    $this->assertModelMissing($leash);
    $this->assertDatabaseCount('item_usages', 2);
});

test('inventory and activity changes roll back when saving the care receipt fails', function () {
    $pet = Pet::factory()->create(['energy' => 50]);
    $toy = careItem($pet->user, 'toys', 1);
    $this->rejectCareWrites('reject_care', 'INSERT');

    expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid()))
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('inventory_items', ['id' => $toy->id, 'remaining_uses' => 1]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50, 'activity' => null]);
    $this->assertDatabaseCount('item_usages', 0);
});

test('pet state and account restrictions reject care without writes', function (array $attributes, string $variant, bool $blocked) {
    $pet = Pet::factory()->create($attributes);

    if ($blocked) {
        $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save();
    }

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => $variant, 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, ...$attributes]);
})->with([
    'no energy' => [['energy' => 3], 'home', false],
    'hungry' => [['satiety' => 0], 'home', false],
    'thirsty' => [['hydration' => 0], 'attention', false],
    'full energy' => [['energy' => 100, 'energy_max' => 100], 'nap', false],
    'clean dog' => [['cleanliness' => 100, 'cleanliness_max' => 100], 'wash', false],
    'full water' => [['hydration' => 100, 'hydration_max' => 100], 'water', false],
    'busy' => [['activity' => 'training'], 'home', false],
    'retired' => [['retired_at' => '2026-09-01 00:00:00'], 'home', false],
    'blocked account' => [[], 'home', true],
]);

test('guests cannot start or finish care', function (string $route) {
    $pet = Pet::factory()->create();
    $this->post(route($route, $pet))->assertRedirect(route('login'));
    $this->assertDatabaseCount('pet_care_actions', 0);
})->with(['pets.care.store', 'pets.care.complete']);

test('players cannot start or finish another players activity', function () {
    $pet = Pet::factory()->create(['energy' => 40]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->actingAs(User::factory()->create())->post(route('pets.care.store', $pet), [
        'variant' => 'home', 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertNotFound();
    $this->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertNotFound();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 40, 'activity' => 'sleep']);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
});

test('tokens are bound to the original pet variant and items', function (string $change) {
    $pet = Pet::factory()->create(['energy' => 40]);
    $other = Pet::factory()->for($pet->user)->create(['energy' => 40]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());

    $this->actingAs($pet->user)->post(route('pets.care.store', $change === 'pet' ? $other : $pet), [
        'variant' => $change === 'variant' ? 'sleep' : 'nap',
        'items' => $change === 'items' ? ['food' => 1] : [], 'token' => $care->token,
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseCount('pet_care_actions', 1);
})->with(['pet', 'variant', 'items']);

test('invalid care input is rejected before starting', function (array $payload, array $errors) {
    $pet = Pet::factory()->create();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasErrors($errors);
    $this->assertDatabaseCount('pet_care_actions', 0);
})->with([
    'required' => [[], ['variant', 'token', 'items']],
    'unknown variant' => [['variant' => 'fly', 'items' => [], 'token' => '07bed92b-cc81-4c34-a7ba-b5aeab2840fd'], ['variant']],
    'invalid token and item' => [['variant' => 'meal', 'items' => ['food' => -1], 'token' => 'bad'], ['token', 'items.food']],
    'unknown item key' => [['variant' => 'meal', 'items' => ['hacked' => 1], 'token' => '07bed92b-cc81-4c34-a7ba-b5aeab2840fd'], ['items']],
]);

test('care uses the inventory quality snapshot and saves effects before catalogue changes', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['mood' => 0, 'mood_max' => 100]);
    $toy = careItem($pet->user, 'toys', 2, 8);
    $toy->item->update(['quality' => 1, 'is_active' => false]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid());
    $toy->update(['quality' => 1]);
    $this->travel(180)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 25]);
});

test('dashboard displays owned supplies active action and persistent cooldown without mutating state', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 40]);
    $toy = careItem($pet->user, 'toys');
    InventoryItem::factory()->create();
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(301)->seconds();
    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->has('care.options', 10)->missing('care.items')
        ->where('care.active.token', $care->token)
        ->where('care.cooldowns.sleep', $care->available_at->toIso8601String())
        ->where('care.busy', true)
    ));
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 40, 'activity' => PetActivity::Sleep->value]);
});

test('care cooldowns use the latest future expiry per group for only the selected pet', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    PetCareAction::factory()->count(4)->sequence(
        ['group' => 'play', 'available_at' => now()->addMinutes(15)],
        ['group' => 'play', 'available_at' => now()->addMinutes(5)],
        ['group' => 'sleep', 'available_at' => now()->addMinutes(30)],
        ['group' => 'feed', 'available_at' => now()],
    )->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id, 'completed_at' => now()]);
    $other = Pet::factory()->for($pet->user)->create();
    PetCareAction::factory()->create([
        'pet_id' => $other->id, 'user_id' => $pet->user_id, 'group' => 'play',
        'available_at' => now()->addHour(),
    ]);

    $care = app(GetPetCare::class)->handle($pet->user, $pet->id, 'en');

    expect($care['cooldowns']->all())->toEqual([
        'play' => now()->addMinutes(15)->toIso8601String(),
        'sleep' => now()->addMinutes(30)->toIso8601String(),
    ]);
});

test('sleep floors hunger and thirst and cannot be finished by a newly blocked owner', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 10, 'satiety' => 1, 'hydration' => 1]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'sleep', [], (string) Str::uuid());
    $this->travel(1200)->seconds();
    $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save();
    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $care->token])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 10]);
    $pet->user->forceFill(['status' => PlayerStatus::Active])->save();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'satiety' => 0, 'hydration' => 0]);
});

test('a full dog does not spend food', function () {
    $pet = Pet::factory()->create(['satiety' => 100, 'satiety_max' => 100]);
    $food = careItem($pet->user, 'food', 1);
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'meal', 'items' => ['food' => $food->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['care' => __('This need is already full. Choose another action.')]);
    $this->assertModelExists($food);
    $this->assertDatabaseCount('item_usages', 0);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('failure while saving completion rolls back the effect and keeps the activity finishable', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 40]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();
    $this->rejectCareWrites('reject_care_completion', 'UPDATE');
    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 40, 'activity_token' => $care->activity_token]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
    $this->allowCareWrites('reject_care_completion');
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 65.4167, 'activity' => null]);
});

test('replaying an old completion does not interrupt a newer activity', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 20]);
    $first = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $first->token);
    $second = app(StartPetCare::class)->handle($pet->user, $pet->id, 'home', [], (string) Str::uuid());
    $this->actingAs($pet->user)->post(route('pets.care.complete', $pet), ['token' => $first->token])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 41.4167, 'activity_token' => $second->activity_token]);
});

test('configured care balance controls the preview costs timing and saved result', function () {
    $this->freezeSecond();
    config([
        'pet_care.options.toy.duration' => 45,
        'pet_care.options.toy.cooldown' => 90,
        'pet_care.options.toy.energy' => 7,
        'pet_care.options.toy.items.toys' => 2,
        'pet_care.options.toy.effects.mood' => 12,
        'pet_care.quality_bonuses.toys.per_level' => 3,
        'pet_care.quality_bonuses.toys.max_quality' => 4,
    ]);
    $pet = Pet::factory()->create(['energy' => 50, 'mood' => 0, 'mood_max' => 100]);
    $toy = careItem($pet->user, 'toys', 3, 8);
    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('care.options.5.duration', 45)->where('care.options.5.cooldown', 90)
        ->where('care.options.5.energy', 7)->where('care.options.5.uses.toys', 2)
        ->where('care.options.5.effects.mood', 12)
    ));
    $this->getJson(route('care-items', ['category' => 'toys', 'uses' => 2]))->assertJsonPath('items.0.bonus.mood', 9);
    $token = (string) Str::uuid();
    $payload = ['variant' => 'toy', 'items' => ['toys' => $toy->id], 'token' => $token];
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 43, 'mood' => 0]);
    $this->assertDatabaseHas('inventory_items', ['id' => $toy->id, 'remaining_uses' => 1]);
    $this->assertDatabaseHas('item_usages', ['inventory_item_id' => $toy->id, 'uses_spent' => 2]);
    $this->assertDatabaseCount('item_usages', 1);
    $this->assertDatabaseHas('pet_care_actions', [
        'token' => $token, 'ends_at' => now()->addSeconds(45)->toDateTimeString(),
        'available_at' => now()->addSeconds(135)->toDateTimeString(),
    ]);

    config(['pet_care.options.toy.effects.mood' => 90, 'pet_care.options.toy.duration' => 600]);
    $this->travel(45)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 21, 'activity' => null]);
});

test('configured item costs reject an insufficient stack without spending anything', function () {
    config(['pet_care.options.walk.items.leashes' => 3]);
    $pet = Pet::factory()->create(['energy' => 50]);
    $collar = careItem($pet->user, 'collars');
    $leash = careItem($pet->user, 'leashes', 2);
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'walk', 'items' => ['collars' => $collar->id, 'leashes' => $leash->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50, 'activity' => null]);
    $this->assertDatabaseHas('inventory_items', ['id' => $collar->id, 'remaining_uses' => 5]);
    $this->assertDatabaseHas('inventory_items', ['id' => $leash->id, 'remaining_uses' => 2]);
    $this->assertDatabaseCount('item_usages', 0);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('configured feeding percentages are used for each dog size', function (string $size) {
    $this->freezeSecond();
    config(['pet_care.feeding_by_size.'.$size => 17]);
    $pet = Pet::factory()->create(['size' => $size, 'satiety' => 10, 'satiety_max' => 200]);
    $food = careItem($pet->user, 'food');
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());
    $this->travel(30)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'satiety' => 43.9167]);
})->with(['small', 'medium', 'large']);

test('configured minimum needs agree in the preview and action validation', function () {
    config(['pet_care.minimum_needs.walk.satiety' => 40]);
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100]);
    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('care.options.3.reason', 'Feed your dog and offer water before active play or a walk.')
    ));
    $this->post(route('pets.care.store', $pet), [
        'variant' => 'home', 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('starting another action applies expired care once and uses the restored energy', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 0, 'energy_max' => 100, 'satiety' => 50, 'satiety_max' => 100]);
    $first = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();
    $token = (string) Str::uuid();
    $payload = ['variant' => 'attention', 'items' => [], 'token' => $token];

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $first->token])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pet_care_actions', ['id' => $first->id, 'completed_at' => now()->toDateTimeString()]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 19, 'satiety' => 45.5833, 'activity' => 'play']);
    $this->assertDatabaseHas('pet_care_actions', ['token' => $token, 'completed_at' => null]);
    $this->assertDatabaseCount('pet_care_actions', 2);
});

test('automatic completion keeps the original cooldown and permits reuse exactly when it expires', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 5, 'energy_max' => 100]);
    $first = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(2099)->seconds();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'sleep', 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['care' => __('This action is cooling down. Wait before trying again.')]);
    $this->assertDatabaseCount('pet_care_actions', 1);

    $this->travel(1)->seconds();
    $this->post(route('pets.care.store', $pet), [
        'variant' => 'sleep', 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 32.9167, 'activity' => 'sleep']);
    $this->assertDatabaseHas('pet_care_actions', [
        'id' => $first->id, 'completed_at' => now()->toDateTimeString(),
        'available_at' => $first->available_at->toDateTimeString(),
    ]);
    $this->assertDatabaseCount('pet_care_actions', 2);
});

test('automatic care completion does not release an unrelated expired activity', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 5, 'energy_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(301)->seconds();
    $activityToken = (string) Str::uuid();
    $pet->forceFill(['activity' => 'training', 'activity_token' => $activityToken])->save();

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'home', 'items' => [], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 5, 'activity' => 'training', 'activity_token' => $activityToken]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
    $this->assertDatabaseCount('pet_care_actions', 1);
});

test('sleep variants restore different percentages and enforce their own shared cooldown', function (string $variant, int $duration, int $cooldown, float $restored, float $percentage) {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['energy' => 20, 'energy_max' => 200]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, $variant, [], (string) Str::uuid());
    expect($care->ends_at->diffInSeconds($care->available_at))->toBe((float) $cooldown);
    $this->travel($duration)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => $restored, 'activity' => null]);
    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->where('pet.states.energy', $percentage)
        ->where('care.options.8.effects.energy', 25)->where('care.options.8.cooldown', 1800)
        ->where('care.options.9.effects.energy', 70)->where('care.options.9.cooldown', 3600)
    ));
    $this->travel($cooldown - 1)->seconds();
    $payload = ['variant' => 'nap', 'items' => [], 'token' => (string) Str::uuid()];
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasErrors('care');
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->travel(1)->seconds();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('pet_care_actions', 2);
})->with([
    'short sleep' => ['nap', 300, 1800, 70.8333, 35.4],
    'long sleep' => ['sleep', 1200, 3600, 163.3333, 81.7],
]);
