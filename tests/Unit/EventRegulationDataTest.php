<?php

use App\Models\GameEvent;
use App\Modules\Pets\DTO\EventRegulationData;
use Tests\TestCase;

uses(TestCase::class);

test('special show metadata and frozen source links are localized without changing the event snapshot', function () {
    $rules = ['version' => 3, 'regulation' => [
        'version' => 2, 'system' => 'rkf', 'rank' => 'cacib', 'country' => 'RU', 'region' => 'novosibirsk',
        'specialShow' => 'eurasia', 'showLeg' => 'president', 'showYear' => 2026, 'showEdition' => '2026-10',
        'sources' => [['url' => 'https://example.test/frozen-rules', 'key' => 'rkf_shows_2026']],
    ]];
    $event = new GameEvent(['discipline' => 'conformation', 'rules' => $rules]);

    $data = EventRegulationData::fromEvent($event, 'ru');

    expect($data)->toMatchArray([
        'specialShowLabel' => 'Евразия', 'showLegLabel' => 'Кубок Президента РКФ',
        'showYear' => 2026, 'showEdition' => '2026-10', 'regionLabel' => 'Новосибирская область',
        'sourceUrl' => 'https://example.test/frozen-rules',
    ]);
    expect($data['sources'][0])->toMatchArray(['url' => 'https://example.test/frozen-rules']);
    expect($event->rules)->toBe($rules);
});

test('legacy source fallback uses the actual discipline source key', function () {
    $event = new GameEvent(['discipline' => 'agility', 'rules' => ['version' => 3, 'regulation' => [
        'version' => 1, 'system' => 'fci', 'rank' => 'certificate', 'format' => 'agility',
    ]]]);

    $data = EventRegulationData::fromEvent($event, 'en');

    expect($data['sourceUrl'])->toBe(config('event_regulations.sources.fci_agility'));
    expect($data['sources'])->toHaveCount(1);
});

test('the legacy source URL cannot overwrite a frozen labeled source for the same address', function () {
    $source = ['key' => 'rkf_shows_2026', 'url' => 'https://example.test/frozen-rules', 'label' => 'Frozen show regulations'];
    $event = new GameEvent(['discipline' => 'conformation', 'rules' => ['version' => 3, 'regulation' => [
        'version' => 2, 'system' => 'rkf', 'rank' => 'cacib', 'sources' => [$source], 'sourceUrl' => $source['url'],
    ]]]);

    $data = EventRegulationData::fromEvent($event, 'en');

    expect($data['sources'])->toBe([$source]);
});

test('nosework certificate policy explains the championship award rather than implying every podium qualifies', function () {
    $event = new GameEvent(['discipline' => 'nosework', 'rules' => ['version' => 3, 'regulation' => [
        'version' => 2, 'system' => 'rkf', 'rank' => 'certificate', 'certificatePolicy' => 'nw_first_place',
    ]]]);

    $data = EventRegulationData::fromEvent($event, 'en');

    expect($data['policyNote'])->toBe('The championship certificate is awarded only for first place in the overall class standings.');
});
