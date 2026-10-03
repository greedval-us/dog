<?php

use App\Models\Dog;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Appearance\Actions\PurchasePetAsset;
use App\Modules\Appearance\Actions\SelectPetAsset;
use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\Actions\RecordPetThought;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Database\Seeders\PetHistorySeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeSecond();
});

test('a dog with zero health cannot start a new action and its death persists after rejection', function (string $action) {
    $owner = User::factory()->create(['coins' => 1000]);
    $pet = Pet::factory()->for($owner)->create(['health' => 0, 'satiety' => 100, 'hydration' => 100, 'energy' => 100]);
    $token = (string) Str::uuid();
    $request = match ($action) {
        'care' => fn () => app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], $token),
        'work' => fn () => app(StartDogWork::class)->handle($owner, $pet->id, new StartDogWorkData(DogWorkOffer::factory()->create()->id, $token)),
        'skill' => fn () => app(TrainPetSkill::class)->handle($owner, $pet->id, new TrainPetSkillData(Skill::factory()->create()->id, 1, 100, $token)),
        'veterinarian' => fn () => app(PurchaseVeterinaryService::class)->handle($owner, new PurchaseVeterinaryServiceData($pet->id, VeterinaryService::Checkup, null, 60, $token)),
    };

    expect($request)->toThrow(PetUnavailable::class, 'This dog is no longer active.');

    expect($pet->fresh()->died_at)->toEqual(now());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 0]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 1000, 'experience' => '0']);
    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('dog_work_shifts', 0);
    $this->assertDatabaseCount('pet_skill_lessons', 0);
    $this->assertDatabaseCount('veterinary_visits', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['care', 'work', 'skill', 'veterinarian']);

test('unfinished care is cancelled without restoring states gaining attributes or giving experience when its dog becomes inactive', function (bool $retired) {
    $pet = $retired
        ? Pet::factory()->retired()->create(['health' => 50, 'satiety' => 20, 'speed' => 10])
        : Pet::factory()->create(['health' => 0, 'satiety' => 20, 'speed' => 10]);
    $care = PetCareAction::factory()->create([
        'pet_id' => $pet->id,
        'ends_at' => now()->subSecond(), 'effects' => ['health' => 50, 'satiety' => 50], 'stat_gains' => ['speed' => 10],
    ]);
    $pet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token,
        'activity_started_at' => now()->subMinutes(2), 'activity_ends_at' => $care->ends_at]);

    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();
    expect(app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toBeFalse();

    expect($care->fresh()->cancelled_at)->toEqual(now());
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null, 'experience_awarded' => null]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => $retired ? 50 : 0, 'satiety' => 20, 'speed' => 10,
        'activity' => null, 'activity_token' => null]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0']);
})->with(['death' => [false], 'retirement' => [true]]);

test('unfinished dog work returns its cancelled receipt without rewards even when completion is requested early or repeatedly', function (bool $retired) {
    $owner = User::factory()->create(['coins' => 20, 'gems' => 5]);
    $pet = $retired
        ? Pet::factory()->for($owner)->retired()->create()
        : Pet::factory()->for($owner)->create(['health' => 0]);
    $shift = DogWorkShift::factory()->create(['pet_id' => $pet->id, 'coins_reward' => 150, 'gems_reward' => 10]);
    $pet->update(['activity' => PetActivity::Work, 'activity_token' => $shift->activity_token,
        'activity_started_at' => $shift->started_at, 'activity_ends_at' => $shift->ends_at]);

    $result = app(CompleteDogWork::class)->handle($owner, $shift->token);
    $replayed = app(CompleteDogWork::class)->handle($owner, $shift->token);

    expect($result->id)->toBe($shift->id);
    expect($replayed->cancelled_at)->toEqual(now());
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'completed_at' => null, 'experience_awarded' => null]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity' => null, 'activity_token' => null]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 20, 'gems' => 5, 'experience' => '0']);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['death' => [false], 'retirement' => [true]]);

test('an archived dog cannot select or buy appearance and no account unlock or payment is created', function (bool $purchase) {
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'icon');
    $owner = User::factory()->create(['coins' => 200]);
    $pet = Pet::factory()->for($owner)->create(['health' => 0]);
    $asset = $purchase ? GameAsset::factory()->background()->paid()->create() : GameAsset::factory()->background()->create();
    $request = $purchase
        ? fn () => app(PurchasePetAsset::class)->handle($owner, $pet->id, new PurchaseAssetData($asset->id, 100, AssetCurrency::Coins))
        : fn () => app(SelectPetAsset::class)->handle($owner, $pet->id, $asset->id);

    expect($request)->toThrow(AppearanceUnavailable::class, 'This dog is no longer active.');

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 0, 'background_asset_id' => null]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 200]);
    $this->assertDatabaseCount('asset_unlocks', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['select' => [false], 'purchase' => [true]]);

test('thought generation cannot create new thoughts for a dead dog even when hunger conditions match', function () {
    $this->seed(PetHistorySeeder::class);
    $pet = Pet::factory()->create(['health' => 0, 'satiety' => 0]);

    expect(app(RecordPetThought::class)->handle($pet->user, $pet->id))->toBeNull();

    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('kind', 'thought')->count())->toBe(0);
    $this->assertDatabaseCount('pet_thought_states', 0);
});

test('replaying a completed veterinary receipt after death cannot revive the dog charge it again or repeat experience', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['health' => 50]);
    $data = new PurchaseVeterinaryServiceData($pet->id, VeterinaryService::Checkup, null, 60, (string) Str::uuid());
    $visit = app(PurchaseVeterinaryService::class)->handle($owner, $data);
    $pet->update(['health' => 0]);

    $replayed = app(PurchaseVeterinaryService::class)->handle($owner, $data);

    expect($replayed->id)->toBe($visit->id);
    expect($pet->fresh()->died_at)->toEqual(now());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 0]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 440, 'experience' => '10']);
    $this->assertDatabaseCount('veterinary_visits', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('a six month old offline dog frees its slot before a kennel purchase and replaying that purchase remains safe', function () {
    $owner = User::factory()->create(['coins' => 1000, 'pet_slots' => 1, 'starter_pet_claimed_at' => now()->subMonths(6)]);
    $oldPet = Pet::factory()->for($owner)->create(['born_at' => now()->subMonthsNoOverflow(6), 'health' => 100]);
    $breed = Dog::factory()->create(['is_starter' => true]);
    $data = new PurchaseKennelPetData($breed->id, 'Новый друг', 500, (string) Str::uuid());

    $purchase = app(PurchaseKennelPet::class)->handle($owner, $data);
    $replayed = app(PurchaseKennelPet::class)->handle($owner, $data);

    expect($oldPet->fresh()->retired_at)->toEqual(now());
    expect($replayed->id)->toBe($purchase->id);
    expect($owner->pets()->active()->count())->toBe(1);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
    $this->assertDatabaseCount('kennel_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});
