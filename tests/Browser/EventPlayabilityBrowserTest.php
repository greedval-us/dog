<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Enums\DogSize;
use App\Modules\Pets\Services\GameEventProcessor;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

/**
 * @param  list<string>  $stages
 * @param  array<string,mixed>  $regulation
 */
function playabilityBrowserEvent(string $discipline, array $stages, array $regulation): GameEvent
{
    return GameEvent::factory()->create([
        'discipline' => $discipline,
        'rules' => [
            'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'stages' => $stages,
            'energy_cost' => 10, 'field_size' => 8,
            'regulation' => [
                'version' => 2, 'system' => 'rkf', 'country' => 'RU', 'region' => 'moscow',
                'judgeId' => 'playability-judge', 'judgeName' => 'Judge One',
                ...$regulation,
            ],
        ],
    ]);
}

test('a career recommendation registers the second dog in its overlapping open class and retains the saved entry over new hints', function (string $locale, int $width, string $colorScheme) {
    $owner = User::factory()->create(['locale' => $locale, 'coins' => 500]);
    $breed = Dog::factory()->create([
        'breed' => 'german_shepherd', 'size' => DogSize::Large,
        'name' => ['en' => 'German Shepherd', 'ru' => 'Немецкая овчарка'],
    ]);
    $firstDog = Pet::factory()->for($owner)->for($breed, 'dog')->create([
        'name' => 'First dog', 'born_at' => now()->subDays(16),
    ]);
    $selectedDog = Pet::factory()->for($owner)->for($breed, 'dog')->create([
        'name' => 'Career dog', 'born_at' => now()->subDays(16),
        'event_profile' => ['registered' => true, 'pedigree_verified' => true],
    ]);
    $event = playabilityBrowserEvent('conformation', ['type', 'structure', 'movement'], [
        'rank' => 'cac_cf', 'format' => 'conformation',
        'sourceUrl' => config('event_regulations.sources.rkf_shows_2026'),
    ]);
    $path = route('game-events.show', $event, absolute: false);
    $recommendation = route('game-events.show', [
        'gameEvent' => $event, 'pet' => $selectedDog->id, 'entry_option' => 'open',
    ], absolute: false);
    $goal = '.pet-title-goal[aria-label="'.($locale === 'ru' ? 'Чемпион России по красоте' : 'Russian Beauty Champion').'"]';
    $this->actingAs($owner->refresh());
    $page = visit(route('dashboard', ['pet' => $selectedDog->id], absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->assertSeeIn('.pet-hero h1', 'Career dog')
        ->click('.pet-tab:has-text("'.($locale === 'ru' ? 'Титулы' : 'Titles').'")')
        ->assertVisible('.pet-title-progress')
        ->click('.pet-title-more > summary')
        ->select($goal.' select', 'standard_interval')
        ->assertAttribute($goal.' .pet-title-events a[href="'.$recommendation.'"]', 'href', $recommendation)
        ->click($goal.' .pet-title-events a[href="'.$recommendation.'"]')
        ->assertPathIs($path)
        ->assertSelected('#event-dog', $selectedDog->id)
        ->assertSelected('#event-entry-option', 'open')
        ->assertEnabled('#event-entry-option option[value="junior"]')
        ->assertEnabled('#event-entry-option option[value="intermediate"]')
        ->assertDisabled('#event-entry-option option[value="working"]')
        ->select('#event-entry-option', 'intermediate')
        ->assertSelected('#event-entry-option', 'intermediate')
        ->select('#event-entry-option', 'open')
        ->click('input[name="event-stage-0"][value="careful"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'Сохранить план' : 'Save performance plan')
        ->navigate(route('game-events.show', [
            'gameEvent' => $event, 'pet' => $firstDog->id, 'entry_option' => 'junior',
        ], absolute: false))
        ->assertSelected('#event-dog', $selectedDog->id)
        ->assertSelected('#event-entry-option', 'open')
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    $page->script('document.querySelector(".event-classification").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'event-playability-career-'.$locale.'-'.$width);

    $entry = GameEventEntry::query()->where('game_event_id', $event->id)->where('user_id', $owner->id)->sole();
    expect($entry->pet_id)->toBe($selectedDog->id);
    expect($entry->plan)->toEqual(['stages' => ['careful', 'balanced', 'balanced'], 'exhibition_class' => 'open']);
    expect($entry->division)->toBe('open:breed-'.$breed->id.':male');
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('game_event_entries', 1);
})->with([
    'English desktop light' => ['en', 1440, 'light'],
    'Russian mobile dark' => ['ru', 390, 'dark'],
]);

test('an odor test saves one decision and unlocks its program after the real scheduled qualification', function () {
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Odor dog', 'born_at' => now()->subDays(8)]);
    $event = playabilityBrowserEvent('nosework', ['odor_test'], [
        'rank' => 'qualifying', 'format' => 'odor_test',
        'sourceUrl' => config('event_regulations.sources.rkf_nosework_2026_09'),
    ]);
    $event->update(['seed' => 'playability-odor-17']);
    $this->actingAs($owner->refresh());
    $page = visit(route('game-events.show', [
        'gameEvent' => $event, 'pet' => $pet->id, 'entry_option' => 'nw_test:pr2:clove',
    ], absolute: false), [
        'viewport' => ['width' => 390, 'height' => 900], 'colorScheme' => 'light',
    ]);

    $page->assertSelected('#event-entry-option', 'nw_test:pr2:clove')
        ->assertSeeIn('.event-overview', 'Odor tests record a qualification without a placement or coin prize.')
        ->assertDontSee('Place 1:')
        ->assertMissing('.event-overview .event-prizes')
        ->assertCount('fieldset.event-stage', 1)
        ->select('#event-entry-option', 'nw_test:pr1:birch')
        ->click('input[name="event-stage-0"][value="careful"]');
    $page->script('Object.defineProperty(crypto, "randomUUID", { value: () => "00000000-0000-4000-8000-000000000007" })');
    $page->click('.event-form-actions button[type="submit"]')
        ->assertSee('Save performance plan')
        ->refresh()
        ->assertSelected('#event-entry-option', 'nw_test:pr1:birch')
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->assertCount('fieldset.event-stage', 1)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    $page->script('document.querySelector(".event-classification").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'event-playability-odor-test-mobile');

    $entry = GameEventEntry::query()->where('game_event_id', $event->id)->where('user_id', $owner->id)->sole();
    expect($entry->plan)->toEqual(['stages' => ['careful'], 'sport_class' => 'nw_test', 'program' => 'pr1', 'test_type' => 'birch']);
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('currency_transactions', 1);

    $this->travelTo($event->ends_at);
    app(GameEventProcessor::class)->processDue();
    expect($entry->fresh()->status)->toBe('completed');
    expect($entry->fresh()->rank)->toBeNull();
    expect($entry->fresh()->prize)->toBe(0);
    expect($entry->titles()->pluck('code')->all())->toBe(['NW_ODOR_TEST']);
    expect($pet->sportRecords()->where('discipline', 'nosework')->sole()->regulatory_progress['nosework_tests']['pr1'])->toBe(['birch']);

    $page->refresh()
        ->assertSeeIn('.event-own-result', 'Qualifications')
        ->assertNoJavaScriptErrors();
    $search = playabilityBrowserEvent('nosework', ['interior', 'exterior', 'objects', 'transport'], [
        'rank' => 'qualifying', 'format' => 'nosework',
    ]);
    $search->update(['closes_at' => now()->addMinutes(165), 'starts_at' => now()->addHours(3), 'ends_at' => now()->addMinutes(190)]);
    $page->navigate(route('game-events.show', $search, absolute: false))
        ->assertEnabled('#event-entry-option option[value="nw_0:pr1"]')
        ->assertDisabled('#event-entry-option option[value="nw_0:pr2"]')
        ->assertNoJavaScriptErrors();
});
