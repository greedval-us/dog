<?php

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\Dog;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\DogWorkType;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\Skill;
use App\Models\Training;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\GenerateDogWorkBoard;
use App\Modules\Pets\Actions\StartBreeding;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Services\PuppyLifecycle;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Database\Seeders\DogWorkTypeSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Support\Str;

test('all default care and attribute training options advertise five to fifteen second waits', function () {
    $this->seed(TrainingSeeder::class);
    $pet = Pet::factory()->create();

    $care = app(GetPetCare::class)->handle($pet->user, $pet->id, 'en');

    expect($care['options'])->toHaveCount(14);
    foreach ($care['options'] as $option) {
        expect($option['duration'])->toBeBetween(5, 15);
        expect($option['cooldown'])->toBeBetween(5, 15);
    }
});

test('fresh water completes after five seconds and its cooldown ends ten seconds later', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['hydration' => 40, 'hydration_max' => 100]);
    $start = app(StartPetCare::class);
    $care = $start->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());
    expect($care->ends_at->diffInSeconds(now(), true))->toBe(5.0);
    $this->travel(4)->seconds();

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(PetUnavailable::class);
    expect($care->fresh()->completed_at)->toBeNull();
    $this->travel(1)->second();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->hydration)->toBeGreaterThan(74);
    $this->travel(9)->seconds();
    expect(fn () => $start->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid()))->toThrow(PetUnavailable::class);
    $this->travel(1)->second();

    $next = $start->handle($pet->user, $pet->id, 'water', [], (string) Str::uuid());

    expect($next->id)->not->toBe($care->id);
    $this->assertDatabaseCount('pet_care_actions', 2);
});

test('seeded sprint training completes after ten seconds and releases its cooldown after twenty seconds', function () {
    $this->freezeSecond();
    $this->seed(TrainingSeeder::class);
    $training = Training::query()->where('code', 'sprint')->firstOrFail();
    expect($training->duration_seconds)->toBe(10);
    $pet = Pet::factory()->create(['energy' => 100, 'speed' => 10, 'speed_potential' => 100]);
    $equipment = InventoryItem::factory()->for($pet->user)->for(
        Item::factory()->for(ItemCategory::factory()->state(['code' => 'sports']), 'category')
    )->create(['bonuses' => [], 'effect_rules' => []]);
    $start = app(StartPetCare::class);
    $care = $start->handle($pet->user, $pet->id, 'training:'.$training->id, ['sports' => $equipment->id], (string) Str::uuid());
    $this->travel(9)->seconds();

    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(PetUnavailable::class);
    $this->travel(1)->second();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->speed)->toBeGreaterThan(10);
    $this->travel(9)->seconds();
    expect(fn () => $start->handle($pet->user, $pet->id, 'training:'.$training->id, ['sports' => $equipment->id], (string) Str::uuid()))->toThrow(PetUnavailable::class);
    $this->travel(1)->second();

    $next = $start->handle($pet->user, $pet->id, 'training:'.$training->id, ['sports' => $equipment->id], (string) Str::uuid());

    expect($next->id)->not->toBe($care->id);
});

test('the same skill can advance again at ten seconds while preserving the first receipt cooldown', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $lessons = app(TrainPetSkill::class);
    $first = $lessons->handle($pet->user, $pet->id, new TrainPetSkillData($skill->id, 1, 100, (string) Str::uuid()));
    expect($first->cooldown_until->diffInSeconds($first->trained_at, true))->toBe(10.0);
    $next = new TrainPetSkillData($skill->id, 2, 200, (string) Str::uuid());
    $this->travel(9)->seconds();

    expect(fn () => $lessons->handle($pet->user, $pet->id, $next))->toThrow(PetUnavailable::class);
    $this->travel(1)->second();
    $second = $lessons->handle($pet->user, $pet->id, $next);

    expect($second->level)->toBe(2);
    expect($first->fresh()->cooldown_until)->toEqual($second->trained_at);
    $this->assertDatabaseCount('pet_skill_lessons', 2);
});

test('new daily work offers finish in fifteen seconds without accelerating the daily board reset', function () {
    $this->freezeSecond();
    $this->seed(DogWorkTypeSeeder::class);
    expect(DogWorkType::query()->firstOrFail()->duration_seconds)->toBe(15);
    $board = app(GenerateDogWorkBoard::class)->handle();
    $offer = $board->offers()->firstOrFail();
    $pet = Pet::factory()->create(['intelligence' => 100, 'obedience' => 100, 'speed' => 100, 'endurance' => 100, 'strength' => 100, 'agility' => 100]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->travel(14)->seconds();

    expect(fn () => app(CompleteDogWork::class)->handle($pet->user, $shift->token))->toThrow(PetUnavailable::class);
    $this->travel(1)->second();
    app(CompleteDogWork::class)->handle($pet->user, $shift->token);

    expect($shift->fresh()->completed_at)->not->toBeNull();
    expect(app(GenerateDogWorkBoard::class)->handle()->id)->toBe($board->id);
    $this->assertDatabaseCount('dog_work_boards', 1);
});

test('new litters are born and their parents can breed again at fifteen seconds with a seven day decision window', function () {
    $this->freezeSecond();
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    $owner = User::factory()->create(['experience' => '1500', 'coins' => 1000]);
    $sireOwner = User::factory()->create(['experience' => '1500']);
    $dam = Pet::factory()->female()->for($owner)->for($dog)->create(['born_at' => now()->subDays(18)]);
    $sire = Pet::factory()->for($sireOwner)->for($dog)->create(['born_at' => now()->subDays(18)]);
    $listing = BreedingListing::factory()->for($sire, 'pet')->for($sireOwner)->create(['price' => 100]);
    $start = app(StartBreeding::class);
    $litter = $start->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid());
    $this->travel(14)->seconds();

    expect(app(PuppyLifecycle::class)->synchronize())->toBe(0);
    expect(fn () => $start->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid()))->toThrow(BreedingUnavailable::class, 'breeding.errors.cooldown');
    $this->travel(1)->second();
    app(PuppyLifecycle::class)->synchronize();

    expect($litter->fresh()->delivered_at)->not->toBeNull();
    expect($litter->expires_at->diffInDays($litter->born_at, true))->toBe(7.0);
    expect($litter->puppies()->where('status', 'unborn')->count())->toBe(0);
    expect($start->handle($owner, $dam->id, 'listing', $listing->id, 100, (string) Str::uuid())->id)->not->toBe($litter->id);
});

test('catalogue timer migration shortens existing offers while retaining launched care and shift snapshots', function () {
    $this->freezeSecond();
    $training = Training::factory()->create(['duration_seconds' => 120, 'cooldown_seconds' => 600]);
    $job = DogWorkType::factory()->create(['duration_seconds' => 1800]);
    $offer = DogWorkOffer::factory()->create(['dog_work_type_id' => $job->id, 'duration_seconds' => 1800]);
    $care = PetCareAction::factory()->create(['ends_at' => now()->addMinutes(2), 'available_at' => now()->addMinutes(12)]);
    $shift = DogWorkShift::factory()->create(['dog_work_offer_id' => $offer->id, 'ends_at' => now()->addMinutes(30)]);
    $litter = BreedingLitter::factory()->create(['born_at' => now()->addDay()]);
    $migration = require database_path('migrations/2026_10_20_143204_accelerate_gameplay_catalogue_timers.php');

    $migration->up();

    expect($training->fresh()->duration_seconds)->toBeBetween(5, 15);
    expect($training->fresh()->cooldown_seconds)->toBeBetween(5, 15);
    expect($job->fresh()->duration_seconds)->toBeBetween(5, 15);
    expect($offer->fresh()->duration_seconds)->toBeBetween(5, 15);
    expect($care->fresh()->ends_at)->toEqual(now()->addMinutes(2));
    expect($care->fresh()->available_at)->toEqual(now()->addMinutes(12));
    expect($shift->fresh()->ends_at)->toEqual(now()->addMinutes(30));
    expect($litter->fresh()->born_at)->toEqual(now()->addDay());
});
