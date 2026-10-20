<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Illuminate\Support\Str;

function regulatedIntegrationEvent(string $discipline, array $regulation = [], int $fieldSize = 1): GameEvent
{
    return GameEvent::factory()->create([
        'discipline' => $discipline,
        'rules' => [
            'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10,
            'stages' => $discipline === 'conformation' ? ['type', 'structure', 'movement'] : ['opening', 'technical', 'finish'],
            'field_size' => $fieldSize, 'time_limit' => 360,
            'regulation' => array_replace([
                'version' => 1, 'system' => 'rkf', 'rank' => 'qualifying', 'country' => 'RU',
                'region' => 'moscow', 'judgeId' => 'judge-1', 'judgeName' => 'Judge One', 'format' => $discipline,
            ], $regulation),
        ],
    ]);
}

function regulatedClearFrozenEntry(GameEvent $event, Pet $pet, array $classification, int $stat = 100): GameEventEntry
{
    $event->update(['status' => 'frozen', 'seed' => 'regulated-clear-fixture']);
    $stages = app(RegulatedEventRules::class)->stages($event->discipline, $event->rules['regulation'], $classification);
    $event->update(['rules' => [...$event->rules, 'stages' => $stages]]);
    $plan = [...$classification, 'stages' => array_fill(0, count($stages), 'careful')];
    $snapshot = app(GameEventAdmission::class)->snapshot($event, $pet, $plan, []);
    $snapshot['stats'] = array_fill_keys(array_keys($snapshot['stats']), $stat);
    $snapshot['states'] = array_fill_keys(array_keys($snapshot['states']), 100);
    $snapshot['exterior'] = ['type' => 100, 'structure' => 100, 'movement' => 100];
    $randomness = new GameEventRandomness;
    $attempt = 0;
    do {
        $token = $randomness->token('clear-entry:'.$event->id.':'.$pet->id.':'.$attempt++);
        $draws = $randomness->draws($event->seed.':entry:'.$token, count($stages) * 2);
        $clear = collect($draws)->every(fn (float $draw, int $index): bool => $draw > ($index % 2 === 0 ? 0.8 : 0.32));
    } while (! $clear);

    return GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'frozen', 'plan' => $plan, 'snapshot' => $snapshot, 'operation_token' => $token,
        'division' => app(GameEventAdmission::class)->division($event, $pet, $plan),
    ]);
}

test('a healthy ten year old dog sees real agility options and can enter its measured height category', function () {
    $this->freezeSecond();
    $event = regulatedIntegrationEvent('agility');
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'born_at' => $event->starts_at->subDays(120),
        'event_profile' => ['height_cm' => 45, 'registered' => true],
    ]);

    $dogs = app(GetGameEvents::class)->registrationDogs($pet->user, 'en', $event);
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['stages' => ['balanced', 'balanced', 'balanced'], 'sport_class' => 'agility_1'], [], 25, (string) Str::uuid());

    expect($dogs[0]['entryOptions'])->not->toBeEmpty();
    expect($dogs[0]['profile']['heightCategory'])->toBe('I');
    expect($entry->division)->toContain('agility_1')->toContain('I');
    expect($pet->fresh()->retired_at)->toBeNull();
});

test('regulated sports keep a single category beyond eight humans rather than awarding independent heats', function () {
    $this->freezeSecond();
    $event = regulatedIntegrationEvent('agility', fieldSize: 8);
    foreach (range(1, 9) as $number) {
        $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
            'event_profile' => ['height_cm' => 40, 'registered' => true],
        ]);
        app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
            ['stages' => ['balanced', 'balanced', 'balanced'], 'sport_class' => 'agility_1'], [], 25, (string) Str::uuid());
    }
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($event->entries()->where('is_npc', false)->distinct()->pluck('division'))->toHaveCount(1);
    expect($event->entries()->where('is_npc', false)->where('status', 'completed')->count())->toBe(9);
    expect($event->entries()->where('is_npc', false)->where('status', 'withdrawn')->count())->toBe(0);
});

test('a show assessment below the qualifying grade earns no placement or certificate even without rivals', function () {
    $this->freezeSecond();
    $event = regulatedIntegrationEvent('conformation', ['rank' => 'cac_cf']);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 500]))->create([
        'exterior' => ['type' => 0, 'structure' => 0, 'movement' => 0],
    ]);
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['stages' => ['careful', 'careful', 'careful'], 'exhibition_class' => 'open'], [], 25, (string) Str::uuid());
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->rank)->toBeNull();
    expect($entry->fresh()->prize)->toBe(0);
    expect(PetTitle::query()->where('game_event_entry_id', $entry->id)->exists())->toBeFalse();
});

test('a six month old nosework starter needs a passed odor test and receives a four category entry plan', function () {
    $this->freezeSecond();
    $event = regulatedIntegrationEvent('nosework');
    $event->update(['rules' => [...$event->rules, 'stages' => ['interior', 'exterior', 'objects', 'transport']]]);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['born_at' => $event->starts_at->subDays(6)]);
    $plan = ['stages' => ['careful', 'careful', 'balanced', 'balanced'], 'sport_class' => 'nw_0', 'program' => 'pr1'];

    expect(fn () => app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, $plan, [], 25, (string) Str::uuid()))
        ->toThrow(GameEventUnavailable::class);
    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'nosework', 'regulatory_progress' => ['nosework_tests' => ['pr1' => ['birch'], 'pr2' => []]],
    ]);

    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, $plan, [], 25, (string) Str::uuid());

    expect($entry->plan['stages'])->toHaveCount(4);
    expect($entry->plan['program'])->toBe('pr1');
});

test('a previously completed legacy event keeps its old award identity alongside new certificate kinds', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = GameEvent::factory()->create(['status' => 'settled']);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create(['status' => 'completed', 'rank' => 1]);
    $title = PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => $entry->id, 'code' => 'agility_daily_winner', 'discipline' => 'agility', 'frequency' => 'daily',
    ]);

    $state = app(GetGameEvents::class)->showState($pet->user, $event, 'en');

    expect($title->fresh()->kind)->toBe('legacy');
    expect($state['entry']['awards'][0]['kind'])->toBe('legacy');
    expect($state['event']['regulation'])->toBeNull();
});

test('new schedules expose regulated ranks and beginner odor tests without changing registered legacy events', function () {
    $this->travelTo('2026-10-20 06:00:00 UTC');
    $legacy = GameEvent::factory()->create([
        'discipline' => 'agility', 'frequency' => 'daily', 'starts_at' => now()->setTime(9, 0),
        'closes_at' => now()->setTime(8, 45), 'ends_at' => now()->setTime(9, 10),
    ]);
    GameEventEntry::factory()->for($legacy, 'event')->create();
    $oldRules = $legacy->rules;

    app(GameEventSchedule::class)->ensureUpcoming();
    $count = GameEvent::query()->count();
    app(GameEventSchedule::class)->ensureUpcoming();

    expect(GameEvent::query()->count())->toBe($count);
    expect($legacy->fresh()->rules)->toBe($oldRules);
    expect(GameEvent::query()->where('rules->version', 3)->exists())->toBeTrue();
    expect(GameEvent::query()->where('discipline', 'nosework')->where('rules->regulation->format', 'odor_test')->exists())->toBeTrue();
    expect(GameEvent::query()->where('rules->regulation->system', 'fci')->where('rules->regulation->country', 'RU')->exists())->toBeFalse();
    expect(GameEvent::query()->where('frequency', 'weekly')->where('discipline', 'agility')->get()->pluck('rules.regulation.judgeId')->unique()->count())->toBeGreaterThan(1);
});

test('three junior certificates under different judges award one cumulative title and survive processor retries', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(Dog::factory()->state(['breed' => 'german_shepherd']))->create([
        'born_at' => now()->subDays(12), 'event_profile' => ['pedigree_verified' => true],
    ]);
    foreach (range(1, 3) as $number) {
        $event = regulatedIntegrationEvent('conformation', ['rank' => 'cac_cf', 'judgeId' => 'junior-judge-'.$number]);
        $entry = regulatedClearFrozenEntry($event, $pet, ['exhibition_class' => 'junior']);
        $this->travelTo($event->ends_at);
        app(GameEventProcessor::class)->processDue();
        expect($entry->fresh()->rank)->toBe(1);
        app(GameEventProcessor::class)->processDue();
    }

    expect($pet->titles()->where('code', 'JCAC')->count())->toBe(3);
    expect($pet->titles()->where('code', 'JCH_RUS')->count())->toBe(1);
    expect($pet->titles()->where('code', 'CH_RUS')->exists())->toBeFalse();
    expect($pet->sportRecords()->where('discipline', 'conformation')->value('starts'))->toBe(3);
    expect($pet->titles()->where('code', 'PLACE_1')->first()->details['classKey'])->toBe('junior');
});

test('agility qualification advances through clear agility runs while jumping does not satisfy the level transition', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    foreach (['agility', 'agility', 'jumping', 'agility'] as $index => $format) {
        $event = regulatedIntegrationEvent('agility', ['format' => $format, 'judgeId' => 'agility-judge-'.($index % 2)]);
        regulatedClearFrozenEntry($event, $pet, ['sport_class' => 'agility_1']);
        $this->travelTo($event->ends_at);
        app(GameEventProcessor::class)->processDue();
        $level = $pet->sportRecords()->where('discipline', 'agility')->first()->regulatory_progress['agility_level'];
        expect($level)->toBe($index === 3 ? 2 : 1);
    }
});

test('an odor test records qualification without a placement and unlocks a beginner search', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['born_at' => now()->subDays(6)]);
    $event = regulatedIntegrationEvent('nosework', ['format' => 'odor_test']);
    $entry = regulatedClearFrozenEntry($event, $pet, ['sport_class' => 'nw_test', 'program' => 'pr1', 'test_type' => 'birch']);
    $this->travelTo($event->ends_at);
    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->rank)->toBeNull();
    expect($entry->fresh()->prize)->toBe(0);
    expect($entry->titles()->pluck('code')->all())->toBe(['NW_ODOR_TEST']);
    expect($pet->sportRecords()->where('discipline', 'nosework')->first()->regulatory_progress['nosework_tests']['pr1'])->toBe(['birch']);
    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');
    expect($career['results']['entries'][0]['rank'])->toBeNull();
    expect($career['titles'][0]['kind'])->toBe('qualification');
    $search = regulatedIntegrationEvent('nosework');
    $options = app(GetGameEvents::class)->registrationDogs($pet->user, 'en', $search)[0]['entryOptions'];
    expect(collect($options)->firstWhere('key', 'nw_0:pr1')['eligible'])->toBeTrue();
});

test('nine qualified sports participants receive one complete standing including ninth place', function () {
    $this->freezeSecond();
    $event = regulatedIntegrationEvent('agility');
    foreach (range(1, 9) as $number) {
        $pet = Pet::factory()->create(['event_profile' => ['height_cm' => 40]]);
        regulatedClearFrozenEntry($event, $pet, ['sport_class' => 'agility_1'], 50 + $number * 5);
    }
    $this->travelTo($event->ends_at);
    app(GameEventProcessor::class)->processDue();

    expect($event->entries()->where('rank', 9)->count())->toBe(1);
    expect($event->entries()->where('status', 'completed')->count())->toBe(9);
});

test('repeat cup wins remain distinct credentials for a single cumulative sports championship', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(Dog::factory()->state(['breed' => 'german_shepherd']))->create();
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]],
    ]);
    foreach (range(1, 2) as $number) {
        $event = regulatedIntegrationEvent('agility', ['rank' => 'cup', 'judgeId' => 'cup-judge-'.$number]);
        regulatedClearFrozenEntry($event, $pet, ['sport_class' => 'agility_3']);
        $this->travelTo($event->ends_at);
        app(GameEventProcessor::class)->processDue();
    }

    expect($pet->titles()->where('code', 'SPORT_CUP_RUS')->count())->toBe(2);
    expect($pet->titles()->where('code', 'SPORT_NATIONAL_CH')->count())->toBe(1);
});

test('an international show completes the agility championship when its assessment is the last missing credential', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(Dog::factory()->state(['breed' => 'german_shepherd']))->create([
        'event_profile' => ['pedigree_verified' => true],
    ]);
    foreach ([['DE', now()->subDays(13)], ['FR', now()->subHour()]] as $index => [$country, $at]) {
        $event = GameEvent::factory()->create([
            'status' => 'settled', 'registration_opens_at' => $at->subDay(), 'closes_at' => $at->subMinutes(15),
            'starts_at' => $at, 'ends_at' => $at->addMinutes(10), 'settled_at' => $at->addMinutes(10),
        ]);
        $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create(['status' => 'completed']);
        PetTitle::factory()->for($pet)->create([
            'game_event_entry_id' => $entry->id, 'code' => 'CACIAG', 'kind' => 'certificate', 'discipline' => 'agility',
            'regulation_version' => '1', 'awarded_at' => $at,
            'details' => ['judgeId' => 'inter-judge-'.$index, 'country' => $country, 'rank' => 'certificate'],
        ]);
    }
    $show = regulatedIntegrationEvent('conformation', ['rank' => 'cacib', 'system' => 'fci', 'country' => 'DE']);
    regulatedClearFrozenEntry($show, $pet, ['exhibition_class' => 'open']);
    $this->travelTo($show->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($pet->titles()->where('discipline', 'agility')->where('code', 'CIAG')->count())->toBe(1);
});

test('nosework records the podium of each qualified search category separately from the overall class', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = regulatedIntegrationEvent('nosework');
    $entry = regulatedClearFrozenEntry($event, $pet, ['sport_class' => 'nw_0', 'program' => 'pr2']);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    expect($entry->fresh()->result['standings'])->toHaveCount(5);
    expect($entry->titles()->where('kind', 'placement')->where('code', 'like', 'NW_CATEGORY_%')->count())->toBe(4);
    expect($entry->titles()->where('code', 'PLACE_1')->count())->toBe(1);
});
