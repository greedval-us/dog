<?php

use App\Modules\Kennel\Generators\StarterPetGenerator;
use App\Modules\Pets\Enums\PetSex;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Randomizer;

test('starter pet selection follows the supplied randomness and available coats', function (int $draw, PetSex $sex, string $coat) {
    $engine = new class($draw) implements Engine
    {
        public function __construct(private int $draw) {}

        public function generate(): string
        {
            return pack('V', $this->draw);
        }
    };

    $pet = (new StarterPetGenerator(new Randomizer($engine)))->generate('Рэй', ['black', 'brown']);

    expect($pet->name)->toBe('Рэй')
        ->and($pet->sex)->toBe($sex)
        ->and($pet->coatColor)->toBe($coat);
})->with([
    'first choices' => [0, PetSex::Male, 'black'],
    'last choices' => [1, PetSex::Female, 'brown'],
]);

test('a breed with one available coat always receives that coat', function () {
    $generator = new StarterPetGenerator(new Randomizer(new Mt19937(123)));

    expect($generator->generate('Рэй', ['black'])->coatColor)->toBe('black');
});

test('starter pet generation rejects an empty coat selection', function () {
    $generator = new StarterPetGenerator(new Randomizer(new Mt19937(123)));

    expect(fn () => $generator->generate('Рэй', []))->toThrow(InvalidArgumentException::class);
});

test('individual genetic limits include both ends of the ten percent spread', function (int $draw, int $expected) {
    $engine = new class($draw) implements Engine
    {
        public function __construct(private int $draw) {}

        public function generate(): string
        {
            return pack('V', $this->draw);
        }
    };
    $generator = new StarterPetGenerator(new Randomizer($engine));

    $pet = $generator->generate('Рэй', ['black'], ['endurance_potential' => 100]);

    expect($pet->potentials)->toBe(['endurance_potential' => $expected]);
})->with([
    'lower limit' => [0, 90],
    'upper limit' => [20, 110],
]);

test('dogs from the same breed receive varying independent genetic limits', function () {
    $generator = new StarterPetGenerator(new Randomizer(new Mt19937(123)));
    $breedPotentials = [
        'endurance_potential' => 100, 'speed_potential' => 85,
        'strength_potential' => 55, 'agility_potential' => 100,
        'obedience_potential' => 110, 'intelligence_potential' => 105,
    ];
    $ranges = [
        'endurance_potential' => [90, 110], 'speed_potential' => [77, 93],
        'strength_potential' => [50, 60], 'agility_potential' => [90, 110],
        'obedience_potential' => [99, 121], 'intelligence_potential' => [95, 115],
    ];
    $generated = [];

    for ($index = 0; $index < 20; $index++) {
        $generated[] = $generator->generate('Рэй', ['black'], $breedPotentials)->potentials;
    }

    foreach ($ranges as $column => [$minimum, $maximum]) {
        $values = array_column($generated, $column);

        expect(min($values))->toBeGreaterThanOrEqual($minimum);
        expect(max($values))->toBeLessThanOrEqual($maximum);
        expect(count(array_unique($values)))->toBeGreaterThan(1);
    }
});

test('genetic limits remain positive and fit the database integer columns', function (int $breedMaximum, int $minimum, int $maximum) {
    $generator = new StarterPetGenerator(new Randomizer(new Mt19937(123)));

    $pet = $generator->generate('Рэй', ['black'], ['endurance_potential' => $breedMaximum]);

    expect($pet->potentials['endurance_potential'])->toBeGreaterThanOrEqual($minimum)
        ->toBeLessThanOrEqual($maximum);
})->with([
    'smallest maximum' => [1, 1, 1],
    'largest maximum' => [2147483647, 1932735283, 2147483647],
]);
