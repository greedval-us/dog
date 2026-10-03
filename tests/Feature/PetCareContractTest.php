<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\Training;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetCare;
use Illuminate\Support\Str;

test('care preview and refused launch share stable refusal codes without spending items', function (string $variant, array $attributes, CareRefusal $reason, string $code) {
    $this->freezeSecond();
    $pet = Pet::factory()->create($attributes);
    $items = [];
    if ($variant === 'training') {
        $training = Training::factory()->create(['stat_gains' => ['speed' => 4]]);
        $variant = 'training:'.$training->id;
        $equipment = InventoryItem::factory()->for($pet->user)->for(
            Item::factory()->for(ItemCategory::factory()->state(['code' => 'sports']), 'category')
        )->create(['remaining_uses' => 1]);
        $items = ['sports' => $equipment->id];
    }
    $preview = app(GetPetCare::class)->handle($pet->user, $pet->id, 'en');
    expect(collect($preview['options'])->firstWhere('id', $variant)['reasonCode'])->toBe($code);

    expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, $variant, $items, (string) Str::uuid()))
        ->toThrow(function (PetUnavailable $exception) use ($reason) {
            expect($exception->reason)->toBe($reason);
        });

    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('item_usages', 0);
    foreach ($items as $id) {
        $this->assertDatabaseHas('inventory_items', ['id' => $id, 'remaining_uses' => 1]);
    }
})->with([
    'energy units' => ['attention', ['energy' => 0], CareRefusal::Energy, 'energy'],
    'minimum active needs' => ['home', ['energy' => 100, 'satiety' => 9, 'satiety_max' => 100], CareRefusal::ActiveNeeds, 'active_needs'],
    'full need' => ['water', ['hydration' => 100, 'hydration_max' => 100], CareRefusal::NeedFull, 'need_full'],
    'training needs' => ['training', ['health' => 49, 'health_max' => 100], CareRefusal::TrainingNeeds, 'training_needs'],
    'training potential' => ['training', ['speed' => 100, 'speed_potential' => 100], CareRefusal::TrainingPotential, 'training_potential'],
]);

test('care completion history records capped state and stat changes once with explicit units', function () {
    $this->freezeSecond();
    config(['pet_states.decay_per_hour.hydration' => 0, 'pet_stats.decay_per_hour.speed' => 0]);
    PetHistoryEvent::factory()->create(['code' => 'training']);
    $activityToken = (string) Str::uuid();
    $pet = Pet::factory()->create([
        'hydration' => 195, 'hydration_max' => 200, 'speed' => 9, 'speed_potential' => 10,
        'activity' => PetActivity::Training, 'activity_token' => $activityToken,
        'activity_started_at' => now(), 'activity_ends_at' => now()->addSeconds(15),
    ]);
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id,
        'group' => 'training', 'variant' => 'training:1', 'activity_token' => $activityToken,
        'effects' => ['hydration' => 35], 'stat_gains' => ['speed' => 5],
        'ends_at' => now()->addSeconds(15),
    ]);
    $this->travel(15)->seconds();

    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeTrue();
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();

    expect(PetHistoryEntry::query()->sole()->details['changes'])->toEqual([
        ['metric' => 'hydration', 'before' => 97.5, 'after' => 100.0, 'delta' => 2.5, 'unit' => 'percent'],
        ['metric' => 'speed', 'before' => 9.0, 'after' => 10.0, 'delta' => 1.0, 'unit' => 'points'],
    ]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 200, 'speed' => 10]);
    $this->actingAs($pet->user)->getJson(route('pets.history.index', $pet))
        ->assertOk()->assertJsonPath('data.0.details.changes.0.unit', 'percent')
        ->assertJsonPath('data.0.details.changes.1.unit', 'points');
});
