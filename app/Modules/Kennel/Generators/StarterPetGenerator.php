<?php

namespace App\Modules\Kennel\Generators;

use App\Modules\Pets\DTO\NewPetData;
use App\Modules\Pets\Enums\PetSex;
use InvalidArgumentException;
use Random\Randomizer;

final class StarterPetGenerator
{
    public function __construct(private Randomizer $randomizer) {}

    /**
     * @param  list<string>  $coatColors
     * @param  array<string, int>  $breedPotentials
     */
    public function generate(string $name, array $coatColors, array $breedPotentials = []): NewPetData
    {
        if ($coatColors === []) {
            throw new InvalidArgumentException('At least one coat color is required.');
        }

        $sexes = PetSex::cases();

        $sex = $sexes[$this->randomizer->getInt(0, count($sexes) - 1)];
        $coatColor = $coatColors[$this->randomizer->getInt(0, count($coatColors) - 1)];
        $potentials = [];

        foreach ($breedPotentials as $column => $maximum) {
            $minimum = max(1, (int) ceil($maximum * 0.9));
            $maximum = min(2147483647, (int) floor($maximum * 1.1));
            $potentials[$column] = $this->randomizer->getInt($minimum, $maximum);
        }

        return new NewPetData(
            name: $name,
            sex: $sex,
            coatColor: $coatColor,
            potentials: $potentials,
        );
    }
}
