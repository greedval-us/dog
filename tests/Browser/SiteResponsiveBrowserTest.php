<?php

use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Vite;
use Pest\Browser\Api\AwaitableWebpage;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

function responsiveBrowserOwner(string $locale): User
{
    $owner = User::factory()->create([
        'locale' => $locale,
        'username' => 'very_long_player_username_123456',
        'name' => 'AlexandertheGreatShepherdCompanion',
        'bio' => str_repeat('DogLiveCompanions', 30),
        'coins' => 999999999,
        'gems' => 999999999,
    ]);
    Pet::factory()->for($owner)->create(['name' => 'AlexandertheGreatShepherdCompanion']);
    $seller = User::factory()->create(['username' => 'seller_with_a_very_long_username']);
    $salePet = Pet::factory()->for($seller)->create(['name' => 'LongNamedShepherdLookingForAHome']);
    PetSaleListing::factory()->for($salePet)->for($seller)->create(['price' => 999999]);
    $owner->notify(SystemNotification::siteUpdate(
        ['en' => 'A new chapter for your companion', 'ru' => 'Новая глава жизни твоего питомца'],
        ['en' => 'Explore new activities and keep caring for your companion.', 'ru' => 'Открывай новые занятия и продолжай заботиться о своём питомце.'],
    ));

    return $owner;
}

function assertResponsiveBrowserPage(AwaitableWebpage $page, string $name): void
{
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: false, filename: 'site-responsive-'.str_replace('.', '-', $name).'.png');
}

test('mobile navigation closes with escape and reaches the game sections with long player data', function (string $locale, int $width, string $colorScheme) {
    $owner = responsiveBrowserOwner($locale);
    $this->actingAs($owner);
    $page = visit(route('dashboard', absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ])->assertPresent('.pet-hero');
    $name = $locale.'-'.$width.'-'.$colorScheme;

    assertResponsiveBrowserPage($page, 'dashboard-'.$name);
    $page->assertScript(<<<'JS'
        (() => {
            const wallet = document.querySelector('.wallet').getBoundingClientRect();
            return Array.from(document.querySelectorAll('.header-tools button')).every((control) => {
                const bounds = control.getBoundingClientRect();
                return bounds.right <= wallet.left || wallet.right <= bounds.left
                    || bounds.bottom <= wallet.top || wallet.bottom <= bounds.top;
            });
        })()
        JS, true);
    $page->click('#mobile-menu-toggle')
        ->assertAttribute('#mobile-menu-toggle', 'aria-expanded', 'true')
        ->assertVisible('#mobile-navigation')
        ->keys('#mobile-navigation a:first-child', 'Escape')
        ->assertAttribute('#mobile-menu-toggle', 'aria-expanded', 'false')
        ->assertPresent('#mobile-menu-toggle:focus')
        ->assertMissing('#mobile-navigation');
    $page->click('.notification-bell')
        ->assertVisible('.notification-panel')
        ->assertSeeIn('.notification-panel', $locale === 'ru' ? 'Новая глава жизни твоего питомца' : 'A new chapter for your companion');
    assertResponsiveBrowserPage($page, 'notifications-'.$name);
    $page->keys('.notification-header button', 'Escape')
        ->assertMissing('.notification-panel');

    foreach (['profile.edit' => '.settings-stack', 'pet-market.index' => '.pet-market-card', 'game-events.index' => '.events-calendar'] as $route => $content) {
        $path = route($route, absolute: false);
        $page->click('#mobile-menu-toggle')->assertVisible('#mobile-navigation')
            ->click('#mobile-navigation a[href="'.$path.'"]')
            ->assertPathIs($path)
            ->assertPresent($content)
            ->assertAttribute('#mobile-menu-toggle', 'aria-expanded', 'false')
            ->assertMissing('#mobile-navigation');
        assertResponsiveBrowserPage($page, $route.'-'.$name);
    }
})->with([
    'Russian narrow phone dark' => ['ru', 320, 'dark'],
    'English phone light' => ['en', 390, 'light'],
]);

test('sidebar navigation reaches the game sections on tablet and desktop with long player data', function (string $locale, int $width, string $colorScheme) {
    $owner = responsiveBrowserOwner($locale);
    $this->actingAs($owner);
    $page = visit(route('dashboard', absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ])->assertPresent('.pet-hero');
    $name = $locale.'-'.$width.'-'.$colorScheme;

    assertResponsiveBrowserPage($page, 'dashboard-'.$name);
    $page->assertVisible('.doglive-sidebar')
        ->click('.notification-bell')
        ->assertVisible('.notification-panel');
    assertResponsiveBrowserPage($page, 'notifications-'.$name);
    $page->keys('.notification-header button', 'Escape')
        ->assertMissing('.notification-panel');

    foreach (['profile.edit' => '.settings-stack', 'pet-market.index' => '.pet-market-card', 'game-events.index' => '.events-calendar'] as $route => $content) {
        $path = route($route, absolute: false);
        $page->click('.doglive-sidebar a[href="'.$path.'"]')
            ->assertPathIs($path)
            ->assertPresent($content);
        assertResponsiveBrowserPage($page, $route.'-'.$name);
    }
})->with([
    'Russian tablet light' => ['ru', 768, 'light'],
    'English desktop dark' => ['en', 1440, 'dark'],
]);

test('public account links and language controls remain usable on narrow and wide screens', function (string $locale, int $width, string $colorScheme) {
    $page = visit(route('home', absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ])->assertPresent('.welcome-hero');
    $name = $locale.'-'.$width.'-'.$colorScheme;

    $page->click('.language-switcher button[lang="'.$locale.'"]')
        ->assertScript('document.documentElement.lang', $locale);
    assertResponsiveBrowserPage($page, 'welcome-'.$name);
    $page->click('.public-navigation a[href="'.route('login', absolute: false).'"]')
        ->assertPathIs(route('login', absolute: false))
        ->assertVisible('#email')
        ->assertVisible('#password');
    assertResponsiveBrowserPage($page, 'login-'.$name);
    $page->click('.auth-tabs a[href="'.route('register', absolute: false).'"]')
        ->assertPathIs(route('register', absolute: false))
        ->assertVisible('#username')
        ->assertVisible('#password_confirmation');
    assertResponsiveBrowserPage($page, 'register-'.$name);
})->with([
    'Russian narrow phone dark' => ['ru', 320, 'dark'],
    'English desktop light' => ['en', 1440, 'light'],
]);
