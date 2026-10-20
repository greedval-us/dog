<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Queries\GetPetEventProfile;
use App\Modules\Pets\Queries\GetTitleProgressEvents;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

function titleProgressPet(array $attributes = []): Pet
{
    $breed = Dog::query()->where('breed', 'german_shepherd')->first() ?? Dog::factory()->create(['breed' => 'german_shepherd']);

    return Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 500]))->create([
        'born_at' => now()->subDays(30), 'event_profile' => ['registered' => true, 'pedigree_verified' => true, 'height_cm' => 40],
        ...$attributes,
    ]);
}

function titleProgressCertificate(Pet $pet, string $code, string $judge, string $discipline = 'conformation', array $details = [], ?CarbonImmutable $at = null): PetTitle
{
    $at ??= now()->subHours(2);
    $event = GameEvent::factory()->create([
        'discipline' => $discipline, 'status' => 'settled',
        'registration_opens_at' => $at->subDay(), 'closes_at' => $at->subMinutes(15), 'starts_at' => $at,
        'ends_at' => $at->addMinutes(10), 'settled_at' => $at->addMinutes(10),
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'completed', 'completed_at' => $at->addMinutes(10), 'result' => ['eliminated' => false],
    ]);

    return PetTitle::factory()->for($pet)->create([
        'game_event_id' => $event->id, 'game_event_entry_id' => $entry->id, 'code' => $code,
        'discipline' => $discipline, 'kind' => 'certificate', 'regulation_version' => '2', 'awarded_at' => $at,
        'details' => ['judgeId' => $judge, 'country' => 'RU', 'region' => 'moscow', 'rank' => 'cac_cf', ...$details],
    ]);
}

function titleProgressUpcoming(string $discipline, array $changes = [], ?CarbonImmutable $startsAt = null): GameEvent
{
    $regulation = ['version' => 2, 'system' => 'rkf', 'country' => 'RU', 'region' => 'moscow', 'judgeId' => 'next-judge',
        'judgeName' => 'Next judge', 'rank' => $discipline === 'conformation' ? 'cac_cf' : 'certificate',
        'format' => $discipline === 'agility' ? 'agility_combined' : $discipline, ...$changes];
    $startsAt ??= now()->addHours(3);

    return GameEvent::factory()->create([
        'discipline' => $discipline, 'registration_opens_at' => now()->subHour(), 'closes_at' => $startsAt->subMinutes(15),
        'starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes(10),
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10, 'field_size' => 8,
            'regulation' => $regulation, 'stages' => app(RegulatedEventRules::class)->stages($discipline, $regulation, [])],
    ]);
}

test('a dog without results has title goals and conditional event recommendations without changing game state', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    $event = titleProgressUpcoming('conformation');

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    $goal = collect($career['titleProgress']['goals'])->firstWhere('code', 'CH_RUS');
    expect($goal)->not->toBeNull();
    expect(collect($goal['paths'])->flatMap(fn (array $path): array => $path['events'])->pluck('id'))->toContain($event->id);
    expect($career['titleProgress']['generatedAt'])->toBe(now()->toIso8601String());
    expect($pet->user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('pet_titles', 0);
    $this->assertDatabaseCount('pet_title_consumptions', 0);
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('career progress exposes existing certificates and recommends a new judge for missing national requirements', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    $certificates = collect(['first', 'second', 'third'])->map(fn (string $judge, int $index): PetTitle => titleProgressCertificate($pet, 'CAC', $judge, at: now()->subDays(14 - $index)));
    $oldJudge = titleProgressUpcoming('conformation', ['judgeId' => 'first']);
    $nextJudge = titleProgressUpcoming('conformation', ['judgeId' => 'fourth'], now()->addHours(6));

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'ru')['titleProgress'];

    $goal = collect($progress['goals'])->firstWhere('code', 'CH_RUS');
    $path = collect($goal['paths'])->firstWhere('key', 'standard_interval');
    expect(collect($path['credentials'])->pluck('id')->sort()->values()->all())->toBe($certificates->pluck('id')->all());
    expect(collect($path['requirements'])->firstWhere('key', 'judges'))->toMatchArray(['current' => 3, 'target' => 4, 'met' => false]);
    expect(collect($path['events'])->pluck('id'))->toContain($nextJudge->id)->not->toContain($oldJudge->id);
});

test('a completed certificate set remains ready after the age window and never grants a title during a career read', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    foreach (['first', 'second', 'third'] as $index => $judge) {
        titleProgressCertificate($pet, 'JCAC', $judge, at: now()->subDays(17 - $index));
    }

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    expect(collect($progress['goals'])->firstWhere('code', 'JCH_RUS')['status'])->toBe('ready');
    expect($pet->titles()->where('code', 'JCH_RUS')->exists())->toBeFalse();
    $this->assertDatabaseCount('pet_title_consumptions', 0);
});

test('junior certificate suggestions use age at the start rather than age when the career is opened', function () {
    $this->freezeSecond();
    $pet = titleProgressPet(['born_at' => now()->subDays(18)->addHour()]);
    $event = titleProgressUpcoming('conformation', startsAt: now()->addHours(2));

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->firstWhere('code', 'JCH_RUS');
    expect(collect($goal['paths'])->flatMap(fn (array $path): array => $path['events'])->pluck('id'))->not->toContain($event->id);
});

test('regional sport recommendations keep the subject and ignore a different subjects event', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    PetSportRecord::factory()->for($pet)->create(['discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]]]);
    titleProgressCertificate($pet, 'REG_CACAg', 'one', 'agility', ['rank' => 'certificate']);
    titleProgressCertificate($pet, 'REG_CACAg', 'two', 'agility', ['rank' => 'certificate'], now()->subHours(3));
    $moscow = titleProgressUpcoming('agility');
    $petersburg = titleProgressUpcoming('agility', ['region' => 'saint_petersburg'], now()->addHours(6));
    $legacy = titleProgressUpcoming('agility', ['version' => 1], now()->addHours(9));

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->first(fn (array $goal): bool => $goal['code'] === 'SPORT_REGIONAL_CH' && $goal['discipline'] === 'agility' && str_contains($goal['key'], 'region:moscow'));
    expect($goal)->not->toBeNull();
    expect(collect($goal['paths'][0]['events'])->pluck('id'))->toContain($moscow->id)->not->toContain($petersburg->id, $legacy->id);
});

test('an existing entry only marks its actual exhibition class as registered', function () {
    $this->freezeSecond();
    $pet = titleProgressPet(['born_at' => now()->subDays(16)]);
    $event = titleProgressUpcoming('conformation');
    GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'registered', 'plan' => ['exhibition_class' => 'junior', 'stages' => ['bold', 'bold', 'bold']],
    ]);
    $profile = app(GetPetEventProfile::class)->forPet($pet);

    $candidates = collect(app(GetTitleProgressEvents::class)->candidates($pet->user, $pet, $profile, 'en', now()));

    expect($candidates->first(fn (array $candidate): bool => $candidate['plan']['exhibition_class'] === 'junior'))->toMatchArray(['registered' => true, 'eligible' => true]);
    expect($candidates->first(fn (array $candidate): bool => $candidate['plan']['exhibition_class'] === 'open'))->toMatchArray(['registered' => false, 'eligible' => false]);
});

test('nosework prerequisites suggest advancing classes and skip an already advanced program', function (int $level, bool $needsQualification) {
    $this->freezeSecond();
    $pet = titleProgressPet();
    PetSportRecord::factory()->for($pet)->create(['discipline' => 'nosework', 'regulatory_progress' => [
        'nosework_levels' => ['pr1' => $level, 'pr2' => 0],
        'nosework_tests' => ['pr1' => ['birch', 'lavender', 'anise'], 'pr2' => []],
    ]]);
    $event = titleProgressUpcoming('nosework', ['rank' => 'qualifying']);
    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];
    $goal = collect($progress['goals'])->first(fn (array $goal): bool => $goal['code'] === 'SPORT_NATIONAL_CH' && $goal['discipline'] === 'nosework');
    $recommendations = collect($goal['paths'])->flatMap(fn (array $path): array => $path['events']);

    expect($recommendations->contains('id', $event->id))->toBe($needsQualification);
    if ($needsQualification) {
        expect($recommendations->pluck('classLabel')->unique()->all())->each->toContain('class 0');
    }
})->with(['beginner' => [0, true], 'one advanced program' => [3, false]]);

test('recommendations do not confuse sports that share the same championship code', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    PetSportRecord::factory()->for($pet)->create(['discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]]]);
    $agility = titleProgressUpcoming('agility', ['rank' => 'cup']);
    $canicross = titleProgressUpcoming('canicross', ['rank' => 'cup'], now()->addHours(6));

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->first(fn (array $goal): bool => $goal['code'] === 'SPORT_NATIONAL_CH' && $goal['discipline'] === 'agility');
    $path = collect($goal['paths'])->firstWhere('key', 'national_2');
    expect(collect($path['events'])->pluck('id'))->toContain($agility->id)->not->toContain($canicross->id);
});

test('recommendations resolve an incompatible evidence set rather than suggesting another useless certificate', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    PetSportRecord::factory()->for($pet)->create(['discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]]]);
    foreach ([['a', ''], ['b', ''], ['c', ''], ['d', 'moscow'], ['d', 'tula'], ['d', 'novgorod']] as $index => [$judge, $region]) {
        titleProgressCertificate($pet, 'CACAg', $judge, 'agility', ['region' => $region], now()->subHours(8 - $index));
    }
    $useless = titleProgressUpcoming('agility', ['judgeId' => 'a', 'region' => '']);
    $compatible = titleProgressUpcoming('agility', ['judgeId' => 'e', 'region' => 'moscow'], now()->addHours(6));

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->first(fn (array $goal): bool => $goal['code'] === 'SPORT_NATIONAL_CH' && $goal['discipline'] === 'agility');
    $path = collect($goal['paths'])->firstWhere('key', 'national_6');
    expect($path['ready'])->toBeFalse();
    expect(collect($path['requirements'])->firstWhere('key', 'compatible_evidence')['met'])->toBeFalse();
    expect(collect($path['events'])->pluck('id'))->toContain($compatible->id)->not->toContain($useless->id);
});

test('an earned title retains its source documents instead of restarting a completed route', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    $ids = [];
    foreach (['first', 'second', 'third'] as $index => $judge) {
        $ids[] = titleProgressCertificate($pet, 'JCAC', $judge, at: now()->subDays(17 - $index))->id;
    }
    $title = titleProgressCertificate($pet, 'JCH_RUS', 'third', at: now()->subDays(15)->addMinute());
    $title->update(['kind' => 'championship', 'details' => ['credential_title_ids' => $ids]]);

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->firstWhere('code', 'JCH_RUS');
    expect($goal)->toMatchArray(['status' => 'achieved', 'earnedCount' => 1]);
    expect($goal['paths'])->toHaveCount(1);
    expect(collect($goal['paths'][0]['credentials'])->pluck('id')->all())->toBe($ids);
    expect(collect($goal['paths'][0]['credentials'])->pluck('status')->unique()->all())->toBe(['counted']);
    expect($goal['paths'][0]['events'])->toBe([]);
});

test('legacy proof does not count unrelated awards from the same historical results', function () {
    $this->freezeSecond();
    $pet = titleProgressPet();
    $certificate = titleProgressCertificate($pet, 'JCAC', 'one');
    PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => $certificate->game_event_entry_id, 'game_event_id' => $certificate->game_event_id,
        'code' => 'SHOW_GRADE', 'kind' => 'qualification', 'discipline' => 'conformation',
        'regulation_version' => '1', 'awarded_at' => $certificate->awarded_at,
    ]);
    $title = PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => $certificate->game_event_entry_id, 'game_event_id' => $certificate->game_event_id,
        'code' => 'JCH_RUS', 'kind' => 'championship', 'discipline' => 'conformation',
        'regulation_version' => '1', 'awarded_at' => $certificate->awarded_at,
        'details' => ['credential_entry_ids' => [$certificate->game_event_entry_id]],
    ]);

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->firstWhere('code', 'JCH_RUS');
    expect($goal['status'])->toBe('achieved');
    expect(collect($goal['paths'][0]['credentials'])->pluck('id')->all())->toBe([$title->id]);
    expect($goal['paths'][0]['credentials'][0]['status'])->toBe('available');
    expect($goal['paths'][0]['notes'])->toContain(__('title_progress.notes.historical_proof', [], 'en'));
});

test('voluntary retirement also stops a fresh cycle of an already earned repeatable title', function () {
    $this->freezeSecond();
    $pet = titleProgressPet(['retired_at' => now()->subHour()]);
    $title = titleProgressCertificate($pet, 'SPORT_REGIONAL_CH', 'one', 'agility', ['region' => 'moscow']);
    $title->update(['kind' => 'championship', 'scope_key' => 'region:moscow:issue:historical']);

    $progress = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en')['titleProgress'];

    $goal = collect($progress['goals'])->first(fn (array $goal): bool => $goal['code'] === 'SPORT_REGIONAL_CH' && str_ends_with($goal['key'], 'region:moscow'));
    expect($goal)->toMatchArray(['status' => 'unavailable', 'earnedCount' => 1, 'repeatable' => true]);
});

test('career partial reload remains restricted to the owner and does not publish title progress in the initial dashboard', function () {
    $this->withoutVite();
    $pet = titleProgressPet();
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('dashboard', ['pet' => $pet->id]))->assertNotFound();
    $this->actingAs($pet->user)->get(route('dashboard', ['pet' => $pet->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('career'));
});
