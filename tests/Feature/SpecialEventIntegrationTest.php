<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\PetTitleConsumption;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Services\EventTitleRecorder;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function specialIntegrationEvent(string $discipline, array $regulation = [], int $fieldSize = 1): GameEvent
{
    $regulation = [...[
        'version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'country' => 'RU',
        'region' => 'moscow', 'judgeId' => 'judge-current', 'judgeName' => 'Current Judge', 'format' => $discipline,
    ], ...$regulation];

    return GameEvent::factory()->create([
        'discipline' => $discipline,
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10,
            'stages' => app(RegulatedEventRules::class)->stages($discipline, $regulation, []),
            'field_size' => $fieldSize, 'time_limit' => 360, 'regulation' => $regulation],
    ]);
}

function specialIntegrationDog(array $attributes = []): Pet
{
    $breed = Dog::query()->where('breed', 'german_shepherd')->first() ?? Dog::factory()->create(['breed' => 'german_shepherd']);

    return Pet::factory()->for($breed)
        ->for(User::factory()->state(['coins' => 5000]))->create([
            'born_at' => now()->subDays(120), 'lifespan_months' => 192,
            'event_profile' => ['registered' => true, 'pedigree_verified' => true, 'height_cm' => 40],
            ...$attributes,
        ]);
}

function specialIntegrationReceipt(Pet $pet, int $number, string $region, string $code = 'REG_CACAg'): PetTitle
{
    $at = now()->subHours(20)->addMinutes($number);
    $event = GameEvent::factory()->create([
        'status' => 'settled', 'registration_opens_at' => $at->subDay(), 'closes_at' => $at->subMinutes(15),
        'starts_at' => $at, 'ends_at' => $at->addMinutes(10), 'settled_at' => $at->addMinutes(10),
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create(['status' => 'completed']);

    return PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => $entry->id, 'game_event_id' => $event->id, 'code' => $code,
        'kind' => 'certificate', 'discipline' => 'agility', 'regulation_version' => '2', 'awarded_at' => $at,
        'details' => ['judgeId' => 'proof-judge-'.($number % 2), 'country' => 'RU', 'region' => $region, 'rank' => 'certificate'],
    ]);
}

function specialIntegrationFreeze(GameEvent $event, Pet $pet, array $classification): GameEventEntry
{
    $event->update(['status' => 'frozen']);
    $stages = app(RegulatedEventRules::class)->stages($event->discipline, $event->rules['regulation'], $classification);
    $event->update(['rules' => [...$event->rules, 'stages' => $stages]]);
    $plan = [...$classification, 'stages' => array_fill(0, count($stages), 'careful')];
    $snapshot = app(GameEventAdmission::class)->snapshot($event, $pet, $plan, []);
    $snapshot['stats'] = array_fill_keys(array_keys($snapshot['stats']), 100);
    $snapshot['states'] = array_fill_keys(array_keys($snapshot['states']), 100);
    $snapshot['exterior'] = ['type' => 100, 'structure' => 100, 'movement' => 100];

    return GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'frozen', 'plan' => $plan, 'snapshot' => $snapshot,
        'division' => app(GameEventAdmission::class)->division($event, $pet, $plan),
    ]);
}

beforeEach(function () {
    $this->freezeSecond();
    config(['event_regulations.simulation.minimum_error_probability' => 0, 'event_regulations.simulation.maximum_error_probability' => 0]);
});

test('special editions follow game months with two separate judges and safe upgrades of empty registrations', function () {
    $this->travelTo('2026-10-20 03:00:00 UTC');
    Dog::factory()->create(['breed' => 'german_shepherd']);
    Dog::factory()->count(8)->create(['event_profile' => ['recognized_rkf' => true, 'club_system' => 'breed']]);
    config(['game-events.special_calendar.breed_interval_minutes' => 15]);
    $empty = specialIntegrationEvent('agility', ['version' => 1]);
    $empty->update(['starts_at' => now()->setTime(9, 0), 'closes_at' => now()->setTime(8, 45), 'ends_at' => now()->setTime(9, 10)]);
    $registered = specialIntegrationEvent('nosework', ['version' => 1]);
    $registered->update(['starts_at' => now()->setTime(15, 0), 'closes_at' => now()->setTime(14, 45), 'ends_at' => now()->setTime(15, 10)]);
    GameEventEntry::factory()->for($registered, 'event')->create();
    $original = $registered->rules;

    app(GameEventSchedule::class)->ensureUpcoming();
    $count = GameEvent::query()->count();
    app(GameEventSchedule::class)->ensureUpcoming();
    $eurasia = GameEvent::query()->where('rules->regulation->specialShow', 'eurasia')->orderBy('starts_at')->get();

    expect(GameEvent::query()->count())->toBe($count);
    expect($empty->fresh()->rules['regulation']['version'])->toBe(2);
    expect($registered->fresh()->rules)->toBe($original);
    expect($eurasia)->toHaveCount(16);
    expect($eurasia[0]->rules['regulation']['showEdition'])->toBe('2050-05');
    expect($eurasia[15]->rules['regulation']['showEdition'])->toBe('2050-12');
    expect(GameEvent::query()->whereIn('rules->regulation->specialShow', ['national_club', 'national_breed'])->count())->toBe(72);
    expect($eurasia[0]->rules['regulation']['showEdition'])->toBe($eurasia[1]->rules['regulation']['showEdition']);
    expect($eurasia[0]->rules['regulation']['judgeId'])->not->toBe($eurasia[1]->rules['regulation']['judgeId']);
    expect($eurasia[0]->starts_at->diffInHours($eurasia[1]->starts_at))->toBe(3.0);
    expect($eurasia[0]->starts_at->diffInDays($eurasia[2]->starts_at))->toBe(1.0);
    expect($eurasia[0]->rules['regulation']['showEdition'])->not->toBe($eurasia[2]->rules['regulation']['showEdition']);
    expect($eurasia[0]->rules['regulation']['sources'])->toHaveCount(2);
    expect($eurasia[0]->registration_opens_at)->toEqual($eurasia[0]->starts_at->subDay());
    expect(GameEvent::query()->where('rules->regulation->format', 'agility_combined')->first()->rules['stages'])->toHaveCount(6);
});

test('both Eurasia legs produce a scoped absolute winner without bypassing the national pedigree requirement', function () {
    $pet = specialIntegrationDog(['event_profile' => ['registered' => true, 'pedigree_verified' => false]]);
    foreach (['president', 'cis'] as $index => $leg) {
        $event = specialIntegrationEvent('conformation', [
            'rank' => 'cac_rkf_special', 'judgeId' => 'eurasia-'.$index, 'specialShow' => 'eurasia',
            'showLeg' => $leg, 'showYear' => 2026, 'showEdition' => '2026-10',
        ]);
        specialIntegrationFreeze($event, $pet, ['exhibition_class' => 'open']);
        $this->travelTo($event->ends_at);
        app(GameEventProcessor::class)->processDue();
        app(GameEventProcessor::class)->processDue();
    }

    expect($pet->titles()->where('code', 'EAW')->count())->toBe(2);
    expect($pet->titles()->where('code', 'EAAW')->count())->toBe(1);
    $absolute = $pet->titles()->where('code', 'EAAW')->firstOrFail();
    expect($absolute->scope_key)->toBe('edition:2026-10');
    expect($absolute->proofs()->count())->toBe(2);
    expect($pet->titles()->whereIn('code', ['CH_RUS', 'KCHK'])->exists())->toBeFalse();
    expect($pet->titles()->where('code', 'CAC')->get()->pluck('details.national_certificate_weight')->all())->toBe([2, 2]);
});

test('regional diplomas from two subjects unlock a national title in the same settlement', function () {
    $pet = specialIntegrationDog();
    foreach (['moscow', 'moscow', 'moscow', 'saint_petersburg', 'saint_petersburg', 'saint_petersburg'] as $index => $region) {
        specialIntegrationReceipt($pet, $index + 1, $region);
    }
    $event = specialIntegrationEvent('agility');
    specialIntegrationFreeze($event, $pet, ['sport_class' => 'agility_1']);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    expect($pet->titles()->where('code', 'SPORT_REGIONAL_CH')->count())->toBe(2);
    expect($pet->titles()->where('code', 'SPORT_NATIONAL_CH')->count())->toBe(1);
    expect($pet->titles()->where('code', 'SPORT_NATIONAL_CH')->firstOrFail()->details['exchange_path'])->toBe('national_7');
    expect(PetTitleConsumption::query()->where('purpose', 'sport_exchange')->count())->toBe(8);
});

test('two regional diplomas from the same subject remain distinct without satisfying the two region national path', function () {
    $pet = specialIntegrationDog();
    foreach (range(1, 6) as $number) {
        specialIntegrationReceipt($pet, $number, 'moscow');
    }
    $event = specialIntegrationEvent('agility');
    specialIntegrationFreeze($event, $pet, ['sport_class' => 'agility_1']);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($pet->titles()->where('code', 'SPORT_REGIONAL_CH')->reorder()->distinct()->pluck('scope_key'))->toHaveCount(2);
    expect($pet->titles()->where('code', 'SPORT_NATIONAL_CH')->exists())->toBeFalse();
    expect(PetTitleConsumption::query()->count())->toBe(6);
});

test('national agility registration freezes and settles two real courses before awarding its certificate', function () {
    $event = specialIntegrationEvent('agility', ['rank' => 'certificate', 'format' => 'agility_combined'], 8);
    $pet = specialIntegrationDog(['endurance' => 100, 'speed' => 100, 'strength' => 100, 'agility' => 100,
        'obedience' => 100, 'intelligence' => 100, 'bond' => 100]);
    PetSportRecord::factory()->for($pet)->create(['discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]]]);
    $plan = ['sport_class' => 'agility_3', 'stages' => array_fill(0, 6, 'careful')];
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, $plan, [], 25, (string) Str::uuid());
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    $result = $entry->fresh()->result;
    expect($result['stages'])->toHaveCount(6);
    expect(array_keys($result['legs']))->toBe(['agility', 'jumping']);
    expect($result['legs']['agility']['total_faults'])->toEqual(0.0);
    expect($result['legs']['jumping']['total_faults'])->toEqual(0.0);
    expect($entry->titles()->where('code', 'CACAg')->count())->toBe(1);
    expect($event->entries()->where('status', 'completed')->count())->toBe(8);
});

test('championship exchange rejects another dogs proof and duplicate consumption atomically', function () {
    $pet = specialIntegrationDog();
    $source = specialIntegrationReceipt($pet, 1, 'moscow');
    $foreign = specialIntegrationReceipt(specialIntegrationDog(), 2, 'moscow');
    $event = specialIntegrationEvent('agility');
    $entry = specialIntegrationFreeze($event, $pet, ['sport_class' => 'agility_1']);
    $recorder = app(EventTitleRecorder::class);
    $award = ['code' => 'SPORT_REGIONAL_CH', 'kind' => 'championship', 'scope_key' => 'first',
        'details' => ['credential_title_ids' => [$source->id], 'proof_purpose' => 'sport_exchange']];
    DB::transaction(fn () => $recorder->record($event, $entry, $pet, $award));

    expect(fn () => DB::transaction(fn () => $recorder->record($event, $entry, $pet, [...$award, 'scope_key' => 'second'])))
        ->toThrow(LogicException::class);
    expect(fn () => DB::transaction(fn () => $recorder->record($event, $entry, $pet, [...$award, 'scope_key' => 'foreign',
        'details' => ['credential_title_ids' => [$foreign->id], 'proof_purpose' => 'sport_exchange']])))
        ->toThrow(LogicException::class);
    expect($pet->titles()->where('code', 'SPORT_REGIONAL_CH')->count())->toBe(1);
    expect(PetTitleConsumption::query()->count())->toBe(1);
});
