<?php

use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Generators\GameEventNpcGenerator;

test('event receipt tokens keep their established deterministic format', function () {
    $randomness = new GameEventRandomness;

    expect($randomness->token('abc'))->toBe('ba7816bf-8f01-4fea-8141-40de5dae2223');
    expect($randomness->token('abc'))->not->toBe($randomness->token('abc:event:2'));
});

test('event draws are independent of processing order and requested sequence length', function () {
    $randomness = new GameEventRandomness;
    $first = $randomness->draws('entry:first', 6);
    $other = $randomness->draws('entry:other', 6);

    expect($randomness->draws('entry:first', 6))->toBe($first)->not->toBe($other);
    expect(array_slice($randomness->draws('entry:first', 12), 0, 6))->toBe($first);
    foreach ($first as $draw) {
        expect($draw)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }
});

test('club participants are reproducible and use the class rather than copying human advantages', function () {
    $generator = new GameEventNpcGenerator(new GameEventRandomness);
    $reference = ['breed' => ['en' => 'Beagle'], 'breed_id' => 7, 'size' => 'medium', 'stats' => ['endurance' => 9999], 'gear' => [['id' => 99]], 'offspring' => [['exterior' => ['type' => 100]]]];
    $novice = $generator->generate($reference, 'novice:all', 'fixed-club-seed', 2);
    $reference['stats'] = ['endurance' => 1];
    $reference['gear'] = [];
    $reference['offspring'] = [];

    expect($generator->generate($reference, 'novice:all', 'fixed-club-seed', 2))->toBe($novice);
    expect($novice)->toMatchArray(['name' => 'NPC #3', 'breed_id' => 7, 'size' => 'medium', 'gear' => [], 'career_experience' => 0]);
    $champion = $generator->generate($reference, 'champion:all', 'fixed-club-seed', 2);
    expect($champion['career_experience'])->toBe(80);
    expect(count($champion['offspring'][0]['titles']))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);
    foreach ($novice['stats'] as $stat => $value) {
        expect($champion['stats'][$stat] - $value)->toBe(70);
    }
});

test('club descendants have distinct bounded profiles and reproduce independently of generation order', function (string $division) {
    $generator = new GameEventNpcGenerator(new GameEventRandomness);
    $reference = ['breed' => ['en' => 'Beagle'], 'breed_id' => 7, 'size' => 'medium'];
    $snapshot = $generator->generate($reference, $division, 'family-seed', 0);
    $other = $generator->generate($reference, $division, 'other-family-seed', 0);

    expect($generator->generate($reference, $division, 'family-seed', 0))->toBe($snapshot);
    expect($snapshot['offspring'])->toHaveCount(3)->not->toBe($other['offspring']);
    foreach (['type', 'structure', 'movement'] as $trait) {
        $values = array_column(array_column($snapshot['offspring'], 'exterior'), $trait);
        expect(array_unique($values))->toHaveCount(3);
        expect(min($values))->toBeGreaterThanOrEqual(0.0);
        expect(max($values))->toBeLessThanOrEqual(100.0);
        expect(max($values) - min($values))->toBeLessThanOrEqual(10.0);
    }
})->with(['novice:all', 'open:all', 'champion:all']);

test('progeny club classes improve in quality and achievements without automatically earning perfect uniformity or an unbeatable result', function () {
    $generator = new GameEventNpcGenerator(new GameEventRandomness);
    $simulator = new GameEventSimulator;
    $reference = ['breed' => ['en' => 'Beagle'], 'breed_id' => 7, 'size' => 'medium'];
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    $rules = ['version' => 2, 'stages' => ['type', 'uniformity', 'achievements']];
    $draws = array_fill(0, 6, 0.5);
    $results = [];
    foreach (['novice', 'open', 'champion'] as $tier) {
        $snapshot = $generator->generate($reference, $tier.':all', 'family-seed', 0);
        $results[$tier] = $simulator->simulate('progeny', $snapshot, $plan, $rules, $draws);
    }
    $player = $generator->generate($reference, 'champion:all', 'player-fixture', 0);
    $player['offspring'] = [
        ['exterior' => ['type' => 97], 'titles' => [['code' => 'conformation_daily_winner'], ['code' => 'conformation_weekly_winner'], ['code' => 'conformation_monthly_winner']]],
        ['exterior' => ['type' => 98], 'titles' => [['code' => 'conformation_daily_winner'], ['code' => 'conformation_weekly_winner'], ['code' => 'conformation_monthly_winner']]],
        ['exterior' => ['type' => 99], 'titles' => [['code' => 'conformation_daily_winner'], ['code' => 'conformation_weekly_winner'], ['code' => 'conformation_monthly_winner']]],
    ];

    $playerResult = $simulator->simulate('progeny', $player, $plan, $rules, $draws);

    expect($results['novice']['stages'][2]['score'])->toBe(0.0);
    expect($results['open']['stages'][0]['score'])->toBeGreaterThan($results['novice']['stages'][0]['score']);
    expect($results['champion']['stages'][0]['score'])->toBeGreaterThan($results['open']['stages'][0]['score']);
    expect($results['open']['stages'][2]['score'])->toBeLessThanOrEqual(25.0);
    expect($results['champion']['stages'][2]['score'])->toBeGreaterThan($results['open']['stages'][2]['score'])->toBeLessThanOrEqual(50.0);
    foreach ($results as $result) {
        expect($result['stages'][1]['score'])->toBeLessThan(100.0);
    }
    expect($simulator->compareResults('progeny', $playerResult, $results['champion']))->toBe(-1);
});
