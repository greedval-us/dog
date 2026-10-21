<?php

use App\Models\BreedingListing;
use App\Models\BreedingLitter;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\Puppy;
use App\Models\User;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    config(['inertia.ssr.enabled' => false]);
    $this->freezeSecond();
});

test('a single dog can be selected, bred and bring a puppy home', function (string $locale, int $width, string $colorScheme) {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $breed = Dog::query()->where('breed', 'german_shepherd')->firstOrFail();
    $owner = User::factory()->create(['locale' => $locale, 'experience' => '1500', 'coins' => 1000, 'pet_slots' => 2]);
    $mother = Pet::factory()->female()->for($owner)->for($breed)->create(['name' => 'Milo']);
    $this->actingAs($owner->refresh());
    [$choose, $forecast, $expected, $keep, $confirm] = $locale === 'ru'
        ? ['Выбери собаку', 'Прогноз щенков', 'Ожидаемые помёты', 'Оставить щенка', 'Подтвердить']
        : ['Choose a dog', 'Puppy forecast', 'Expected litters', 'Keep puppy', 'Confirm'];

    $page = visit(route('breeding.index', absolute: false), [
        'viewport' => ['width' => $width, 'height' => 900], 'colorScheme' => $colorScheme,
    ]);
    $page->assertSelected('#breeding-dog', '')
        ->assertScript('document.querySelector("#breeding-dog").selectedOptions[0].textContent.trim()', $choose)
        ->select('#breeding-dog', (string) $mother->id)
        ->assertSelected('#breeding-dog', (string) $mother->id)
        ->click('.breeding-offer .ui-button')
        ->assertSeeIn('.breeding-preview', $forecast)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: true, filename: 'breeding-forecast-'.$locale.'-'.$width)
        ->click('.breeding-confirm > .ui-button')
        ->assertPresent('[role="dialog"]')
        ->click('[role="dialog"] .ui-button--primary')
        ->assertMissing('[role="dialog"]')
        ->assertSee($expected);

    $litter = BreedingLitter::query()->sole();
    expect($owner->fresh()->coins)->toBe(900);
    $this->assertDatabaseCount('breeding_litters', 1);
    $this->travel(15)->seconds();
    $page->script('performance.now = ((original) => () => original() + 15000)(performance.now.bind(performance))');
    $page->assertMissing('.breeding-pregnancy')
        ->assertPresent('.puppy-card')
        ->assertSee($keep)
        ->click('.puppy-card:first-child .puppy-actions .ui-button--primary')
        ->assertPresent('#puppy-keep-name')
        ->fill('#puppy-keep-name', 'Nova')
        ->press($confirm)
        ->assertMissing('[role="dialog"]')
        ->assertPresent('#pet-care')
        ->assertSee('Nova')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'breeding-puppy-home-'.$locale.'-'.$width)
        ->assertNoJavaScriptErrors();

    $puppy = Puppy::query()->whereNotNull('pet_id')->sole();
    $this->assertDatabaseHas('puppies', ['id' => $puppy->id, 'status' => 'placed', 'user_id' => $owner->id]);
    $this->assertDatabaseHas('pets', ['id' => $puppy->pet_id, 'name' => 'Nova', 'user_id' => $owner->id]);
    expect(Pet::query()->findOrFail($puppy->pet_id)->born_at->equalTo($litter->born_at))->toBeTrue();
    expect($owner->pets()->active()->count())->toBe(2);
    $this->assertDatabaseCount('puppy_placements', 1);
})->with([
    'English desktop light' => ['en', 1440, 'light'],
    'Russian mobile dark' => ['ru', 390, 'dark'],
]);

test('the stud offer form preserves its saved fee and updates the visible offer', function () {
    $owner = User::factory()->create(['locale' => 'en', 'experience' => '1500']);
    $father = Pet::factory()->for($owner)->create(['name' => 'Leo']);
    $listing = BreedingListing::factory()->for($owner)->for($father, 'pet')->create(['price' => 725, 'is_active' => true]);
    $this->actingAs($owner->refresh());

    $page = visit(route('breeding.index', ['pet' => $father->id], absolute: false));
    $page->assertValue('#breeding-price', '725')
        ->fill('#breeding-price', '900')
        ->press('Publish or update offer')
        ->assertSeeIn('.breeding-own-offer', '900 coins')
        ->assertValue('#breeding-price', '900')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('breeding_listings', ['id' => $listing->id, 'price' => 900, 'is_active' => true]);
    $this->assertDatabaseCount('breeding_listings', 1);
});
