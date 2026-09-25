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
