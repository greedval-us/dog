<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventSimulator;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
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
