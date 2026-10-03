<?php

use App\Modules\Pets\Calculators\BreedingGeneticsCalculator;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Generators\PuppyGenerator;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Randomizer;

/** @return array<string, array{value: int, potential: int}> */
function puppyGeneratorParentStats(int $value, int $potential): array
{
    $stats = [];

    foreach (PetStat::cases() as $stat) {
        $stats[$stat->value] = ['value' => $value, 'potential' => $potential];
    }

    return $stats;
}

test('puppy potential averages both personal parental limits before the two percent litter spread', function (int $draw, int $expected) {
    $engine = new class($draw) implements Engine
    {
        public function __construct(private int $draw) {}

        public function generate(): string
        {
            return pack('V', $this->draw);
        }
    };
    $generator = new PuppyGenerator(new Randomizer($engine), new BreedingGeneticsCalculator);

    $puppies = $generator->generate(puppyGeneratorParentStats(50, 100), puppyGeneratorParentStats(100, 200), ['black' => 10000]);

    expect($puppies)->toHaveCount(2);
    foreach ($puppies as $puppy) {
        foreach ($puppy['potentials'] as $potential) {
            expect($potential)->toBe($expected);
        }
    }
})->with([
    'lower spread edge' => [0, 147],
    'upper spread edge' => [400, 153],
]);

test('litter size is between two and five with varying siblings and valid independent inherited statistics', function () {
    $generator = new PuppyGenerator(new Randomizer(new Mt19937(123)), new BreedingGeneticsCalculator);
    $sizes = [];
    $values = [];

    for ($index = 0; $index < 20; $index++) {
        $puppies = $generator->generate(puppyGeneratorParentStats(90, 100), puppyGeneratorParentStats(70, 100), ['black' => 9990, 'liver' => 10]);
        $sizes[] = count($puppies);

        foreach ($puppies as $puppy) {
            expect($puppy['sex'])->toBeIn(['male', 'female']);
            expect($puppy['coat_color'])->toBeIn(['black', 'liver']);
            expect(array_keys($puppy['potentials']))->toBe(['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence']);

            foreach ($puppy['potentials'] as $potential) {
                expect($potential)->toBeGreaterThanOrEqual(102)->toBeLessThanOrEqual(110);
            }

            $values[] = $puppy['potentials']['endurance'];
        }
    }

    expect(min($sizes))->toBe(2);
    expect(max($sizes))->toBe(5);
    expect(count(array_unique($values)))->toBeGreaterThan(1);
});

test('weighted coat selection can reach both the common and rare buckets', function (int $draw, string $coat) {
    $engine = new class($draw) implements Engine
    {
        public function __construct(private int $draw) {}

        public function generate(): string
        {
            return pack('V', $this->draw);
        }
    };
    $generator = new PuppyGenerator(new Randomizer($engine), new BreedingGeneticsCalculator);

    $puppies = $generator->generate(puppyGeneratorParentStats(50, 100), puppyGeneratorParentStats(50, 100), ['black' => 9990, 'liver' => 10]);

    foreach ($puppies as $puppy) {
        expect($puppy['coat_color'])->toBe($coat);
    }
})->with([
    'first weighted bucket' => [0, 'black'],
    'last weighted bucket' => [9999, 'liver'],
]);

test('offspring genetics stay positive and do not overflow database integers', function (int $value, int $potential, int $expected) {
    $generator = new PuppyGenerator(new Randomizer(new Mt19937(123)), new BreedingGeneticsCalculator);

    $puppies = $generator->generate(puppyGeneratorParentStats($value, $potential), puppyGeneratorParentStats($value, $potential), ['black' => 1]);

    foreach ($puppies as $puppy) {
        foreach ($puppy['potentials'] as $maximum) {
            expect($maximum)->toBe($expected);
        }
    }
})->with([
    'minimum positive value' => [0, 1, 1],
    'maximum integer genetic limit' => [2147483647, 2147483647, 2147483647],
]);

test('litter generation rejects missing parent characteristics', function () {
    $generator = new PuppyGenerator(new Randomizer(new Mt19937(123)), new BreedingGeneticsCalculator);
    $mother = puppyGeneratorParentStats(50, 100);
    unset($mother['intelligence']);

    expect(fn () => $generator->generate(puppyGeneratorParentStats(50, 100), $mother, ['black' => 1]))
        ->toThrow(InvalidArgumentException::class);
});

test('litter generation rejects empty or nonpositive color weights', function (array $weights) {
    $generator = new PuppyGenerator(new Randomizer(new Mt19937(123)), new BreedingGeneticsCalculator);

    expect(fn () => $generator->generate(puppyGeneratorParentStats(50, 100), puppyGeneratorParentStats(50, 100), $weights))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'no coats' => [[]],
    'zero weight' => [['black' => 0]],
    'negative weight' => [['black' => -1]],
]);
