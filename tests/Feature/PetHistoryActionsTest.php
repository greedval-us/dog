<?php

use App\Models\DogWorkOffer;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\Skill;
use App\Models\Training;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Enums\VeterinaryService;
use Database\Seeders\PetHistorySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeSecond();
    $this->seed(PetHistorySeeder::class);
});

function historyActionItem(User $owner, string $category, int $uses = 1): InventoryItem
{
    return InventoryItem::factory()->for($owner)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['remaining_uses' => $uses, 'quality' => 10, 'bonuses' => [], 'effect_rules' => []]);
}

test('feeding history records the capped recovery at the finish time once despite late confirmation and retries', function () {
    $pet = Pet::factory()->create(['size' => 'small', 'satiety' => 190, 'satiety_max' => 200]);
    $food = historyActionItem($pet->user, 'food');
    $token = (string) Str::uuid();
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], $token);
    app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], strtoupper($token));
    $this->travelTo($care->ends_at->addHours(2));

    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $token))->toBeTrue();
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $token))->toBeFalse();

    $this->assertDatabaseCount('pet_history_entries', 2);
    $started = PetHistoryEntry::query()->where('pet_id', $pet->id)->where('source_key', 'care:'.$care->id.':started')->sole();
    expect($started->details['items'])->toBe([['category' => 'food', 'name' => $food->name, 'uses' => 1]]);
    $completed = PetHistoryEntry::query()->where('pet_id', $pet->id)->where('source_key', 'care:'.$care->id.':completed')->sole();
    expect($completed->occurred_at->equalTo($care->ends_at))->toBeTrue();
    expect($completed->details['changes'])->toEqual([[
        'metric' => 'satiety', 'before' => 94.9584, 'after' => 100,
        'delta' => 5.0417, 'unit' => 'percent',
    ]]);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('item_usages', 1);
});

test('training history keeps its name and records energy percentages and capped attribute gains', function () {
    $training = Training::factory()->create();
    $pet = Pet::factory()->create(['energy' => 200, 'energy_max' => 200, 'mood' => 100, 'mood_max' => 100,
        'bond' => 100, 'bond_max' => 100, 'speed' => 10, 'speed_potential' => 100,
        'endurance' => 99, 'endurance_potential' => 100]);
    $equipment = historyActionItem($pet->user, 'sports');
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'training:'.$training->id,
        ['sports' => $equipment->id], (string) Str::uuid());
    $training->delete();
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $started = PetHistoryEntry::query()->where('source_key', 'care:'.$care->id.':started')->sole();
    $completed = PetHistoryEntry::query()->where('source_key', 'care:'.$care->id.':completed')->sole();
    expect($started->details['changes'])->toEqual([[
        'metric' => 'energy', 'before' => 100, 'after' => 92.5, 'delta' => -7.5, 'unit' => 'percent',
    ]]);
    expect($completed->details['name'])->toBe(['ru' => 'Бег', 'en' => 'Running']);
    expect(collect($completed->details['changes'])->firstWhere('metric', 'endurance'))->toEqual([
        'metric' => 'endurance', 'before' => 99, 'after' => 100, 'delta' => 1, 'unit' => 'points',
    ]);
    expect(collect($completed->details['changes'])->firstWhere('metric', 'speed'))->toEqual([
        'metric' => 'speed', 'before' => 10, 'after' => 16, 'delta' => 6, 'unit' => 'points',
    ]);
});

test('work history records the saved reward once after the offer changes', function () {
    $offer = DogWorkOffer::factory()->create(['gems_reward' => 2]);
    $pet = Pet::factory()->create(['energy' => 100, 'energy_max' => 100, 'intelligence' => 100, 'obedience' => 100]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);
    $data = new StartDogWorkData($offer->id, (string) Str::uuid());
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, $data);
    app(StartDogWork::class)->handle($pet->user, $pet->id, $data);
    $offer->update(['name' => ['ru' => 'Изменено', 'en' => 'Changed'], 'coins_reward' => 999]);
    $this->travelTo($shift->ends_at);

    app(CompleteDogWork::class)->handle($pet->user, $shift->token);
    app(CompleteDogWork::class)->handle($pet->user, $shift->token);

    $this->assertDatabaseCount('pet_history_entries', 2);
    $entry = PetHistoryEntry::query()->where('source_key', 'work:'.$shift->id.':completed')->sole();
    expect($entry->details['name'])->toBe(['ru' => 'Поиск в парке', 'en' => 'Park search']);
    expect($entry->details['coins'])->toBe(150);
    expect($entry->details['gems'])->toBe(2);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150, 'gems' => 2]);
});

test('skill lesson history keeps the learned level name and payment without repeating the entry', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create(['name' => ['ru' => 'Поиск', 'en' => 'Search']]);
    $data = new TrainPetSkillData($skill->id, 1, 100, (string) Str::uuid());

    $lesson = app(TrainPetSkill::class)->handle($owner, $pet->id, $data);
    $skill->update(['name' => ['ru' => 'Изменено', 'en' => 'Changed']]);
    app(TrainPetSkill::class)->handle($owner, $pet->id, $data);

    $this->assertDatabaseCount('pet_history_entries', 1);
    $entry = PetHistoryEntry::query()->where('source_key', 'skill:'.$lesson->id.':completed')->sole();
    expect($entry->details['name'])->toBe(['ru' => 'Поиск', 'en' => 'Search']);
    expect($entry->details['level'])->toBe(1);
    expect($entry->details['coins'])->toBe(-100);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 1]);
});

test('veterinary history records the actual capped health recovery and its charge once', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['health' => 190, 'health_max' => 200]);
    $data = new PurchaseVeterinaryServiceData($pet->id, VeterinaryService::Checkup, null, 60, (string) Str::uuid());

    $visit = app(PurchaseVeterinaryService::class)->handle($owner, $data);
    app(PurchaseVeterinaryService::class)->handle($owner, $data);

    $this->assertDatabaseCount('pet_history_entries', 1);
    $entry = PetHistoryEntry::query()->where('source_key', 'veterinary:'.$visit->id.':completed')->sole();
    expect($entry->details['changes'])->toEqual([[
        'metric' => 'health', 'before' => 95, 'after' => 100, 'delta' => 5, 'unit' => 'percent',
    ]]);
    expect($entry->details['coins'])->toBe(-60);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 200]);
});

test('a history write failure rolls back care energy supplies and its receipt', function () {
    $pet = Pet::factory()->create(['energy' => 50, 'energy_max' => 100]);
    $toy = historyActionItem($pet->user, 'toys');
    PetHistoryEntry::creating(fn () => throw new RuntimeException('Cannot save history.'));

    try {
        expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, 'toy', ['toys' => $toy->id], (string) Str::uuid()))
            ->toThrow(RuntimeException::class, 'Cannot save history.');
    } finally {
        PetHistoryEntry::flushEventListeners();
    }

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 50, 'activity' => null]);
    $this->assertDatabaseHas('inventory_items', ['id' => $toy->id, 'remaining_uses' => 1]);
    $this->assertDatabaseCount('pet_history_entries', 0);
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('item_usages', 0);
});

test('a failed completion history write keeps care finishable without applying its result', function () {
    $pet = Pet::factory()->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);
    PetHistoryEntry::creating(fn () => throw new RuntimeException('Cannot save completion history.'));

    try {
        expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))
            ->toThrow(RuntimeException::class, 'Cannot save completion history.');
    } finally {
        PetHistoryEntry::flushEventListeners();
    }

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 50, 'activity_token' => $care->activity_token]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
    $this->assertDatabaseCount('pet_history_entries', 1);
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeTrue();
    $this->assertDatabaseCount('pet_history_entries', 2);
});
