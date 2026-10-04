<?php

use App\Modules\Pets\Calculators\GameEventRandomness;
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
    $reference = ['breed' => ['en' => 'Beagle'], 'breed_id' => 7, 'size' => 'medium', 'stats' => ['endurance' => 9999], 'gear' => [['id' => 99]]];
    $novice = $generator->generate($reference, 'novice:all', 'fixed-club-seed', 2);
    $reference['stats'] = ['endurance' => 1];
    $reference['gear'] = [];

    expect($generator->generate($reference, 'novice:all', 'fixed-club-seed', 2))->toBe($novice);
    expect($novice)->toMatchArray(['name' => 'NPC #3', 'breed_id' => 7, 'size' => 'medium', 'gear' => [], 'career_experience' => 0]);
    $champion = $generator->generate($reference, 'champion:all', 'fixed-club-seed', 2);
    expect($champion['career_experience'])->toBe(80);
    expect($champion['offspring'][0]['titles'])->toHaveCount(1);
    foreach ($novice['stats'] as $stat => $value) {
        expect($champion['stats'][$stat] - $value)->toBe(70);
    }
});
