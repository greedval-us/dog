<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Enums\DogSize;
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
function regulatedBrowserEvent(string $discipline, array $stages, array $regulation = []): GameEvent
{
    return GameEvent::factory()->create([
        'discipline' => $discipline,
        'rules' => [
            'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'stages' => $stages, 'energy_cost' => 10, 'field_size' => 8,
            'regulation' => array_replace([
                'version' => 1, 'system' => 'rkf', 'rank' => $discipline === 'conformation' ? 'cac_rkf' : 'qualifying',
                'format' => $discipline, 'country' => 'RU', 'region' => 'Moscow', 'judgeId' => 'browser-judge', 'judgeName' => 'Judge One',
                'sourceUrl' => config('event_regulations.sources.'.($discipline === 'conformation' ? 'rkf_shows_2026' : 'rkf_nosework_2026_09')),
            ], $regulation),
        ],
    ]);
}

test('a v2 Eurasia show keeps its edition and sources while a young dog saves an eligible class on Russian mobile', function () {
    $owner = User::factory()->create(['locale' => 'ru', 'coins' => 500]);
    $breed = Dog::factory()->create([
        'breed' => 'german_shepherd', 'size' => DogSize::Large,
        'name' => ['en' => 'German Shepherd', 'ru' => 'Немецкая овчарка'],
    ]);
    $pet = Pet::factory()->for($owner)->for($breed, 'dog')->create(['name' => 'Руна', 'born_at' => now()->subDays(16)]);
    $showSource = config('special_exhibitions.sources.eurasia');
    $titleSource = config('event_regulations.sources.rkf_championships');
    $event = regulatedBrowserEvent('conformation', ['type', 'structure', 'movement'], [
        'version' => 2, 'system' => 'rkf', 'rank' => 'cac_rkf_special', 'specialShow' => 'eurasia', 'showLeg' => 'president',
        'showYear' => 2050, 'showEdition' => '2050-05', 'region' => 'moscow',
        'sourceUrl' => $showSource,
        'sources' => [['key' => 'eurasia', 'url' => $showSource], ['key' => 'rkf_championships', 'url' => $titleSource]],
    ]);
    $this->actingAs($owner->refresh());
    $page = visit(route('game-events.show', $event, absolute: false), [
        'viewport' => ['width' => 390, 'height' => 900], 'colorScheme' => 'dark',
    ]);

    $page->assertSee('Немецкая овчарка')
        ->assertSeeIn('.event-overview .event-regulation p.event-note', 'Евразия')
        ->assertSeeIn('.event-overview .event-regulation', 'чемпион РКФ с особым статусом')
        ->assertSeeIn('.event-overview .event-regulation', 'Игровой год: 2050')
        ->assertSeeIn('.event-overview .event-regulation', 'Игровой выпуск: 2050-05')
        ->assertCount('.event-overview .event-regulation a.text-link', 2)
        ->assertAttribute('.event-overview .event-regulation a[href="'.$showSource.'"]', 'target', '_blank')
        ->assertAttribute('.event-overview .event-regulation a[href="'.$titleSource.'"]', 'rel', 'noopener noreferrer')
        ->assertEnabled('#event-entry-option option[value="junior"]')
        ->assertEnabled('#event-entry-option option[value="intermediate"]')
        ->assertEnabled('#event-entry-option option[value="open"]')
        ->assertDisabled('#event-entry-option option[value="working"]')
        ->assertDisabled('#event-entry-option option[value="champion"]')
        ->assertSee('Для рабочего класса нужен рабочий сертификат.')
        ->select('#event-entry-option', 'intermediate')
        ->click('input[name="event-stage-0"][value="bold"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee('Сохранить план')
        ->refresh()
        ->assertSelected('#event-entry-option', 'intermediate')
        ->assertChecked('input[name="event-stage-0"][value="bold"]')
        ->assertSee('Промежуточный класс')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $page->script('document.querySelector(".event-overview .event-regulation").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'regulated-eurasia-summary-mobile');
    $page->script('document.querySelector(".event-classification").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'regulated-show-classes-mobile');

    $entry = GameEventEntry::query()->where('game_event_id', $event->id)->where('user_id', $owner->id)->sole();
    expect($entry->pet_id)->toBe($pet->id);
    expect($entry->division)->toBe('intermediate:breed-'.$breed->id.':male');
    expect($entry->plan)->toMatchArray(['stages' => ['bold', 'balanced', 'balanced'], 'exhibition_class' => 'intermediate']);
});

test('a combined agility plan saves and edits all six stages after a reload', function (string $locale, int $width, string $colorScheme) {
    $owner = User::factory()->create(['locale' => $locale, 'coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Milo', 'born_at' => now()->subDays(30)]);
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'agility', 'regulatory_progress' => ['agility_level' => 2, 'agility_available_levels' => [2, 3]],
    ]);
    $event = regulatedBrowserEvent('agility', [
        'agility_opening', 'agility_technical', 'agility_finish', 'jumping_opening', 'jumping_technical', 'jumping_finish',
    ], [
        'version' => 2, 'rank' => 'certificate', 'format' => 'agility_combined', 'region' => 'moscow',
        'sourceUrl' => config('event_regulations.sources.rkf_agility_2026'),
    ]);
    $this->actingAs($owner->refresh());
    $page = visit(route('game-events.show', $event, absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->assertSeeIn('.event-overview .event-regulation', $locale === 'ru' ? 'Двоеборье: аджилити и джампинг' : 'Agility and jumping combined')
        ->assertCount('fieldset.event-stage', 6)
        ->assertSeeIn('fieldset.event-stage:nth-of-type(1) legend', $locale === 'ru' ? 'Аджилити — начало трассы' : 'Agility — course opening')
        ->assertSeeIn('fieldset.event-stage:nth-of-type(6) legend', $locale === 'ru' ? 'Джампинг — финиш' : 'Jumping — finish')
        ->assertSelected('#event-entry-option', 'agility_2')
        ->select('#event-entry-option', 'agility_3')
        ->press($locale === 'ru' ? 'Беречь силы к финишу' : 'Save for the finish')
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->assertChecked('input[name="event-stage-1"][value="careful"]')
        ->assertChecked('input[name="event-stage-2"][value="balanced"]')
        ->assertChecked('input[name="event-stage-3"][value="balanced"]')
        ->assertChecked('input[name="event-stage-4"][value="bold"]')
        ->assertChecked('input[name="event-stage-5"][value="bold"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'Сохранить план' : 'Save performance plan')
        ->refresh()
        ->assertSelected('#event-entry-option', 'agility_3')
        ->assertCount('fieldset.event-stage', 6)
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->assertChecked('input[name="event-stage-5"][value="bold"]')
        ->click('input[name="event-stage-5"][value="careful"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee($locale === 'ru' ? 'План выступления сохранён.' : 'Your performance plan has been saved.')
        ->refresh()
        ->assertChecked('input[name="event-stage-0"][value="careful"]')
        ->assertChecked('input[name="event-stage-5"][value="careful"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    $page->script('document.querySelector("fieldset.event-stage:last-of-type").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'regulated-agility-combined-'.$locale.'-'.$width);

    $entry = GameEventEntry::query()->where('game_event_id', $event->id)->where('user_id', $owner->id)->sole();
    expect($entry->pet_id)->toBe($pet->id);
    expect($entry->plan)->toMatchArray(['sport_class' => 'agility_3', 'stages' => ['careful', 'careful', 'balanced', 'balanced', 'bold', 'careful']]);
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('currency_transactions', 1);
})->with([
    'English desktop' => ['en', 1440, 'light'],
    'Russian mobile' => ['ru', 390, 'dark'],
]);

test('nosework registration retains a selected program and a four stage preset after reload', function () {
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Milo']);
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'nosework',
        'regulatory_progress' => [
            'nosework_levels' => ['pr1' => 0, 'pr2' => 0],
            'nosework_tests' => ['pr1' => ['birch', 'lavender', 'anise'], 'pr2' => ['cinnamon', 'orange', 'clove']],
        ],
    ]);
    $event = regulatedBrowserEvent('nosework', ['interior', 'exterior', 'objects', 'transport']);
    $this->actingAs($owner->refresh());
    $page = visit(route('game-events.show', $event, absolute: false), [
        'viewport' => ['width' => 1440, 'height' => 900], 'colorScheme' => 'light',
    ]);

    $page->assertSelected('#event-entry-option', 'nw_0:pr1')
        ->assertCount('fieldset.event-stage', 4)
        ->assertEnabled('#event-entry-option option[value="nw_0:pr2"]')
        ->assertDisabled('#event-entry-option option[value="nw_1:pr2"]')
        ->select('#event-entry-option', 'nw_0:pr2')
        ->press('Save for the finish')
        ->assertChecked('input[name="event-stage-3"][value="bold"]')
        ->click('.event-form-actions button[type="submit"]')
        ->assertSee('Save performance plan')
        ->refresh()
        ->assertSelected('#event-entry-option', 'nw_0:pr2')
        ->assertCount('fieldset.event-stage', 4)
        ->assertChecked('input[name="event-stage-3"][value="bold"]')
        ->assertSeeIn('.event-classification > .event-note', 'Program 2')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $page->script('document.querySelector(".event-classification").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'regulated-nosework-program-desktop');

    $entry = GameEventEntry::query()->where('game_event_id', $event->id)->where('user_id', $owner->id)->sole();
    expect($entry->division)->toBe('nw_0:pr2');
    expect($entry->plan)->toMatchArray(['stages' => ['careful', 'balanced', 'balanced', 'bold'], 'sport_class' => 'nw_0', 'program' => 'pr2']);
});

test('dog preparation and equipment update while heat navigation preserves an unsaved plan', function (string $locale, int $width, string $colorScheme) {
    $owner = User::factory()->create(['locale' => $locale, 'coins' => 500]);
    $firstDog = Pet::factory()->for($owner)->create(['name' => 'Milo']);
    $selectedDog = Pet::factory()->for($owner)->create([
        'name' => 'Runa', 'hydration' => 25, 'hydration_max' => 100,
        'health' => 100, 'energy' => 100, 'satiety' => 100,
    ]);
    $event = GameEvent::factory()->create();
    foreach (['novice:medium', 'novice:medium:heat-2'] as $division) {
        GameEventEntry::factory()->for($event, 'event')->create(['division' => $division]);
    }
    foreach ([['Training harness', 'body', 0.08], ['Focus aid', 'preparation', 0.07]] as [$name, $slot, $precision]) {
        InventoryItem::factory()->for($owner)->create([
            'name' => ['en' => $name, 'ru' => $name], 'remaining_uses' => 3,
            'characteristics' => ['competition' => [
                'slot' => $slot, 'phase' => 'preparation', 'disciplines' => ['agility'], 'sizes' => ['medium'],
                'modifiers' => ['precision' => $precision],
                'description' => ['en' => 'Helps the dog stay precise.', 'ru' => 'Помогает собаке двигаться точнее.'],
            ]],
        ]);
    }
    $this->actingAs($owner->refresh());
    $totals = '[aria-label="'.($locale === 'en' ? 'Combined equipment effect' : 'Общий эффект амуниции').'"]';
    $refresh = $locale === 'en' ? 'Refresh preparation' : 'Обновить готовность';
    $careEffect = $locale === 'en' ? '× 0.96' : '× 0,96';
    $riskEffect = $locale === 'en' ? 'Base error risk: -15 pp' : 'Базовый риск ошибки: -15 п. п.';
    $firstStage = 'input[name="event-stage-0"][value="bold"]';
    $secondHeat = '.event-division-tabs a[href*="heat-2"]';
    $page = visit(route('game-events.show', $event, absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->assertSelected('#event-dog', $firstDog->id)
        ->select('#event-dog', $selectedDog->id)
        ->assertSeeIn('.event-readiness', '25%')
        ->assertSeeIn('.event-readiness', $careEffect)
        ->click($firstStage)
        ->click('button.event-gear:has-text("Training harness")')
        ->click('button.event-gear:has-text("Focus aid")')
        ->assertSeeIn($totals, $riskEffect)
        ->click($secondHeat)
        ->assertAttribute($secondHeat, 'aria-current', 'page')
        ->assertSelected('#event-dog', $selectedDog->id)
        ->assertChecked($firstStage)
        ->assertSeeIn($totals, $riskEffect);
    $selectedDog->update(['hydration' => 100]);
    $page->press($refresh)
        ->assertSeeIn('.event-readiness-metrics', '× 1')
        ->assertSelected('#event-dog', $selectedDog->id)
        ->assertChecked($firstStage)
        ->assertSeeIn($totals, $riskEffect);
    $page->script('window.scrollBy({ top: document.querySelector(".event-readiness").getBoundingClientRect().top - 150, behavior: "instant" })');
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'events-preparation-'.$locale.'-'.$width);
    $page->script('document.querySelector(".event-gear-heading").scrollIntoView({ block: "center", behavior: "instant" })');
    $page->keys('button.event-gear:first-child', 'Escape')
        ->screenshot(fullPage: false, filename: 'events-equipment-'.$locale.'-'.$width)
        ->assertNoJavaScriptErrors();

    expect(GameEventEntry::query()->where('user_id', $owner->id)->exists())->toBeFalse();
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('item_usages', 0);
})->with([
    'English desktop' => ['en', 1440, 'light'],
    'Russian mobile' => ['ru', 390, 'dark'],
]);

test('a player can open the recorded factors for each stage with the keyboard', function (string $locale, int $width, string $colorScheme) {
    $owner = User::factory()->create(['locale' => $locale]);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Milo']);
    $event = GameEvent::factory()->create([
        'status' => 'settled', 'settled_at' => now(),
        'registration_opens_at' => now()->subDay(), 'closes_at' => now()->subMinutes(30),
        'starts_at' => now()->subMinutes(15), 'ends_at' => now()->subMinutes(5),
    ]);
    $plan = ['stages' => ['careful', 'balanced', 'bold']];
    $snapshot = [
        'version' => 1, 'name' => $pet->name,
        'stats' => array_fill_keys(['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'], 80),
        'states' => array_fill_keys(['health', 'energy', 'satiety', 'hydration', 'cleanliness', 'mood', 'bond'], 100),
        'skills' => [], 'modifiers' => ['precision' => 0.05], 'career_experience' => 0,
    ];
    $result = app(GameEventSimulator::class)->simulate('agility', $snapshot, $plan, $event->rules, array_fill(0, 6, 0.9));
    GameEventEntry::factory()->for($event, 'event')->for($pet)->for($owner)->create([
        'status' => 'completed', 'rank' => 1, 'prize' => 100, 'snapshot' => $snapshot,
        'result' => $result, 'plan' => $plan, 'completed_at' => now(),
    ]);
    $this->actingAs($owner->refresh());
    $quality = $locale === 'en' ? 'Stage quality' : 'Качество этапа';
    $risk = $locale === 'en' ? 'Error risk' : 'Риск ошибки';
    $page = visit(route('game-events.show', $event, absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);

    $page->assertSeeIn('.event-own-result', $pet->name);
    foreach (range(1, 3) as $stage) {
        $factor = '.event-replay-stages > li:nth-child('.$stage.') .event-stage-factors';
        $page->assertAttributeMissing($factor, 'open')
            ->keys($factor.' summary', 'Enter')
            ->assertVisible($factor.' dl')
            ->assertSeeIn($factor.' dl', $quality)
            ->assertSeeIn($factor.' dl', $risk);
    }
    $page->script('window.scrollBy({ top: document.querySelector(".event-replay").getBoundingClientRect().top - 150, behavior: "instant" })');
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'events-results-'.$locale.'-'.$width)
        ->assertNoJavaScriptErrors();
})->with([
    'English desktop' => ['en', 1440, 'light'],
    'Russian mobile' => ['ru', 390, 'dark'],
]);
