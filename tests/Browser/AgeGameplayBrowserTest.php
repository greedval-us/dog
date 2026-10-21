<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventSimulator;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

test('the kennel age choice is saved and shown after taking a free dog home', function (int $months, string $locale, int $width, string $age, string $submit) {
    Dog::factory()->create(['is_starter' => true]);
    $owner = User::factory()->create(['locale' => $locale, 'coins' => 37]);
    $this->actingAs($owner->refresh());
    $page = visit(route('kennel.index', absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900],
    ]);

    $page->click('input[name="age_months"][value="'.$months.'"]')
        ->assertChecked('input[name="age_months"][value="'.$months.'"]')
        ->fill('#pet-name', 'Milo')
        ->press($submit)
        ->assertSeeIn('.pet-hero h1', 'Milo')
        ->assertSeeIn('.pet-hero .pet-age-summary', $age)
        ->assertPresent('#pet-care')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->script('window.scrollTo({ top: 0, left: 0, behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'age-kennel-'.$months.'-'.$locale)
        ->assertNoJavaScriptErrors();

    $pet = $owner->pets()->sole();
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'name' => 'Milo', 'born_at' => now()->subDays($months)->toDateTimeString(),
    ]);
    expect($owner->fresh()->coins)->toBe(37);
    $this->assertDatabaseCount('currency_transactions', 0);

    $page->navigate(route('dashboard', ['pet' => $pet->id], absolute: false))
        ->assertSeeIn('.pet-hero .pet-age-summary', $age)
        ->assertAttribute('.pet-slot-action[aria-label="Milo"]', 'aria-current', 'page')
        ->assertNoJavaScriptErrors();
})->with([
    'three month puppy on English desktop' => [3, 'en', 1440, 'Age: 3 months', 'Take home for free'],
    'eighteen month adult on Russian mobile' => [18, 'ru', 390, 'Возраст: 1 год 6 месяцев', 'Забрать домой бесплатно'],
]);

test('a living retired dog keeps its dashboard slot and can receive care and a veterinary checkup', function () {
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 500]);
    $pet = Pet::factory()->for($owner)->retired()->create([
        'name' => 'Old Milo', 'born_at' => now()->subDays(120), 'lifespan_months' => 168,
        'health' => 65, 'health_max' => 100, 'hydration' => 20, 'hydration_max' => 100,
    ]);
    $this->actingAs($owner->refresh());
    $dashboard = route('dashboard', ['pet' => $pet->id], absolute: false);
    $page = visit($dashboard);

    $page->assertSeeIn('.pet-hero h1', 'Old Milo')
        ->assertSeeIn('.pet-hero .pet-age-summary', 'Age: 10 years')
        ->assertSeeIn('.pet-hero .pet-age-summary', 'Care continues and your dog keeps a slot.')
        ->assertSeeIn('.pet-lifecycle-status', 'Retired')
        ->assertAttribute('.pet-slot-action[aria-label="Old Milo"]', 'aria-current', 'page')
        ->assertPresent('.pet-slots-count[aria-label="Dogs in your care: 1"]')
        ->click('#pet-care button[aria-label="Feed"]')
        ->click('[role="dialog"] input[value="water"]')
        ->assertButtonEnabled('Start · 5 sec')
        ->keys('[role="dialog"] button[type="submit"]', 'Escape')
        ->assertMissing('[role="dialog"]');
    $page->script('window.scrollTo({ top: 0, left: 0, behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'age-retired-dashboard')
        ->navigate(route('veterinarian.index', ['pet' => $pet->id], absolute: false))
        ->assertSelected('#vet-dog', $pet->id)
        ->press('Pay 60 coins')
        ->assertSee('Your dog has already had its weekly checkup.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('veterinary_visits', ['pet_id' => $pet->id, 'service' => 'checkup', 'price_paid' => 60]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'health' => 75, 'died_at' => null]);
    expect($owner->fresh()->coins)->toBe(440);

    $page->navigate(route('players.memorial.index', ['user' => $owner->username], absolute: false))
        ->assertSeeIn('.pet-memorial-empty', 'Your memorial hall is empty. Dogs who have passed away will appear here.')
        ->assertMissing('.pet-memorial-card')
        ->navigate($dashboard)
        ->assertSeeIn('.pet-hero h1', 'Old Milo')
        ->assertPresent('#pet-care')
        ->assertPresent('.pet-slots-count[aria-label="Dogs in your care: 1"]')
        ->assertNoJavaScriptErrors();
});

test('a sixteen month dog can choose an overlapping exhibition class and its saved plan survives a reload', function () {
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 500]);
    $event = GameEvent::factory()->create([
        'discipline' => 'conformation',
        'rules' => [
            'version' => GameEventSimulator::VERSION, 'fee' => 25, 'prizes' => [100, 60, 40],
            'stages' => ['inspection', 'stance', 'movement'], 'energy_cost' => 10, 'field_size' => 8,
        ],
    ]);
    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Runa', 'born_at' => $event->starts_at->subDays(16),
    ]);
    $this->actingAs($owner->refresh());
    $eventPage = route('game-events.show', $event, absolute: false);
    $page = visit($eventPage, ['viewport' => ['width' => 390, 'height' => 900]]);

    $page->assertSelected('#event-dog', $pet->id)
        ->assertSeeIn('.event-entry-form', 'Age at the start: 1 year 4 months')
        ->assertSelected('#event-exhibition-class', 'junior')
        ->select('#event-exhibition-class', 'open')
        ->assertSelected('#event-exhibition-class', 'open')
        ->assertSeeIn('[data-testid="preparation-division"]', 'Open class')
        ->select('#event-exhibition-class', 'intermediate')
        ->assertSelected('#event-exhibition-class', 'intermediate')
        ->assertSeeIn('[data-testid="preparation-division"]', 'Intermediate')
        ->press('Enter for 25 coins')
        ->assertSee('Your entry')
        ->assertSelected('#event-exhibition-class', 'intermediate')
        ->assertNoJavaScriptErrors();

    $entry = GameEventEntry::query()->where('user_id', $owner->id)->sole();
    expect($entry->plan['exhibition_class'])->toBe('intermediate');
    expect($entry->division)->toBe('intermediate:breed-'.$pet->dog_id.':male');
    expect($owner->fresh()->coins)->toBe(475);
    $this->assertDatabaseCount('currency_transactions', 1);

    $page->navigate($eventPage)
        ->assertSelected('#event-exhibition-class', 'intermediate')
        ->assertSeeIn('[data-testid="preparation-division"]', 'Intermediate')
        ->assertButtonEnabled('Save performance plan')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->script('window.scrollBy({ top: document.querySelector(".event-entry-form").getBoundingClientRect().top - 150, behavior: "instant" })');
    $page->screenshot(fullPage: false, filename: 'age-exhibition-intermediate-mobile')
        ->assertNoJavaScriptErrors();
});

test('confirming a retired dog market purchase transfers its age and pays its former owner', function () {
    $seller = User::factory()->create(['locale' => 'en', 'coins' => 20]);
    $buyer = User::factory()->create(['locale' => 'en', 'coins' => 500]);
    $pet = Pet::factory()->for($seller)->retired()->create([
        'name' => 'Market Milo', 'born_at' => now()->subDays(120), 'lifespan_months' => 168,
    ]);
    $listing = PetSaleListing::factory()->for($pet)->create(['price' => 137]);
    $this->actingAs($buyer->refresh());
    $page = visit(route('pet-market.index', absolute: false), [
        'viewport' => ['width' => 390, 'height' => 900],
    ]);

    $page->assertSeeIn('.pet-market-card', 'Market Milo')
        ->assertSeeIn('.pet-market-card', 'Age: 10 years')
        ->assertSeeIn('.pet-market-retired', 'Retired')
        ->press('Buy this dog')
        ->assertSeeIn('[role="dialog"]', 'Take Market Milo home?')
        ->assertSeeIn('[role="dialog"]', 'This dog is retired. Care and veterinary help remain available; its career has ended.')
        ->assertButtonEnabled('Buy for 137 coins')
        ->screenshot(fullPage: false, filename: 'age-market-purchase-confirmation-mobile')
        ->press('Buy for 137 coins')
        ->assertSeeIn('.pet-hero h1', 'Market Milo')
        ->assertSeeIn('.pet-hero .pet-age-summary', 'Age: 10 years')
        ->assertSeeIn('.pet-lifecycle-status', 'Retired')
        ->assertAttribute('.pet-slot-action[aria-label="Market Milo"]', 'aria-current', 'page')
        ->assertPresent('#pet-care')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'user_id' => $buyer->id, 'born_at' => now()->subDays(120)->toDateTimeString(),
        'lifespan_months' => 168, 'retired_at' => now()->toDateTimeString(), 'died_at' => null,
    ]);
    $this->assertDatabaseHas('pet_sale_listings', ['id' => $listing->id, 'is_active' => false]);
    $this->assertDatabaseHas('pet_sale_purchases', [
        'listing_id' => $listing->id, 'pet_id' => $pet->id,
        'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'price_paid' => 137,
    ]);
    expect($buyer->fresh()->coins)->toBe(363);
    expect($seller->fresh()->coins)->toBe(157);
    $this->assertDatabaseCount('currency_transactions', 2);

    $page->navigate(route('dashboard', ['pet' => $pet->id], absolute: false))
        ->assertSeeIn('.pet-hero h1', 'Market Milo')
        ->assertPresent('.pet-slots-count[aria-label="Dogs in your care: 1"]')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('pet_sale_purchases', 1);
});
