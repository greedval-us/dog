<?php

use App\Http\Telegram\GameMenu;
use App\Http\Telegram\Menus\MenuView;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\Storage;

test('telegram section icons preserve translated labels and commands', function (string $locale, string $petsLabel, string $backLabel) {
    $view = app(MenuView::class);

    $pets = $view->button('pets', 'pets', $locale);
    $screen = $view->screen('<Profile> & details'."\n".'<unsafe>', [], $locale);

    expect($pets)->toBe(['text' => $petsLabel, 'command' => ['action' => 'pets']]);
    expect($view->translate('pets', $locale))->toBe(substr($petsLabel, strlen('🐾 ')));
    expect($screen->text)->toBe('<b>&lt;Profile&gt; &amp; details</b>'."\n".'&lt;unsafe&gt;');
    expect($screen->rows[0][0])->toBe(['text' => $backLabel, 'command' => ['action' => 'home']]);
    expect($view->commandButton('Unchanged dog name', ['action' => 'pet', 'petId' => 7]))
        ->toBe(['text' => 'Unchanged dog name', 'command' => ['action' => 'pet', 'petId' => 7]]);
})->with(['English' => ['en', '🐾 My dogs', '← Back'], 'Russian' => ['ru', '🐾 Мои собаки', '← Назад']]);

test('telegram dog cards pair their private portrait with readable needs and grouped attributes', function (string $locale) {
    $this->freezeSecond();
    Storage::fake('local');
    $path = 'breeds/german_shepherd/portrait.png';
    Storage::disk('local')->put($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $owner = User::factory()->create(['locale' => $locale, 'status' => PlayerStatus::Active]);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($owner)->for($breed)->create(['name' => '<Rex> & friend', 'satiety' => 40, 'satiety_max' => 100, 'energy' => 50, 'energy_max' => 100]);

    $screen = app(GameMenu::class)->render($owner, ['action' => 'pet', 'petId' => $pet->id]);

    expect($screen->photoPath)->toBe(realpath(Storage::disk('local')->path($path)));
    expect($screen->text)->toContain('<b>🐾 &lt;Rex&gt; &amp; friend · ', '■■□□□ 40%', '■■■□□ 50/100',
        trans('telegram.sections.needs', [], $locale), trans('telegram.sections.stats', [], $locale))
        ->not->toContain('<Rex>', 'telegram.');
    expect(mb_strlen(html_entity_decode(strip_tags($screen->text), ENT_QUOTES | ENT_HTML5, 'UTF-8')))->toBeLessThanOrEqual(1024);
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->all())->toContain('care.groups', 'care.variants', 'skills', 'work', 'clinic', 'pet.history');
})->with(['en', 'ru']);

test('telegram breed cards attach the breed portrait without changing age selection', function () {
    Storage::fake('local');
    $path = 'breeds/german_shepherd/portrait.png';
    Storage::disk('local')->put($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $owner = User::factory()->create(['locale' => 'en']);
    $breed = Dog::factory()->create(['breed' => 'german_shepherd', 'is_starter' => true, 'name' => ['en' => '<Shepherd>']]);

    $screen = app(GameMenu::class)->render($owner, ['action' => 'kennel.breed', 'dogId' => $breed->id]);

    expect($screen->photoPath)->toBe(realpath(Storage::disk('local')->path($path)));
    expect($screen->text)->toContain('<b>🐕 &lt;Shepherd&gt;</b>')->not->toContain('<Shepherd>');
    expect(collect($screen->rows)->flatten(1)->pluck('command')->all())->toContain(
        ['action' => 'kennel.prepare', 'dogId' => $breed->id, 'ageMonths' => 3],
        ['action' => 'kennel.prepare', 'dogId' => $breed->id, 'ageMonths' => 18],
    );
});

test('telegram home and profile show escaped headings and localized sections without email', function (string $locale) {
    $owner = User::factory()->create(['name' => '<Alice>', 'locale' => $locale]);
    $menu = app(GameMenu::class);

    $home = $menu->render($owner);
    $profile = $menu->render($owner, ['action' => 'profile']);

    expect($home->text)->toStartWith('<b>')->toContain('&lt;Alice&gt;')->not->toContain('<Alice>');
    expect($profile->text)->toStartWith('<b>👤 ')->toContain('&lt;Alice&gt;',
        trans('telegram.sections.progress', [], $locale), trans('telegram.sections.activity', [], $locale), trans('telegram.sections.achievements', [], $locale))
        ->not->toContain('<Alice>', $owner->email, 'telegram.');
    expect(collect($home->rows)->flatten(1)->pluck('command.action')->all())
        ->toContain('pets', 'profile', 'kennel', 'slots', 'inventory', 'shop', 'daily_work', 'work', 'events', 'notifications', 'help');
})->with(['en', 'ru']);
