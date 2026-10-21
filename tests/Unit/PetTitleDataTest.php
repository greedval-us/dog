<?php

use App\Models\PetTitle;
use App\Modules\Pets\DTO\PetTitleData;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

beforeEach(function () {
    $this->originalTitleContainer = Container::getInstance();
    $container = new Container;
    $container->instance('translator', new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'en'));
    Container::setInstance($container);
});

afterEach(function () {
    Container::setInstance($this->originalTitleContainer);
});

test('regulated awards retain their class program and odor in the localized name', function (string $locale, string $expected) {
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->fill([
        'code' => 'NW_ODOR_TEST', 'kind' => 'qualification', 'discipline' => 'nosework', 'frequency' => 'daily',
        'class_key' => 'nw_test', 'awarded_at' => '2026-10-20 18:00:00',
        'details' => ['program' => 'pr1', 'odor' => 'birch', 'format' => 'odor_test'],
    ]);

    $data = PetTitleData::fromTitle($title, $locale);

    expect($data['name'])->toBe($expected);
})->with([
    'English' => ['en', 'Odor recognition test passed · Odor recognition test · Program 1 · Birch tar'],
    'Russian' => ['ru', 'Пройден тест распознавания запаха · Тест распознавания запаха · Программа 1 · Берёзовый дёготь'],
]);

test('agility awards distinguish jumping and height categories', function () {
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->fill([
        'code' => 'PLACE_1', 'kind' => 'placement', 'discipline' => 'agility', 'frequency' => 'daily',
        'class_key' => 'agility_1', 'awarded_at' => '2026-10-20 18:00:00',
        'details' => ['format' => 'jumping', 'heightCategory' => 'I'],
    ]);

    $data = PetTitleData::fromTitle($title, 'en');

    expect($data['name'])->toBe('1st place in class · Agility 1 · Jumping · Height category I');
});

test('legacy awards keep their original name despite newer context fields', function () {
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->fill([
        'code' => 'agility_daily_winner', 'kind' => 'legacy', 'discipline' => 'agility', 'frequency' => 'daily',
        'class_key' => 'agility_1', 'awarded_at' => '2026-10-20 18:00:00',
        'details' => ['format' => 'jumping', 'heightCategory' => 'I'],
    ]);

    $data = PetTitleData::fromTitle($title, 'en');

    expect($data['name'])->toBe('Club winner: Agility');
});

test('regional diplomas name the subject and count their distinct supporting documents', function () {
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->fill([
        'code' => 'SPORT_REGIONAL_CH', 'kind' => 'championship', 'discipline' => 'agility', 'frequency' => 'weekly',
        'scope_key' => 'region:novosibirsk:proof', 'awarded_at' => '2026-10-20 18:00:00',
        'details' => ['region' => 'novosibirsk', 'credential_title_ids' => [1, 2, 2, 3]],
    ]);

    $data = PetTitleData::fromTitle($title, 'ru');

    expect($data['name'])->toBe('Региональный чемпион по рабочим качествам · Новосибирская область');
    expect($data['scopeKey'])->toBe('region:novosibirsk:proof');
    expect($data['sourceCount'])->toBe(3);
});

test('special show titles retain the annual and game edition context in their name', function () {
    $title = (new PetTitle)->setDateFormat('Y-m-d H:i:s')->fill([
        'code' => 'EAAW', 'kind' => 'championship', 'discipline' => 'conformation', 'frequency' => 'monthly',
        'scope_key' => 'edition:2026-10', 'awarded_at' => '2026-10-20 18:00:00',
        'details' => ['specialShow' => 'eurasia', 'showYear' => 2026, 'showEdition' => '2026-10'],
    ]);

    $data = PetTitleData::fromTitle($title, 'en');

    expect($data['name'])->toBe('Absolute Eurasia Winner · Game year: 2026 · Game edition: 2026-10');
});
