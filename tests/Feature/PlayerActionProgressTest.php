<?php

use App\Models\DogWorkOffer;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
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
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Services\PlayerProgress;
use Database\Seeders\PetHistorySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeSecond();
});

function progressActionItem(User $owner, string $category): InventoryItem
{
    return InventoryItem::factory()->for($owner)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => $category]), 'category')
    )->create(['remaining_uses' => 5, 'quality' => 10, 'bonuses' => [], 'effect_rules' => []]);
}

test('every care variant gives experience only for its completed result and retries cannot repeat it', function (string $variant, array $categories) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'energy' => 75, 'energy_max' => 100, 'health' => 75, 'health_max' => 100,
        'satiety' => 75, 'satiety_max' => 100, 'hydration' => 75, 'hydration_max' => 100,
        'mood' => 75, 'mood_max' => 100, 'cleanliness' => 75, 'cleanliness_max' => 100,
    ]);
    $items = [];
    foreach ($categories as $category) {
        $items[$category] = progressActionItem($owner, $category)->id;
    }
    $token = (string) Str::uuid();
    $care = app(StartPetCare::class)->handle($owner, $pet->id, $variant, $items, $token);
    app(StartPetCare::class)->handle($owner, $pet->id, $variant, $items, strtoupper($token));
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '0', 'level' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => null]);
    expect(fn () => app(CompletePetCare::class)->handle($owner, $pet->id, $token))
        ->toThrow(PetUnavailable::class);
    $this->travelTo($care->ends_at);

    expect(app(CompletePetCare::class)->handle($owner, $pet->id, $token))->toBeTrue();
    expect(app(CompletePetCare::class)->handle($owner, $pet->id, strtoupper($token)))->toBeFalse();

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '10', 'level' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => 10]);
    expect($owner->fresh()->pet_statistics)->toBe(['care.'.$variant => 1]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'active_days' => 1,
        'walks_count' => in_array($variant, ['walk', 'home'], true) ? 1 : 0,
        'last_pet_action_at' => now()->toDateTimeString()]);
})->with([
    'feeding' => ['meal', ['food']],
    'water' => ['water', []],
    'walk outside' => ['walk', ['collars', 'leashes']],
    'walk at home' => ['home', []],
    'play together' => ['attention', []],
    'play with toy' => ['toy', ['toys']],
    'wash paws' => ['wash', []],
    'care product' => ['care', ['care']],
    'nap' => ['nap', []],
    'long sleep' => ['sleep', []],
]);

test('a completed training earns experience even after the catalogue session is deleted', function () {
    $training = Training::factory()->create();
    $pet = Pet::factory()->create([
        'energy' => 100, 'energy_max' => 100, 'satiety' => 100, 'satiety_max' => 100,
        'hydration' => 100, 'hydration_max' => 100, 'health' => 100, 'health_max' => 100,
        'speed' => 10, 'speed_potential' => 100, 'endurance' => 10, 'endurance_potential' => 100,
    ]);
    $equipment = progressActionItem($pet->user, 'sports');
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'training:'.$training->id,
        ['sports' => $equipment->id], (string) Str::uuid());
    $training->delete();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'trainings_count' => 0]);
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10', 'trainings_count' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => 10]);
    expect($pet->user->fresh()->pet_statistics)->toBe(['training' => 1]);
});

test('work gives player experience together with the saved reward only on the first completion', function () {
    $offer = DogWorkOffer::factory()->create();
    $pet = Pet::factory()->create(['energy' => 100, 'energy_max' => 100, 'intelligence' => 100, 'obedience' => 100]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0']);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'experience_awarded' => null]);
    $this->travelTo($shift->ends_at);

    app(CompleteDogWork::class)->handle($pet->user, $shift->token);
    app(CompleteDogWork::class)->handle($pet->user, strtoupper($shift->token));

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10', 'coins' => 150]);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'experience_awarded' => 10]);
    expect($pet->user->fresh()->pet_statistics)->toBe(['work' => 1]);
});

test('an instructor lesson gives experience once and replaying its receipt after deleting the dog gives none', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $data = new TrainPetSkillData($skill->id, 1, 100, (string) Str::uuid());
    $petId = $pet->id;

    $lesson = app(TrainPetSkill::class)->handle($owner, $petId, $data);
    $pet->delete();
    app(TrainPetSkill::class)->handle($owner, $petId, $data);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '10', 'coins' => 400, 'trainings_count' => 1]);
    $this->assertDatabaseHas('pet_skill_lessons', ['id' => $lesson->id, 'experience_awarded' => 10]);
    expect($owner->fresh()->pet_statistics)->toBe(['skill_training' => 1]);
});

test('a veterinary visit gives experience once together with its actual effect', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['health' => 50, 'health_max' => 100]);
    $data = new PurchaseVeterinaryServiceData($pet->id, VeterinaryService::Checkup, null, 60, (string) Str::uuid());

    $visit = app(PurchaseVeterinaryService::class)->handle($owner, $data);
    app(PurchaseVeterinaryService::class)->handle($owner, $data);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '10', 'coins' => 440]);
    $this->assertDatabaseHas('veterinary_visits', ['id' => $visit->id, 'experience_awarded' => 10]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 60]);
    expect($owner->fresh()->pet_statistics)->toBe(['veterinary.checkup' => 1]);
});

test('each successive level requires twice the previous experience and keeps the lifetime total', function (string $before, int $level, string $after, int $nextLevel) {
    $owner = User::factory()->create(['experience' => $before, 'level' => $level]);
    $pet = Pet::factory()->for($owner)->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($owner, $pet->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => $after, 'level' => $nextLevel]);
})->with([
    '100 experience to level two' => ['90', 1, '100', 2],
    'another 200 experience to level three' => ['290', 2, '300', 3],
    'another 400 experience to level four' => ['690', 3, '700', 4],
    'another 800 experience to level five' => ['1490', 4, '1500', 5],
    'level one hundred and one with experience beyond integer limits' => ['126765060022822940149670320537495', 100, '126765060022822940149670320537505', 101],
]);

test('a configurable action reward can cross several levels without losing remaining experience', function () {
    config(['player-progress.rewards' => ['care.water' => 750]]);
    $owner = User::factory()->create(['experience' => '95', 'level' => 1]);
    $pet = Pet::factory()->for($owner)->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($owner, $pet->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '845', 'level' => 4]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => 750]);
});

test('a disabled history catalogue cannot disable experience from completing care', function () {
    $this->seed(PetHistorySeeder::class);
    PetHistoryEvent::query()->update(['is_active' => false]);
    $pet = Pet::factory()->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $this->assertDatabaseCount('pet_history_entries', 0);
});

test('confirmation after the history retention period still gives experience once', function () {
    config(['pet_states.health_loss_per_hour' => 0]);
    $this->seed(PetHistorySeeder::class);
    $pet = Pet::factory()->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at->addDays(31));
    PetHistoryEntry::query()->delete();

    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => 10]);
    $this->assertDatabaseCount('pet_history_entries', 0);
});

test('a history failure rolls back experience and its receipt flag until completion succeeds', function () {
    $this->seed(PetHistorySeeder::class);
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

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'level' => 1,
        'active_days' => 0, 'last_pet_action_at' => null]);
    expect($pet->user->fresh()->pet_statistics)->toBe([]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null, 'experience_awarded' => null]);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10']);
    $completed = PetHistoryEntry::query()->where('source_key', 'care:'.$care->id.':completed')->sole();
    expect($completed->details['experienceAwarded'])->toBe(10);
});

test('starting another activity automatically gives the expired care experience once', function () {
    $owner = User::factory()->create(['experience' => '90']);
    $pet = Pet::factory()->for($owner)->create(['energy' => 0, 'energy_max' => 100, 'satiety' => 50, 'satiety_max' => 100]);
    $nap = app(StartPetCare::class)->handle($owner, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travelTo($nap->ends_at);
    $token = (string) Str::uuid();

    $next = app(StartPetCare::class)->handle($owner, $pet->id, 'attention', [], $token);
    app(StartPetCare::class)->handle($owner, $pet->id, 'attention', [], $token);
    app(CompletePetCare::class)->handle($owner, $pet->id, $nap->token);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '100', 'level' => 2]);
    expect($owner->fresh()->pet_statistics)->toBe(['care.nap' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $nap->id, 'experience_awarded' => 10]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $next->id, 'completed_at' => null, 'experience_awarded' => null]);
});

test('a failed next activity rolls back automatically completed care and its experience', function () {
    $pet = Pet::factory()->create(['energy' => 5, 'energy_max' => 100]);
    $nap = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    $this->travelTo($nap->ends_at);

    expect(fn () => app(StartPetCare::class)->handle($pet->user, $pet->id, 'sleep', [], (string) Str::uuid()))
        ->toThrow(PetUnavailable::class, 'This action is cooling down. Wait before trying again.');

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    expect($pet->user->fresh()->pet_statistics)->toBe([]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $nap->id, 'completed_at' => null, 'experience_awarded' => null]);
});

test('several dogs share statistics and completed actions count days at Moscow midnight', function () {
    config(['doglive.work_timezone' => 'Europe/Moscow']);
    $this->travelTo(now()->setTime(20, 50));
    $owner = User::factory()->create();
    $first = Pet::factory()->for($owner)->create(['hydration' => 50, 'hydration_max' => 100]);
    $second = Pet::factory()->for($owner)->create(['hydration' => 50, 'hydration_max' => 100]);
    foreach ([$first, $second] as $pet) {
        $care = app(StartPetCare::class)->handle($owner, $pet->id, 'water', [], (string) Str::uuid());
        $this->travelTo($care->ends_at);
        app(CompletePetCare::class)->handle($owner, $pet->id, $care->token);
    }
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '20', 'active_days' => 1]);
    $this->travelTo(now()->setTime(21, 1));
    $third = Pet::factory()->for($owner)->create(['hydration' => 50, 'hydration_max' => 100]);
    $care = app(StartPetCare::class)->handle($owner, $third->id, 'water', [], (string) Str::uuid());
    $this->travelTo($care->ends_at);

    app(CompletePetCare::class)->handle($owner, $third->id, $care->token);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '30', 'active_days' => 2]);
    expect($owner->fresh()->pet_statistics)->toBe(['care.water' => 3]);
});

test('the progress boundary refuses an unsaved receipt without changing player progress', function () {
    $owner = User::factory()->create();
    $receipt = new PetCareAction;

    expect(fn () => app(PlayerProgress::class)->award($owner, $receipt))
        ->toThrow(InvalidArgumentException::class, 'Player progress requires a saved action receipt.');

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseCount('pet_care_actions', 0);
});

test('the progress boundary checks saved completion instead of a changed receipt instance', function () {
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id]);
    $receipt->completed_at = now();

    expect(fn () => app(PlayerProgress::class)->award($pet->user, $receipt))
        ->toThrow(InvalidArgumentException::class, 'Player progress requires a completed action owned by the player.');

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'completed_at' => null, 'experience_awarded' => null]);
});

test('the progress boundary checks saved ownership instead of a changed receipt instance', function () {
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id,
        'ends_at' => now(), 'completed_at' => now()]);
    $receipt->user_id = $viewer->id;

    expect(fn () => app(PlayerProgress::class)->award($viewer, $receipt))
        ->toThrow(InvalidArgumentException::class, 'Player progress requires a completed action owned by the player.');

    $this->assertDatabaseHas('users', ['id' => $viewer->id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => null]);
});

test('replaying the progress boundary keeps its first reward after balance edits including a zero reward', function (int $firstReward) {
    config(['player-progress.rewards' => ['care.attention' => $firstReward]]);
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id,
        'ends_at' => now(), 'completed_at' => now()]);

    expect(app(PlayerProgress::class)->award($pet->user, $receipt))->toBe($firstReward);
    config(['player-progress.rewards' => ['care.attention' => 99]]);
    expect(app(PlayerProgress::class)->award($pet->user, $receipt))->toBe($firstReward);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => (string) $firstReward, 'active_days' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => $firstReward]);
    expect($pet->user->fresh()->pet_statistics)->toBe(['care.attention' => 1]);
})->with(['ordinary reward' => [10], 'zero reward' => [0]]);

test('malformed configured rewards cannot record player progress', function (mixed $reward) {
    config(['player-progress.rewards' => ['care.attention' => $reward]]);
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id,
        'ends_at' => now(), 'completed_at' => now()]);

    expect(fn () => app(PlayerProgress::class)->award($pet->user, $receipt))
        ->toThrow(InvalidArgumentException::class, 'Player experience rewards must be non-negative integers.');

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => null]);
    expect($pet->user->fresh()->pet_statistics)->toBe([]);
})->with([
    'negative reward' => [-1],
    'numeric string' => ['10'],
    'fractional number' => [10.5],
    'boolean' => [true],
]);

test('a failed experience receipt marker rolls back the player reward and counters', function () {
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id,
        'ends_at' => now(), 'completed_at' => now()]);
    PetCareAction::updating(fn () => throw new RuntimeException('Cannot record awarded experience.'));

    try {
        expect(fn () => app(PlayerProgress::class)->award($pet->user, $receipt))
            ->toThrow(RuntimeException::class, 'Cannot record awarded experience.');
    } finally {
        PetCareAction::flushEventListeners();
    }

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => null]);
    expect($pet->user->fresh()->pet_statistics)->toBe([]);
    expect(app(PlayerProgress::class)->award($pet->user, $receipt))->toBe(10);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10', 'active_days' => 1]);
});
