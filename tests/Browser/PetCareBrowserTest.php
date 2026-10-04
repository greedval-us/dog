<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

test('a missing supply blocks feeding while fresh water remains available on mobile', function () {
    $owner = User::factory()->create(['locale' => 'en']);
    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Milo', 'satiety' => 20, 'satiety_max' => 100,
        'hydration' => 20, 'hydration_max' => 100,
    ]);
    $this->actingAs($owner->refresh());

    $page = visit(route('dashboard', ['pet' => $pet->id], absolute: false))
        ->on()->mobile();
    $page->click('button[aria-label="Wellbeing"]')
        ->assertSeeIn('.help-hint-content[aria-label="Wellbeing"]', 'Below 25% — needs care.')
        ->keys('button[aria-label="Wellbeing"]', 'Escape')
        ->assertMissing('.help-hint-content[aria-label="Wellbeing"]');
    $page->keys('button[aria-label="Wellbeing"]', 'Tab')
        ->keys('button:focus', 'Shift+Tab')
        ->assertSeeIn('.help-hint-content[aria-label="Wellbeing"]', 'Below 25% — needs care.')
        ->keys('button[aria-label="Wellbeing"]', 'Tab')
        ->assertMissing('.help-hint-content[aria-label="Wellbeing"]')
        ->keys('button:focus', 'Escape')
        ->assertMissing('.help-hint-content');
    $page->click('#pet-care button[aria-label="Feed"]')
        ->assertSeeIn('[role="dialog"]', 'No suitable item in your inventory.')
        ->assertDisabled('[role="dialog"] button[type="submit"]')
        ->assertSeeLink('Go to shop')
        ->click('[role="dialog"] input[value="water"]')
        ->assertSeeIn('[role="dialog"]', 'No items needed')
        ->assertButtonEnabled('Start · 15 sec')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('pet_care_actions', 0);
    $this->assertDatabaseCount('item_usages', 0);
});

test('feeding spends the chosen supply once and the active receipt survives a page reload', function () {
    $owner = User::factory()->create(['locale' => 'en']);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Milo', 'satiety' => 20, 'satiety_max' => 100]);
    $food = Item::factory()->for(ItemCategory::factory()->state(['code' => 'food']), 'category')->create();
    $first = InventoryItem::factory()->for($owner)->for($food)->create([
        'name' => ['en' => 'First food', 'ru' => 'Первый корм'], 'remaining_uses' => 3,
    ]);
    $selected = InventoryItem::factory()->for($owner)->for($food)->create([
        'name' => ['en' => 'Selected food', 'ru' => 'Выбранный корм'], 'remaining_uses' => 4,
    ]);
    $this->actingAs($owner->refresh());
    $dashboard = route('dashboard', ['pet' => $pet->id], absolute: false);

    $page = visit($dashboard);
    $page->click('#pet-care button[aria-label="Feed"]')
        ->assertSelected('.pet-care-supplies select', (string) $first->id)
        ->select('.pet-care-supplies select', (string) $selected->id)
        ->assertSelected('.pet-care-supplies select', (string) $selected->id)
        ->press('Start · 30 sec')
        ->assertMissing('[role="dialog"]')
        ->assertSeeIn('.pet-care-progress', 'A portion of food')
        ->assertPresent('#pet-care progress');

    $receipt = PetCareAction::query()->sole();
    expect($receipt->inventory_item_ids)->toBe(['food' => $selected->id]);
    $page->navigate($dashboard)
        ->assertSeeIn('.pet-care-progress', 'A portion of food')
        ->assertPresent('#pet-care progress')
        ->click('#pet-care button[aria-label="Feed"]')
        ->assertDisabled('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('[role="dialog"]', 'Your dog is busy with another activity.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('inventory_items', ['id' => $first->id, 'remaining_uses' => 3]);
    $this->assertDatabaseHas('inventory_items', ['id' => $selected->id, 'remaining_uses' => 3]);
    $this->assertDatabaseHas('item_usages', ['inventory_item_id' => $selected->id, 'uses_spent' => 1]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'completed_at' => null]);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('item_usages', 1);
});

test('the care timer applies the result automatically and reloading does not award it again', function (string $locale, int $width, string $colorScheme) {
    config(['pet_states.decay_per_hour.hydration' => 0]);
    $owner = User::factory()->create(['locale' => $locale]);
    $pet = Pet::factory()->for($owner)->create(['name' => 'Milo', 'hydration' => 20, 'hydration_max' => 100]);
    PetHistoryEvent::factory()->create(['code' => 'care.water']);
    $this->actingAs($owner->refresh());
    $dashboard = route('dashboard', ['pet' => $pet->id], absolute: false);

    [$feed, $start, $water, $hydration, $notifications, $completed] = $locale === 'ru'
        ? ['Покормить', 'Начать · 15 сек', 'Свежая вода', 'Запас воды', 'Уведомления', 'Уход завершён. Состояние собаки обновлено.']
        : ['Feed', 'Start · 15 sec', 'Fresh water', 'Hydration', 'Notifications', 'Care completed. Your dog’s condition has been updated.'];
    $toastIsInsideViewport = <<<'JS'
        (() => {
            const toast = document.querySelector('[data-sonner-toast][data-front="true"]');
            if (!toast) return false;
            const bounds = toast.getBoundingClientRect();
            return bounds.width > 0 && bounds.height > 0
                && bounds.top >= 0 && bounds.bottom <= window.innerHeight
                && bounds.left >= 0 && bounds.right <= window.innerWidth;
        })()
        JS;

    $page = visit($dashboard, ['viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme]);
    if ($width === 1440) {
        $page->hover('button[aria-label="Wellbeing"]')
            ->assertSeeIn('.help-hint-content[aria-label="Wellbeing"]', 'Below 25% — needs care.')
            ->click('button[aria-label="Wellbeing"]')
            ->assertSeeIn('.help-hint-content[aria-label="Wellbeing"]', 'Below 25% — needs care.')
            ->keys('button[aria-label="Wellbeing"]', 'Escape')
            ->assertMissing('.help-hint-content[aria-label="Wellbeing"]');
    }
    $page->click('#pet-care button[aria-label="'.$feed.'"]')
        ->click('[role="dialog"] input[value="water"]')
        ->press($start)
        ->assertSeeIn('.pet-care-progress', $water)
        ->assertPresent('#pet-care progress');
    $page->script('window.scrollTo({ top: 0, left: 0, behavior: "instant" })');
    $page->assertScript($toastIsInsideViewport, true);
    $this->travel(15)->seconds();

    /** Advance only the browser clock; its isolated context is discarded after the test. */
    $page->script('performance.now = ((original) => () => original() + 15000)(performance.now.bind(performance))');
    $page->assertMissing('.pet-care-progress')
        ->assertSee($completed);
    $page->script('window.scrollTo({ top: 0, left: 0, behavior: "instant" })');
    $page->assertScript($toastIsInsideViewport, true)
        ->assertPresent('section[aria-label^="'.$notifications.'"]')
        ->assertAttribute('progress[aria-label="'.$hydration.'"]', 'aria-valuetext', '55%')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'care-completion-'.$locale.'-'.$width)
        ->assertNoJavaScriptErrors();

    $receipt = PetCareAction::query()->sole();
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'completed_at' => now()->toDateTimeString()]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => 10]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 55, 'activity' => null]);
    $this->assertDatabaseHas('pet_history_entries', ['source_key' => 'care:'.$receipt->id.':completed']);
    $this->assertDatabaseCount('pet_history_entries', 2);
    expect($owner->fresh()->pet_statistics)->toBe(['care.water' => 1]);
    expect($owner->fresh()->experience)->toBe('10');

    $page->navigate($dashboard)
        ->assertPresent('#pet-care')
        ->assertMissing('.pet-care-progress')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('pet_history_entries', 2);
    expect($owner->fresh()->pet_statistics)->toBe(['care.water' => 1]);
    expect($owner->fresh()->experience)->toBe('10');
})->with([
    'English desktop light' => ['en', 1440, 'light'],
    'English tablet light' => ['en', 768, 'light'],
    'Russian mobile dark' => ['ru', 390, 'dark'],
]);
