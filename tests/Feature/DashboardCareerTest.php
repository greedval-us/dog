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

function careerCompletedEntry(Pet $pet, string $discipline = 'agility', int $rank = 1, bool $eliminated = false, ?User $previousOwner = null, ?CarbonImmutable $completedAt = null): GameEventEntry
{
    $completedAt ??= CarbonImmutable::now()->subHour();
    $event = GameEvent::factory()->create([
        'status' => 'settled', 'discipline' => $discipline,
        'registration_opens_at' => $completedAt->subDay(), 'closes_at' => $completedAt->subMinutes(25),
        'starts_at' => $completedAt->subMinutes(10)->subSeconds(GameEvent::query()->count()), 'ends_at' => $completedAt, 'settled_at' => $completedAt,
    ]);

    return GameEventEntry::factory()->for($event, 'event')->for($previousOwner ?? $pet->user)->for($pet)->create([
        'status' => 'completed', 'completed_at' => $completedAt, 'rank' => $rank,
        'prize' => $eliminated ? 0 : 100, 'result' => ['eliminated' => $eliminated, 'score' => 97],
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
        'awardedAt' => $latest->completed_at->toIso8601String(), 'count' => 2, 'eventId' => $latest->game_event_id,
    ]);
    expect(collect($career['results']['entries'])->firstWhere('id', $show->id)['kind'])->toBe('exhibition');
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
