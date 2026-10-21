<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventCalendar;
use App\Modules\Pets\Enums\DogSize;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

/** @param array<string,mixed> $regulation */
function titleProgressBrowserShow(array $regulation): GameEvent
{
    return GameEvent::factory()->create([
        'discipline' => 'conformation',
        'rules' => [
            'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40],
            'stages' => ['type', 'structure', 'movement'], 'energy_cost' => 10, 'field_size' => 8,
            'regulation' => $regulation,
        ],
    ]);
}

test('title progress supports choosing a route and opening its real upcoming show', function (string $locale, int $width, string $colorScheme, string $goalName, string $tabName) {
    $owner = User::factory()->create(['locale' => $locale, 'coins' => 5000]);
    $breed = Dog::factory()->create([
        'breed' => 'german_shepherd', 'size' => DogSize::Large,
        'name' => ['en' => 'German Shepherd', 'ru' => 'Немецкая овчарка'],
    ]);
    $pet = Pet::factory()->for($owner)->for($breed, 'dog')->create([
        'name' => 'Milo', 'born_at' => now()->subDays(30),
        'event_profile' => ['registered' => true, 'pedigree_verified' => true],
    ]);
    $edition = app(GameEventCalendar::class)->editionAt(CarbonImmutable::now());
    $showSource = config('special_exhibitions.sources.eurasia');
    $titleSource = config('event_regulations.sources.rkf_championships');
    $regulation = [
        'version' => 2, 'system' => 'rkf', 'country' => 'RU', 'region' => 'moscow',
        'rank' => 'cac_cf', 'format' => 'conformation',
        'sourceUrl' => config('event_regulations.sources.rkf_shows_2026'),
    ];
    $special = [
        'rank' => 'cac_rkf_special', 'specialShow' => 'eurasia', 'showLeg' => 'president',
        'showYear' => $edition['showYear'], 'showEdition' => $edition['showEdition'],
        'sourceUrl' => $showSource,
        'sources' => [['key' => 'eurasia', 'url' => $showSource], ['key' => 'rkf_championships', 'url' => $titleSource]],
    ];
    $history = [];
    foreach ([now()->subDays(2), now()->subDay(), now()->subHours(4)] as $index => $awardedAt) {
        $metadata = [...$regulation, ...($index === 2 ? $special : []),
            'judgeId' => 'progress-judge-'.$index, 'judgeName' => 'Judge '.($index + 1)];
        $event = titleProgressBrowserShow($metadata);
        $event->update([
            'status' => 'settled', 'registration_opens_at' => $awardedAt->copy()->subDay(),
            'closes_at' => $awardedAt->copy()->subMinutes(15), 'starts_at' => $awardedAt->copy()->subMinutes(10),
            'ends_at' => $awardedAt, 'settled_at' => $awardedAt,
        ]);
        $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create([
            'division' => 'open:breed-'.$breed->id.':male', 'status' => 'completed', 'rank' => 1, 'prize' => 0,
            'snapshot' => ['version' => 1, 'name' => 'Milo', 'age_months' => 30, 'sex' => 'male'],
            'plan' => ['stages' => ['balanced', 'balanced', 'balanced'], 'exhibition_class' => 'open'],
            'result' => ['eliminated' => false, 'qualified' => true, 'grade' => 'excellent'], 'completed_at' => $awardedAt,
        ]);
        PetTitle::factory()->for($pet)->create([
            'game_event_entry_id' => $entry->id, 'game_event_id' => $event->id,
            'discipline' => 'conformation', 'frequency' => 'daily', 'code' => 'CAC', 'kind' => 'certificate',
            'regulation_version' => '2', 'class_key' => 'open', 'sex' => 'male', 'awarded_at' => $awardedAt,
            'details' => [...$metadata, 'national_certificate_weight' => $index === 2 ? 2 : 1],
        ]);
        $history[] = $event;
    }
    $upcoming = titleProgressBrowserShow([...$regulation, ...$special,
        'showLeg' => 'cis', 'judgeId' => 'progress-next-judge', 'judgeName' => 'Judge Four']);
    $upcomingPath = route('game-events.show', $upcoming, absolute: false);
    $upcomingRecommendation = route('game-events.show', [
        'gameEvent' => $upcoming, 'pet' => $pet->id, 'entry_option' => 'open',
    ], absolute: false);
    $historyPath = route('game-events.show', $history[2], absolute: false);
    $goal = '.pet-title-goal[aria-label="'.$goalName.'"]';
    $select = $goal.' select';
    $this->actingAs($owner->refresh());
    $page = visit(route('dashboard', ['pet' => $pet->id], absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->assertSeeIn('.pet-hero h1', 'Milo')
        ->click('.pet-tab:has-text("'.$tabName.'")')
        ->assertVisible('.pet-title-progress')
        ->click('.pet-title-more > summary')
        ->assertVisible($goal)
        ->keys($select, 'Home')
        ->assertScript('document.querySelector('.json_encode($select).').selectedIndex', 0)
        ->keys($select, 'ArrowDown')
        ->assertScript('document.querySelector('.json_encode($select).').selectedIndex', 1)
        ->select($select, 'two_doubled_certificates')
        ->assertSelected($select, 'two_doubled_certificates')
        ->assertSeeIn($goal.' .pet-title-requirements li:has(strong:has-text("CAC"))', '1 / 2')
        ->keys($goal.' .pet-title-documents summary', 'Enter')
        ->assertVisible($goal.' .pet-title-documents ul')
        ->assertAttribute($goal.' .pet-title-documents a[href="'.$historyPath.'"]', 'href', $historyPath)
        ->assertSeeIn($goal.' .pet-title-events li:has(a[href="'.$upcomingRecommendation.'"]) .pet-title-event-contribution', 'CAC')
        ->assertAttribute($goal.' .pet-title-events a[href="'.$upcomingRecommendation.'"]', 'href', $upcomingRecommendation)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->script('window.scrollBy({ top: document.querySelector('.json_encode($goal).').getBoundingClientRect().top - 150, behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'title-progress-goal-'.$locale.'-'.$width);
    $page->script('document.querySelector('.json_encode($goal.' .pet-title-event-section').').scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'title-progress-events-'.$locale.'-'.$width)
        ->assertNoJavaScriptErrors()
        ->click($goal.' .pet-title-events a[href="'.$upcomingRecommendation.'"]')
        ->assertPathIs($upcomingPath)
        ->assertSelected('#event-dog', $pet->id)
        ->assertSeeIn('.event-overview .event-regulation p.event-note', $locale === 'ru' ? 'Евразия' : 'Eurasia')
        ->assertAttribute('.event-overview .event-regulation a[href="'.$showSource.'"]', 'href', $showSource)
        ->assertAttribute('.event-overview .event-regulation a[href="'.$titleSource.'"]', 'rel', 'noopener noreferrer')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($owner->fresh()->coins)->toBe(5000);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('game_event_entries', 3);
})->with([
    'Russian mobile dark' => ['ru', 390, 'dark', 'Чемпион России по красоте', 'Титулы'],
    'English desktop light' => ['en', 1440, 'light', 'Russian Beauty Champion', 'Titles'],
]);
