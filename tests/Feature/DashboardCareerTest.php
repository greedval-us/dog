<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Queries\GetPetCareer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeSecond();
});

function careerCompletedEntry(Pet $pet, string $discipline = 'agility', ?int $rank = 1, bool $eliminated = false, ?User $previousOwner = null, ?CarbonImmutable $completedAt = null): GameEventEntry
{
    $completedAt ??= CarbonImmutable::now()->subHour();
    $event = GameEvent::factory()->create([
        'status' => 'settled', 'discipline' => $discipline,
        'registration_opens_at' => $completedAt->subDay(), 'closes_at' => $completedAt->subMinutes(25),
        'starts_at' => $completedAt->subMinutes(10)->subSeconds(GameEvent::query()->count()), 'ends_at' => $completedAt, 'settled_at' => $completedAt,
    ]);

    return GameEventEntry::factory()->for($event, 'event')->for($previousOwner ?? $pet->user)->for($pet)->create([
        'status' => 'completed', 'completed_at' => $completedAt, 'rank' => $rank,
        'prize' => $eliminated || $rank === null ? 0 : 100, 'result' => ['eliminated' => $eliminated, 'score' => 97],
        'snapshot' => ['name' => 'Имя на выступлении', 'buffs' => ['private' => 10], 'states' => ['health' => 94]],
    ]);
}

function careerAward(GameEventEntry $entry): PetTitle
{
    return PetTitle::factory()->for($entry, 'entry')->create([
        'pet_id' => $entry->pet_id, 'discipline' => $entry->event->discipline, 'frequency' => $entry->event->frequency,
        'awarded_at' => $entry->completed_at,
    ]);
}

test('the selected owned dog keeps completed career results and titles from its previous owner', function () {
    $owner = User::factory()->create(['locale' => 'ru']);
    $primary = Pet::factory()->for($owner)->create();
    $selected = Pet::factory()->for($owner)->create(['name' => 'Новое имя']);
    $previousOwner = User::factory()->create();
    $entry = careerCompletedEntry($selected, previousOwner: $previousOwner);
    careerAward($entry);
    careerAward(careerCompletedEntry($primary));
    careerCompletedEntry(Pet::factory()->create());

    $initial = $this->actingAs($owner)->get(route('dashboard'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'career',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('dashboard', ['pet' => $selected->id]))
        ->assertJsonPath('props.career.petId', $selected->id)
        ->assertJsonCount(1, 'props.career.results.entries')
        ->assertJsonPath('props.career.results.entries.0', [
            'id' => $entry->id, 'eventId' => $entry->game_event_id, 'kind' => 'competition',
            'discipline' => 'agility', 'frequency' => 'daily', 'divisionLabel' => 'Начинающие · Средние собаки',
            'division' => 'novice:medium', 'rank' => 1, 'prize' => 100,
            'completedAt' => $entry->completed_at->toIso8601String(), 'petName' => 'Имя на выступлении', 'eliminated' => false,
        ])
        ->assertJsonPath('props.career.titles.0.name', 'Победитель клуба: Аджилити')
        ->assertJsonPath('props.career.titles.0.count', 1)
        ->assertJsonPath('props.career.titles.0.eventId', $entry->game_event_id)
        ->assertJsonMissingPath('props.career.results.entries.0.userId')
        ->assertJsonMissingPath('props.career.results.entries.0.snapshot')
        ->assertJsonMissingPath('props.career.results.entries.0.operationToken')
        ->assertJsonMissingPath('props.pet');
});

test('career summaries count valid wins and podiums while excluding unfinished cancelled and NPC results', function () {
    $pet = Pet::factory()->create();
    $earlier = careerCompletedEntry($pet, completedAt: CarbonImmutable::now()->subDays(2));
    $latest = careerCompletedEntry($pet);
    $show = careerCompletedEntry($pet, 'conformation');
    $show->event->update(['frequency' => 'weekly']);
    careerCompletedEntry($pet, rank: 2);
    careerCompletedEntry($pet, rank: 1, eliminated: true);
    careerCompletedEntry($pet, 'progeny', rank: 3);
    foreach ([$earlier, $latest, $show] as $winner) {
        careerAward($winner);
    }
    foreach (['registered', 'frozen', 'cancelled', 'withdrawn'] as $status) {
        $unfinished = careerCompletedEntry($pet);
        $unfinished->update(['status' => $status]);
        careerAward($unfinished);
    }
    $cancelled = careerCompletedEntry($pet);
    $cancelled->event->update(['status' => 'cancelled']);
    careerAward($cancelled);
    $npcEvent = GameEvent::factory()->create(['status' => 'settled']);
    $npc = GameEventEntry::factory()->for($npcEvent, 'event')->create([
        'is_npc' => true, 'user_id' => null, 'pet_id' => null, 'fee' => 0, 'prize' => 0,
        'status' => 'completed', 'completed_at' => now(), 'rank' => 1, 'result' => ['eliminated' => false],
    ]);
    PetTitle::factory()->for($npc, 'entry')->for($pet)->create();

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    expect($career['summary'])->toBe([
        'competitionStarts' => 4, 'competitionWins' => 2, 'exhibitionStarts' => 2, 'exhibitionWins' => 1, 'podiums' => 5, 'cups' => 1,
    ]);
    expect($career['results']['entries'])->toHaveCount(6);
    expect($career['titles'])->toHaveCount(2);
    $agility = collect($career['titles'])->firstWhere('discipline', 'agility');
    expect($agility)->toBe([
        'name' => 'Club winner: Agility', 'discipline' => 'agility', 'frequency' => 'daily',
        'awardedAt' => $latest->completed_at->toIso8601String(),
        'code' => 'agility_daily_winner', 'classKey' => null, 'rank' => null, 'ageMonths' => null, 'breedId' => null,
        'kind' => 'legacy', 'context' => [], 'details' => [], 'scopeKey' => '', 'sourceCount' => 0, 'regulationVersion' => null,
        'count' => 2, 'eventId' => $latest->game_event_id,
    ]);
    expect(collect($career['results']['entries'])->firstWhere('id', $show->id)['kind'])->toBe('exhibition');
});

test('regulated career awards distinguish their category while combining repeated awards across the schedule', function (string $discipline, string $class, string $code, array $firstContext, array $otherContext, string $firstName, string $otherName) {
    $pet = Pet::factory()->create();
    $earlier = careerCompletedEntry($pet, $discipline, completedAt: CarbonImmutable::now()->subHours(3));
    $latest = careerCompletedEntry($pet, $discipline, completedAt: CarbonImmutable::now()->subHour());
    $latest->event->update(['frequency' => 'weekly']);
    $other = careerCompletedEntry($pet, $discipline, completedAt: CarbonImmutable::now()->subHours(2));
    foreach ([[$earlier, $firstContext], [$latest, $firstContext], [$other, $otherContext]] as [$entry, $context]) {
        PetTitle::factory()->for($entry, 'entry')->create([
            'pet_id' => $pet->id, 'discipline' => $discipline, 'frequency' => $entry->event->frequency,
            'code' => $code, 'class_key' => $class, 'kind' => 'qualification', 'details' => $context,
            'regulation_version' => '1', 'awarded_at' => $entry->completed_at,
        ]);
    }

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    expect($career['titles'])->toHaveCount(2);
    expect($career['titles'][0])->toMatchArray([
        'name' => $firstName, 'count' => 2, 'context' => $firstContext, 'eventId' => $latest->game_event_id,
    ]);
    expect($career['titles'][1])->toMatchArray([
        'name' => $otherName, 'count' => 1, 'context' => $otherContext, 'eventId' => $other->game_event_id,
    ]);
})->with([
    'nosework programs' => ['nosework', 'nw_0', 'NW_CLASS_QUALIFICATION', ['program' => 'pr1'], ['program' => 'pr2'],
        'Nosework class qualification · Nosework — class 0 · Program 1', 'Nosework class qualification · Nosework — class 0 · Program 2'],
    'nosework test odors' => ['nosework', 'nw_test', 'NW_ODOR_TEST', ['program' => 'pr1', 'odor' => 'birch'], ['program' => 'pr1', 'odor' => 'lavender'],
        'Odor recognition test passed · Odor recognition test · Program 1 · Birch tar', 'Odor recognition test passed · Odor recognition test · Program 1 · Lavender'],
    'agility heights' => ['agility', 'agility_1', 'AGILITY_QUALIFICATION', ['heightCategory' => 'S'], ['heightCategory' => 'I'],
        'Agility class qualification · Agility 1 · Height category S', 'Agility class qualification · Agility 1 · Height category I'],
    'agility and jumping' => ['agility', 'agility_1', 'AGILITY_QUALIFICATION', ['format' => 'agility'], ['format' => 'jumping'],
        'Agility class qualification · Agility 1 · Agility', 'Agility class qualification · Agility 1 · Jumping'],
    'canicross breed categories' => ['canicross', 'canicross_open', 'RACE_QUALIFICATION', ['race_category' => '3'], ['race_category' => '4'],
        'Canicross qualifying standard met · Canicross — open class · Breed category 3', 'Canicross qualifying standard met · Canicross — open class · Breed category 4'],
]);

test('completed unplaced qualifications remain in the career without becoming wins or podiums', function () {
    $pet = Pet::factory()->create();
    $entry = careerCompletedEntry($pet, 'nosework', rank: null);
    PetTitle::factory()->for($entry, 'entry')->create([
        'pet_id' => $pet->id, 'discipline' => 'nosework', 'frequency' => 'daily', 'code' => 'NW_ODOR_TEST',
        'class_key' => 'nw_test', 'kind' => 'qualification', 'details' => ['program' => 'pr1', 'odor' => 'birch'],
        'regulation_version' => '1', 'awarded_at' => $entry->completed_at,
    ]);

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    expect($career['results']['entries'])->toHaveCount(1);
    expect($career['results']['entries'][0]['rank'])->toBeNull();
    expect($career['titles'])->toHaveCount(1);
    expect($career['summary'])->toMatchArray(['competitionStarts' => 1, 'competitionWins' => 0, 'podiums' => 0, 'cups' => 0]);
});

test('an eliminated trigger keeps earned championships and qualifications while hiding finish dependent awards', function () {
    $pet = Pet::factory()->create();
    $entry = careerCompletedEntry($pet, eliminated: true);
    foreach ([
        ['SPORT_REGIONAL_CH', 'championship'], ['AGILITY_QUALIFICATION', 'qualification'],
        ['CACAg', 'certificate'], ['PLACE_1', 'placement'], ['CW', 'comparison'], ['agility_daily_winner', 'legacy'],
    ] as [$code, $kind]) {
        PetTitle::factory()->for($pet)->for($entry, 'entry')->create([
            'code' => $code, 'kind' => $kind, 'regulation_version' => '2', 'awarded_at' => $entry->completed_at,
        ]);
    }
    $unfinished = careerCompletedEntry($pet);
    $unfinished->update(['status' => 'frozen']);
    PetTitle::factory()->for($pet)->for($unfinished, 'entry')->create(['code' => 'AGILITY_START', 'kind' => 'qualification']);
    $unsettled = careerCompletedEntry($pet);
    $unsettled->event->update(['status' => 'frozen']);
    PetTitle::factory()->for($pet)->for($unsettled, 'entry')->create(['code' => 'SPORT_NATIONAL_CH', 'kind' => 'championship']);

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    expect(collect($career['titles'])->pluck('code')->sort()->values()->all())->toBe(['AGILITY_QUALIFICATION', 'SPORT_REGIONAL_CH']);
    expect($career['results']['entries'])->toHaveCount(1);
    expect($career['results']['entries'][0])->toMatchArray(['id' => $entry->id, 'eliminated' => true]);
    expect($career['summary'])->toMatchArray(['competitionStarts' => 1, 'competitionWins' => 0, 'podiums' => 0]);
});

test('all historical results remain accessible with stable forward and backward pagination', function () {
    $pet = Pet::factory()->create();
    $ids = [];
    for ($index = 0; $index < 25; $index++) {
        $ids[] = careerCompletedEntry($pet, rank: 4, completedAt: CarbonImmutable::now()->subDays(40))->id;
    }
    $ids = array_reverse($ids);
    $initial = $this->actingAs($pet->user)->get(route('dashboard'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'career',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ]);

    $first = $this->get(route('dashboard'))->assertJsonCount(20, 'props.career.results.entries')
        ->assertJsonPath('props.career.summary.competitionStarts', 25)->assertJsonPath('props.career.results.previousCursor', null);
    $next = $first->json('props.career.results.nextCursor');
    $second = $this->get(route('dashboard', ['pet' => $pet->id, 'career_cursor' => $next]))
        ->assertJsonCount(5, 'props.career.results.entries')->assertJsonPath('props.career.results.nextCursor', null);
    $back = $this->get(route('dashboard', ['pet' => $pet->id, 'career_cursor' => $second->json('props.career.results.previousCursor')]));

    expect(array_column($first->json('props.career.results.entries'), 'id'))->toBe(array_slice($ids, 0, 20));
    expect(array_column($second->json('props.career.results.entries'), 'id'))->toBe(array_slice($ids, 20));
    expect(array_column($back->json('props.career.results.entries'), 'id'))->toBe(array_slice($ids, 0, 20));
});

test('another players dog is unavailable through both the career query and dashboard selection', function () {
    $viewer = User::factory()->create();
    $pet = Pet::factory()->create();
    careerAward(careerCompletedEntry($pet));

    expect(fn () => app(GetPetCareer::class)->handle($viewer, $pet->id, 'ru'))->toThrow(ModelNotFoundException::class);
    $initial = $this->actingAs($viewer)->get(route('dashboard'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'career',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('dashboard', ['pet' => $pet->id]))->assertNotFound();
});

test('regular dashboard polling and the initial page do not load the optional career history', function () {
    $pet = Pet::factory()->create();
    careerAward(careerCompletedEntry($pet));
    DB::enableQueryLog();

    $initial = $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->missing('career'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'pet,care,skills',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('dashboard'))->assertJsonMissingPath('props.career');

    $queries = implode(' ', array_column(DB::getQueryLog(), 'query'));
    DB::disableQueryLog();
    expect($queries)->not->toContain('pet_titles', 'historical_name', 'awards_count', 'AS podiums');
});

test('career is null when the player has no dog', function () {
    $owner = User::factory()->create();

    $initial = $this->actingAs($owner)->get(route('dashboard'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'career',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('dashboard'))->assertJsonPath('props.career', null);
});

test('career rejects malformed cursor data without exposing another page', function (string $cursor) {
    $pet = Pet::factory()->create();

    $initial = $this->actingAs($pet->user)->get(route('dashboard'));
    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'career', 'Accept' => 'application/json',
        'X-Inertia-Version' => $initial->viewData('page')['version'],
    ])->get(route('dashboard', ['career_cursor' => $cursor]))->assertUnprocessable();
})->with([
    'not encoded' => ['invalid'],
    'missing ordering fields' => [(new Cursor(['id' => 1]))->encode()],
    'wrong timestamp' => [(new Cursor(['id' => 1, 'completed_at' => 'invalid']))->encode()],
    'array direction' => [base64_encode(json_encode(['id' => 1, 'completed_at' => '2026-10-04 12:00:00', '_pointsToNextItems' => []], JSON_THROW_ON_ERROR))],
]);
