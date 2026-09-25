<?php

namespace App\Modules\Kennel\Generators;

use App\Modules\Pets\DTO\NewPetData;
use App\Modules\Pets\Enums\PetSex;
use InvalidArgumentException;
use Random\Randomizer;

final class StarterPetGenerator
{
    public function __construct(private Randomizer $randomizer) {}

    /** @param list<string> $coatColors */
    public function generate(string $name, array $coatColors): NewPetData
    {
        if ($coatColors === []) {
            throw new InvalidArgumentException('At least one coat color is required.');
        }

        $sexes = PetSex::cases();

        return new NewPetData(
            name: $name,
            sex: $sexes[$this->randomizer->getInt(0, count($sexes) - 1)],
            coatColor: $coatColors[$this->randomizer->getInt(0, count($coatColors) - 1)],
        );
    }
}
