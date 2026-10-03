<?php

namespace App\Modules\Pets\Generators;

use App\Modules\Pets\Calculators\BreedingGeneticsCalculator;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use InvalidArgumentException;
use Random\Randomizer;

final class PuppyGenerator
{
    public function __construct(private Randomizer $randomizer, private BreedingGeneticsCalculator $genetics) {}

    /**
     * @param  array<string, array{value: int, potential: int}>  $fatherStats
     * @param  array<string, array{value: int, potential: int}>  $motherStats
     * @param  array<string, int>  $colorWeights
     * @return list<array{sex: string, coat_color: string, potentials: array<string, int>}>
     */
    public function generate(array $fatherStats, array $motherStats, array $colorWeights): array
    {
        if ($colorWeights === [] || array_filter($colorWeights, static fn (int $weight): bool => $weight < 1) !== []) {
            throw new InvalidArgumentException('A positive offspring coat distribution is required.');
        }

        $ranges = [];

        foreach (PetStat::cases() as $stat) {
            if (! isset($fatherStats[$stat->value], $motherStats[$stat->value])) {
                throw new InvalidArgumentException('Both parents require all six characteristic snapshots.');
            }

            $father = $fatherStats[$stat->value];
            $mother = $motherStats[$stat->value];
            $ranges[$stat->value] = [
                $this->genetics->range($father['value'], $father['potential']),
                $this->genetics->range($mother['value'], $mother['potential']),
            ];
        }

        $puppies = [];
        $sexes = PetSex::cases();
        $count = $this->randomizer->getInt(2, 5);

        for ($index = 0; $index < $count; $index++) {
            $potentials = [];

            foreach (PetStat::cases() as $stat) {
                [$fatherRange, $motherRange] = $ranges[$stat->value];
                $father = $this->drawPotential($fatherRange);
                $mother = $this->drawPotential($motherRange);
                $spread = $this->randomizer->getInt(9800, 10200) / 10000;
                $potentials[$stat->value] = max(1, min(2147483647, (int) round(($father + $mother) / 2 * $spread)));
            }

            $puppies[] = [
                'sex' => $sexes[$this->randomizer->getInt(0, count($sexes) - 1)]->value,
                'coat_color' => $this->drawCoat($colorWeights),
                'potentials' => $potentials,
            ];
        }

        return $puppies;
    }

    /** @param array{min: float, max: float} $range */
    private function drawPotential(array $range): float
    {
        if ($range['min'] === $range['max']) {
            return $range['min'];
        }

        return $this->randomizer->getInt((int) ceil($range['min'] * 10000), (int) floor($range['max'] * 10000)) / 10000;
    }

    /** @param array<string, int> $colorWeights */
    private function drawCoat(array $colorWeights): string
    {
        $draw = $this->randomizer->getInt(1, array_sum($colorWeights));

        foreach ($colorWeights as $color => $weight) {
            $draw -= $weight;

            if ($draw <= 0) {
                return $color;
            }
        }

        throw new InvalidArgumentException('Invalid offspring coat distribution.');
    }
}
