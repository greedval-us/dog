<?php

use App\Models\Dog;
use App\Modules\Pets\DTO\GameEventDivisionData;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

beforeEach(function () {
    $this->originalDivisionContainer = Container::getInstance();
    $container = new Container;
    $container->instance('translator', new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'en'));
    Container::setInstance($container);
});

afterEach(function () {
    Container::setInstance($this->originalDivisionContainer);
});

test('division labels describe the regulated category and preserve historical formats', function (string $division, string $locale, string $expected) {
    $label = GameEventDivisionData::label($division, $locale);

    expect($label)->toBe($expected);
})->with([
    'legacy size class' => ['novice:small', 'en', 'Novice · Small dogs'],
    'legacy masters tier' => ['champion:large', 'ru', 'Мастера · Крупные собаки'],
    'legacy heat suffix' => ['open:medium:heat-2', 'en', 'Open class · Medium dogs · Heat 2'],
    'legacy missing breed' => ['open:breed-17', 'ru', 'Открытый класс · breed-17'],
    'agility intermediate height' => ['agility_1:I', 'en', 'Agility 1 · Height category I'],
    'agility small height' => ['agility_2:S', 'ru', 'Аджилити-2 · Ростовая категория S'],
    'agility legacy heat suffix' => ['agility_3:L:heat-3', 'en', 'Agility 3 · Height category L · Heat 3'],
    'nosework zero class' => ['nw_0:pr1', 'ru', 'Ноузворк — нулевой класс · Программа 1'],
    'nosework second program' => ['nw_2:pr2', 'en', 'Nosework — class 2 · Program 2'],
    'nosework veterans' => ['nw_v:pr1', 'en', 'Nosework — veterans · Program 1'],
    'nosework odor test' => ['nw_test:pr1:birch', 'ru', 'Тест распознавания запаха · Программа 1 · Берёзовый дёготь'],
    'nosework second program odor' => ['nw_test:pr2:clove', 'en', 'Odor recognition test · Program 2 · Clove'],
    'canicross breed category' => ['canicross_open:3:open', 'ru', 'Каникросс — открытый класс · Породная категория 3 · Открытый зачёт'],
    'canicross russian category' => ['canicross_open:2-RUS:open', 'en', 'Canicross — open class · Breed category 2-RUS · Open standings'],
    'canicross breed standings without the catalogue row' => ['canicross_open:breed-17:open', 'en', 'Canicross — open class · Breed standings · Open standings'],
    'working show class without breed' => ['working:breed-17:female', 'ru', 'Рабочий класс · breed-17 · Суки'],
    'champion show class without breed' => ['champion:breed-17:male', 'en', 'Champion class · breed-17 · Male'],
    'progeny class without breed' => ['progeny:breed-17', 'en', 'Progeny competition · breed-17'],
]);

test('show divisions use the supplied breed name and the selected language', function (string $division, string $locale, string $expected) {
    $breed = new Dog(['breed' => 'beagle', 'name' => ['en' => 'Beagle', 'ru' => 'Бигль']]);

    $label = GameEventDivisionData::label($division, $locale, $breed);

    expect($label)->toBe($expected);
})->with([
    'legacy show heat' => ['open:breed-17:heat-2', 'ru', 'Открытый класс · Бигль · Группа 2'],
    'working female' => ['working:breed-17:female', 'ru', 'Рабочий класс · Бигль · Суки'],
    'champion male' => ['champion:breed-17:male', 'en', 'Champion class · Beagle · Male'],
    'progeny' => ['progeny:breed-17', 'en', 'Progeny competition · Beagle'],
    'canicross breed standings' => ['canicross_open:breed-17:open', 'ru', 'Каникросс — открытый класс · Бигль · Открытый зачёт'],
]);

test('division heat parsing only removes a trailing positive heat suffix', function () {
    expect(GameEventDivisionData::base('nw_test:pr2:orange:heat-12'))->toBe('nw_test:pr2:orange');
    expect(GameEventDivisionData::heat('nw_test:pr2:orange:heat-12'))->toBe(12);
    expect(GameEventDivisionData::base('agility_1:I'))->toBe('agility_1:I');
    expect(GameEventDivisionData::heat('agility_1:I'))->toBe(1);
});
