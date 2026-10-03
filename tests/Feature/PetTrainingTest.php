<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\StatusEffect;
use App\Models\Training;
use App\Models\User;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetCare;
use Database\Seeders\TrainingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
});

function trainingEquipment(User $owner, array $attributes = [], string $category = 'sports'): InventoryItem
{
    return InventoryItem::factory()->for($owner)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['quality' => 10, 'bonuses' => [], 'effect_rules' => [], ...$attributes]);
}

test('training consumes equipment and energy once and applies saved capped gains after the timer', function () {
    $this->freezeSecond();
    $training = Training::factory()->create();
    $pet = Pet::factory()->create(['energy' => 100, 'mood' => 100, 'mood_max' => 100, 'bond' => 100, 'bond_max' => 100,
        'speed' => 10, 'speed_potential' => 100, 'endurance' => 99, 'endurance_potential' => 100,
        'satiety' => 100, 'satiety_max' => 100, 'hydration' => 100, 'hydration_max' => 100]);
    $item = trainingEquipment($pet->user, ['remaining_uses' => 1]);
    $payload = ['variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => (string) Str::uuid()];

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->assertModelMissing($item);
    $this->assertDatabaseCount('item_usages', 1);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 85, 'speed' => 10, 'endurance' => 99, 'activity' => 'training']);
    $this->post(route('pets.care.complete', $pet), ['token' => $payload['token']])->assertSessionHasErrors('care');
    $training->delete();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $care = app(GetPetCare::class)->handle($pet->user, $pet->id, 'ru');
    expect($care['active']['label'])->toBe('Бег');
    $this->travel(120)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $payload['token']])->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $payload['token']])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'speed' => 16, 'endurance' => 100, 'activity' => null,
        'satiety' => 94.8333, 'hydration' => 91.8333]);
    $this->assertDatabaseCount('pet_care_actions', 1);
});

test('training refuses needs just below fifty percent without spending supplies', function (string $state) {
    $this->freezeSecond();
    $training = Training::factory()->create();
    $pet = Pet::factory()->create([$state => 99.98, $state.'_max' => 200, 'energy' => 100]);
    $item = trainingEquipment($pet->user);

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['care' => __('Training requires health, satiety and hydration of at least 50%.')]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 100, 'activity' => null]);
    $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('item_usages', 0);
})->with(['health', 'satiety', 'hydration']);

test('training permits exactly fifty percent and its preview uses equipment mood and bond', function (int $quality, int $mood, int $bond, int $gain) {
    $this->freezeSecond();
    $training = Training::factory()->create(['stat_gains' => ['speed' => 8]]);
    $pet = Pet::factory()->create(['health' => 100, 'health_max' => 200, 'satiety' => 100, 'satiety_max' => 200,
        'hydration' => 100, 'hydration_max' => 200, 'mood' => $mood, 'mood_max' => 100, 'bond' => $bond, 'bond_max' => 100,
        'speed' => 0, 'speed_potential' => 100, 'energy' => 100]);
    $item = trainingEquipment($pet->user, ['quality' => $quality]);
    $care = app(GetPetCare::class)->handle($pet->user, $pet->id, 'en');
    $option = collect($care['options'])->firstWhere('id', 'training:'.$training->id);
    expect($option['reason'])->toBeNull();
    expect($option['gainsByQuality'][$quality])->toBe(['speed' => $gain]);

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    expect(PetCareAction::query()->sole()->stat_gains)->toBe(['speed' => $gain]);
})->with([
    'best equipment and rapport' => [10, 100, 100, 12],
    'basic equipment' => [1, 100, 100, 5],
    'low mood' => [10, 0, 100, 6],
    'low bond' => [10, 100, 0, 6],
]);

test('training requires usable owned sports equipment', function (string $condition) {
    $this->freezeSecond();
    $training = Training::factory()->create();
    $pet = Pet::factory()->create(['energy' => 100]);
    $item = trainingEquipment($condition === 'foreign' ? User::factory()->create() : $pet->user,
        [], $condition === 'wrong category' ? 'toys' : 'sports');
    if ($condition === 'empty') {
        $item->delete();
    }

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => $condition === 'missing' ? [] : ['sports' => $item->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 100, 'activity' => null]);
    $this->assertDatabaseCount('item_usages', 0);
    $this->assertDatabaseCount('pet_care_actions', 0);
})->with(['missing', 'foreign', 'empty', 'wrong category']);

test('all trainings share a cooldown which ends at the exact recorded time', function () {
    $this->freezeSecond();
    $first = Training::factory()->create();
    $second = Training::factory()->create();
    $pet = Pet::factory()->create(['energy' => 100, 'satiety' => 100, 'hydration' => 100]);
    $item = trainingEquipment($pet->user);
    $payload = ['variant' => 'training:'.$first->id, 'items' => ['sports' => $item->id], 'token' => (string) Str::uuid()];
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->travel(120)->seconds();
    $this->post(route('pets.care.complete', $pet), ['token' => $payload['token']])->assertSessionHasNoErrors();
    $next = [...$payload, 'variant' => 'training:'.$second->id, 'token' => (string) Str::uuid()];
    $this->travel(599)->seconds();

    $this->post(route('pets.care.store', $pet), $next)->assertSessionHasErrors(['care' => __('This action is cooling down. Wait before trying again.')]);
    $this->assertDatabaseCount('item_usages', 1);
    $this->travel(1)->seconds();
    $this->post(route('pets.care.store', $pet), $next)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('item_usages', 2);
});

test('training and equipment grant their saved injuries and buffs once', function () {
    $this->freezeSecond();
    $injury = StatusEffect::factory()->create(['kind' => 'debuff', 'modifiers' => ['energy_cost_percent' => 25]]);
    $buff = StatusEffect::factory()->create();
    $equipmentInjury = StatusEffect::factory()->create(['kind' => 'debuff']);
    $training = Training::factory()->for($injury, 'statusEffect')->create(['risk_chance' => 10000]);
    $pet = Pet::factory()->create(['energy' => 100]);
    $rules = collect([$buff, $equipmentInjury])->map(fn (StatusEffect $effect): array => [
        'effect' => $effect->snapshot(), 'chance_percent' => 100, 'chance_by_quality' => [], 'duration_by_quality' => [], 'duration_seconds' => null,
    ])->all();
    $item = trainingEquipment($pet->user, ['effect_rules' => $rules]);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => $token,
    ])->assertSessionHasNoErrors();
    $injury->update(['is_active' => false, 'modifiers' => []]);
    $this->travel(120)->seconds();

    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    expect(array_column($pet->fresh()->buffs, 'code'))->toContain($buff->code);
    expect(array_column($pet->fresh()->debuffs, 'code'))->toBe([$injury->code, $equipmentInjury->code]);
    expect($pet->fresh()->debuffs[0]['modifiers'])->toBe(['energy_cost_percent' => 25]);
});

test('disabled invalid and fully mastered trainings do not consume equipment', function (array $trainingAttributes, array $petAttributes) {
    $this->freezeSecond();
    $training = Training::factory()->create($trainingAttributes);
    $pet = Pet::factory()->create(['energy' => 100, ...$petAttributes]);
    $item = trainingEquipment($pet->user);

    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('care');
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 100, 'activity' => null]);
    $this->assertDatabaseCount('item_usages', 0);
})->with([
    'inactive' => [['is_active' => false], []],
    'three stats' => [['stat_gains' => ['speed' => 1, 'strength' => 1, 'agility' => 1]], []],
    'unknown stat' => [['stat_gains' => ['coins' => 1]], []],
    'full potential' => [[], ['speed' => 100, 'speed_potential' => 100, 'endurance' => 100, 'endurance_potential' => 100]],
]);

test('training seeding preserves edited catalogue values and adds four playable sessions', function () {
    $this->seed(TrainingSeeder::class);
    Training::query()->where('code', 'sprint')->update(['energy_cost' => 30]);
    $this->seed(TrainingSeeder::class);

    $this->assertDatabaseCount('trainings', 4);
    $this->assertDatabaseHas('trainings', ['code' => 'sprint', 'energy_cost' => 30]);
    $pet = Pet::factory()->create();
    $care = app(GetPetCare::class)->handle($pet->user, $pet->id, 'ru');
    expect(collect($care['options'])->where('group', 'training'))->toHaveCount(4);
});

test('failed training completion rolls back attribute gains and can be retried', function () {
    $this->freezeSecond();
    $training = Training::factory()->create(['stat_gains' => ['speed' => 4]]);
    $pet = Pet::factory()->create(['energy' => 100, 'speed' => 0, 'speed_potential' => 100]);
    $item = trainingEquipment($pet->user);
    $token = (string) Str::uuid();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), [
        'variant' => 'training:'.$training->id, 'items' => ['sports' => $item->id], 'token' => $token,
    ])->assertSessionHasNoErrors();
    $this->travel(120)->seconds();
    $this->rejectCareWrites('reject_training_completion', 'UPDATE');

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $token))->toThrow(QueryException::class);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'speed' => 0, 'activity' => 'training']);
    $this->assertDatabaseHas('pet_care_actions', ['token' => $token, 'completed_at' => null]);
    $this->allowCareWrites('reject_training_completion');
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'speed' => PetCareAction::query()->sole()->stat_gains['speed'], 'activity' => null]);
});

test('refused training rolls back expired care completion and its rewards', function () {
    $this->freezeSecond();
    $training = Training::factory()->create();
    $pet = Pet::factory()->create([
        'energy' => 0, 'energy_max' => 100, 'satiety' => 100, 'satiety_max' => 100,
        'hydration' => 100, 'hydration_max' => 100,
        'speed' => 100, 'speed_potential' => 100, 'endurance' => 100, 'endurance_potential' => 100,
    ]);
    $item = trainingEquipment($pet->user);
    $rest = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travel(300)->seconds();

    expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, 'training:'.$training->id,
        ['sports' => $item->id], (string) Str::uuid()))
        ->toThrow(PetUnavailable::class, 'Your dog has reached the potential for this training.');

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 0, 'activity_token' => $rest->activity_token]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $rest->id, 'completed_at' => null, 'experience_awarded' => null]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0']);
    $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'remaining_uses' => 5]);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('item_usages', 0);
});
